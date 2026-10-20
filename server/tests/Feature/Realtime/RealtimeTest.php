<?php

namespace Tests\Feature\Realtime;

use App\Events\MessagePosted;
use App\Events\MessagesRead;
use App\Events\ThreadBlockChanged;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RealtimeTest extends TestCase
{
    use RefreshDatabase;

    private User $receiver;

    private User $sender;

    protected function setUp(): void
    {
        parent::setUp();

        $this->receiver = User::factory()->create(['username' => 'alice', 'name' => 'Alice Receiver']);
        $this->sender = User::factory()->create([
            'username' => 'sneaky_bob',
            'name' => 'Bob Secretname',
            'email' => 'bob.secret@example.com',
        ]);
    }

    private function as(User $user): static
    {
        Sanctum::actingAs($user);

        return $this;
    }

    private function send(string $body = 'hello'): void
    {
        $this->as($this->sender)->postJson('/api/profiles/alice/messages', ['body' => $body])->assertCreated();
    }

    /**
     * @return Collection<int, MessagePosted>
     */
    private function posted(): Collection
    {
        return Event::dispatched(MessagePosted::class)->map(fn ($args) => $args[0]);
    }

    public function test_a_new_message_is_pushed_to_both_participants_with_their_own_view(): void
    {
        Event::fake([MessagePosted::class]);

        $this->send('you are great');

        $events = $this->posted()->keyBy('recipientId');
        $this->assertCount(2, $events);

        $toReceiver = $events[$this->receiver->id]->payload;
        $this->assertSame('inbox', $toReceiver['box']);
        $this->assertSame('Anonymous #1', $toReceiver['thread']['label']);
        $this->assertSame(1, $toReceiver['thread']['unread_count']);
        $this->assertSame('you are great', $toReceiver['message']['body']);
        $this->assertFalse($toReceiver['message']['mine']);

        $toSender = $events[$this->sender->id]->payload;
        $this->assertSame('sent', $toSender['box']);
        $this->assertSame('alice', $toSender['thread']['receiver']['username']);
        $this->assertTrue($toSender['message']['mine']);
        $this->assertSame(0, $toSender['thread']['unread_count']);
    }

    public function test_nothing_addressed_to_the_receiver_reveals_the_sender(): void
    {
        Event::fake([MessagePosted::class, MessagesRead::class, ThreadBlockChanged::class]);

        $this->send();
        $thread = Thread::firstOrFail();
        $this->as($this->receiver)->postJson("/api/threads/{$thread->id}/messages", ['body' => 'who?'])->assertCreated();
        $this->as($this->sender)->postJson("/api/threads/{$thread->id}/read")->assertNoContent();
        $this->as($this->receiver)->putJson("/api/threads/{$thread->id}/block")->assertNoContent();

        $toReceiver = collect([MessagePosted::class, MessagesRead::class, ThreadBlockChanged::class])
            ->flatMap(fn ($class) => Event::dispatched($class)->map(fn ($args) => $args[0]))
            ->filter(fn ($event) => $event->recipientId === $this->receiver->id);

        $this->assertNotEmpty($toReceiver);

        foreach ($toReceiver as $event) {
            $json = json_encode($event->payload);

            foreach ([$this->sender->id, 'sneaky_bob', 'Bob Secretname', 'bob.secret@example.com'] as $secret) {
                $this->assertStringNotContainsString($secret, $json, "leaked [$secret] in ".$event::class);
            }
        }
    }

    public function test_a_reply_is_pushed_back_to_the_sender(): void
    {
        $this->send();
        $thread = Thread::firstOrFail();

        Event::fake([MessagePosted::class]);
        $this->as($this->receiver)->postJson("/api/threads/{$thread->id}/messages", ['body' => 'hi back'])->assertCreated();

        $toSender = $this->posted()->firstWhere('recipientId', $this->sender->id)->payload;
        $this->assertFalse($toSender['message']['mine']);
        $this->assertSame('hi back', $toSender['message']['body']);
        $this->assertSame(1, $toSender['thread']['unread_count']);
    }

    public function test_marking_read_notifies_only_the_other_side_and_only_when_something_changed(): void
    {
        $this->send();
        $thread = Thread::firstOrFail();

        Event::fake([MessagesRead::class]);

        $this->as($this->receiver)->postJson("/api/threads/{$thread->id}/read")->assertNoContent();
        Event::assertDispatched(MessagesRead::class, fn ($e) => $e->recipientId === $this->sender->id
            && $e->payload['thread_id'] === $thread->id);

        // Nothing new to read the second time: no event.
        Event::fake([MessagesRead::class]);
        $this->as($this->receiver)->postJson("/api/threads/{$thread->id}/read")->assertNoContent();
        Event::assertNotDispatched(MessagesRead::class);
    }

    public function test_blocking_updates_both_sides(): void
    {
        $this->send();
        $thread = Thread::firstOrFail();

        Event::fake([ThreadBlockChanged::class]);
        $this->as($this->receiver)->putJson("/api/threads/{$thread->id}/block")->assertNoContent();

        Event::assertDispatched(ThreadBlockChanged::class, fn ($e) => $e->recipientId === $this->receiver->id && $e->payload['blocked'] === true);
        Event::assertDispatched(ThreadBlockChanged::class, fn ($e) => $e->recipientId === $this->sender->id && $e->payload['can_reply'] === false);
    }

    public function test_events_target_the_recipients_private_channel_and_wait_for_commit(): void
    {
        Event::fake([MessagePosted::class]);
        $this->send();

        $event = $this->posted()->firstWhere('recipientId', $this->receiver->id);

        $this->assertSame(["private-users.{$this->receiver->id}"], array_map(fn ($c) => $c->name, $event->broadcastOn()));
        $this->assertSame('message.posted', $event->broadcastAs());
        $this->assertTrue($event->afterCommit);
    }

    // ---- channel authorization (real Reverb broadcaster, no network needed to sign) ----

    private function useReverbAuth(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => '1',
        ]);

        // Channel callbacks register on whichever driver is default at boot (null under test),
        // so register them again on the Reverb driver now that it is the default.
        require base_path('routes/channels.php');
    }

    public function test_users_can_authorize_their_own_channel_only(): void
    {
        $this->useReverbAuth();

        $this->as($this->receiver)->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-users.{$this->receiver->id}",
        ])->assertOk()->assertJsonStructure(['auth']);

        $this->as($this->receiver)->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-users.{$this->sender->id}",
        ])->assertForbidden();
    }

    public function test_guests_and_banned_users_cannot_authorize_channels(): void
    {
        $this->useReverbAuth();
        $channel = "private-users.{$this->receiver->id}";

        $this->postJson('/api/broadcasting/auth', ['socket_id' => '1.2', 'channel_name' => $channel])->assertUnauthorized();

        $banned = User::factory()->banned()->create();
        $this->as($banned)->postJson('/api/broadcasting/auth', [
            'socket_id' => '1.2',
            'channel_name' => "private-users.{$banned->id}",
        ])->assertForbidden();
    }
}
