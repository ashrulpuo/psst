<?php

namespace Tests\Feature\Realtime;

use App\Events\ReactionChanged;
use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReactionRealtimeTest extends TestCase
{
    use RefreshDatabase;

    private User $receiver;

    private User $sender;

    private Message $message;

    protected function setUp(): void
    {
        parent::setUp();

        $this->receiver = User::factory()->create(['username' => 'alice']);
        $this->sender = User::factory()->create(['username' => 'bob']);

        Sanctum::actingAs($this->sender);
        $this->postJson('/api/profiles/alice/messages', ['body' => 'hello'])->assertCreated();

        $this->message = Message::query()->sole();
    }

    /**
     * @return Collection<int, ReactionChanged>
     */
    private function react(User $user, string $emoji = '🔥')
    {
        Sanctum::actingAs($user);
        $this->postJson("/api/messages/{$this->message->id}/reactions", ['emoji' => $emoji])->assertOk();

        return Event::dispatched(ReactionChanged::class)->map(fn ($args) => $args[0]);
    }

    public function test_a_reaction_reaches_both_participants_with_their_own_mine_flag(): void
    {
        Event::fake([ReactionChanged::class]);

        $events = $this->react($this->receiver);

        $this->assertCount(2, $events);

        $toReceiver = $events->firstWhere('recipientId', $this->receiver->id);
        $toSender = $events->firstWhere('recipientId', $this->sender->id);

        $this->assertSame('🔥', $toReceiver->payload['reactions'][0]['emoji']);
        $this->assertSame(1, $toReceiver->payload['reactions'][0]['count']);
        $this->assertTrue($toReceiver->payload['reactions'][0]['mine']);
        $this->assertFalse($toSender->payload['reactions'][0]['mine']);

        $this->assertSame($this->message->id, $toReceiver->payload['message_id']);
        $this->assertSame(Thread::query()->sole()->id, $toReceiver->payload['thread_id']);
    }

    public function test_untoggling_pushes_an_empty_tally(): void
    {
        Event::fake([ReactionChanged::class]);

        $this->react($this->receiver);
        // Re-fake so only the untoggle is recorded.
        Event::fake([ReactionChanged::class]);
        $events = $this->react($this->receiver);

        $toReceiver = $events->firstWhere('recipientId', $this->receiver->id);

        $this->assertSame([], $toReceiver->payload['reactions']);
    }

    public function test_a_guest_thread_only_notifies_the_receiver(): void
    {
        Event::fake([ReactionChanged::class]);

        // No account behind the sender: there is no second channel to push to.
        $this->message->thread
            ->forceFill(['sender_id' => null, 'guest_key_hash' => str_repeat('a', 64)])
            ->save();

        $events = $this->react($this->receiver);

        $this->assertCount(1, $events);
        $this->assertSame($this->receiver->id, $events[0]->recipientId);
    }
}
