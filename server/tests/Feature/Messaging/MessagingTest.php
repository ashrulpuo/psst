<?php

namespace Tests\Feature\Messaging;

use App\Models\Message;
use App\Models\Report;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessagingTest extends TestCase
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
            'avatar_url' => 'https://example.com/bob-avatar.png',
        ]);
    }

    private function as(User $user): static
    {
        Sanctum::actingAs($user);

        return $this;
    }

    private function send(User $from, string $body = 'hello', string $to = 'alice'): TestResponse
    {
        return $this->as($from)->postJson("/api/profiles/$to/messages", ['body' => $body]);
    }

    // ---- public profile ----

    public function test_a_profile_is_public_and_exposes_no_private_fields(): void
    {
        $response = $this->getJson('/api/profiles/alice')->assertOk();

        $response->assertExactJson(['data' => [
            'username' => 'alice',
            'name' => 'Alice Receiver',
            'avatar_url' => $this->receiver->avatar_url,
        ]]);
    }

    public function test_unknown_and_banned_profiles_are_not_found(): void
    {
        User::factory()->banned()->create(['username' => 'banned_one']);

        $this->getJson('/api/profiles/nobody_here')->assertNotFound();
        $this->getJson('/api/profiles/banned_one')->assertNotFound();
    }

    // ---- sending ----

    public function test_sending_requires_sign_in(): void
    {
        $this->postJson('/api/profiles/alice/messages', ['body' => 'hi'])->assertUnauthorized();
    }

    public function test_first_message_opens_a_thread_labelled_anonymous_1(): void
    {
        $this->send($this->sender, 'you are great')
            ->assertCreated()
            ->assertJsonPath('data.body', 'you are great')
            ->assertJsonPath('data.mine', true);

        $this->as($this->receiver)->getJson('/api/inbox')
            ->assertOk()
            ->assertJsonPath('data.0.label', 'Anonymous #1')
            ->assertJsonPath('data.0.unread_count', 1)
            ->assertJsonPath('data.0.last_message.body', 'you are great')
            ->assertJsonPath('data.0.last_message.mine', false);
    }

    public function test_each_new_sender_gets_the_next_label_and_repeat_senders_reuse_their_thread(): void
    {
        $other = User::factory()->create();

        $this->send($this->sender, 'one');
        $this->send($other, 'two');
        $this->send($this->sender, 'three');

        $this->assertSame(2, Thread::count());
        $this->assertEqualsCanonicalizing(
            ['Anonymous #1', 'Anonymous #2'],
            $this->as($this->receiver)->getJson('/api/inbox')->collect('data')->pluck('label')->all(),
        );
    }

    public function test_you_cannot_message_yourself(): void
    {
        $this->send($this->receiver)->assertUnprocessable();
    }

    public function test_unknown_receivers_are_not_found(): void
    {
        $this->send($this->sender, 'hi', 'ghost_user')->assertNotFound();
    }

    public function test_message_bodies_are_validated(): void
    {
        $this->send($this->sender, '   ')->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->send($this->sender, str_repeat('a', Message::MAX_BODY_LENGTH + 1))->assertUnprocessable();
        $this->send($this->sender, "bad\x00byte")->assertUnprocessable();
        $this->send($this->sender, str_repeat('a', Message::MAX_BODY_LENGTH))->assertCreated();
    }

    public function test_html_is_stored_verbatim_and_returned_as_data(): void
    {
        $this->send($this->sender, '<script>alert(1)</script>')
            ->assertCreated()
            ->assertJsonPath('data.body', '<script>alert(1)</script>');
    }

    // ---- privacy ----

    public function test_nothing_about_the_sender_ever_reaches_the_receiver(): void
    {
        $this->send($this->sender, 'secret admirer here');
        $thread = Thread::firstOrFail();

        $payloads = [
            $this->as($this->receiver)->getJson('/api/inbox')->getContent(),
            $this->as($this->receiver)->getJson("/api/threads/{$thread->id}/messages")->getContent(),
        ];

        $this->as($this->receiver)->postJson("/api/threads/{$thread->id}/messages", ['body' => 'who are you?']);
        $payloads[] = $this->as($this->receiver)->getJson("/api/threads/{$thread->id}/messages")->getContent();

        foreach ($payloads as $payload) {
            foreach ([$this->sender->id, 'sneaky_bob', 'Bob Secretname', 'bob.secret@example.com', 'bob-avatar'] as $secret) {
                $this->assertStringNotContainsString($secret, $payload, "leaked [$secret]");
            }
        }
    }

    public function test_the_sender_only_sees_the_receivers_public_profile(): void
    {
        $this->send($this->sender, 'hi');

        $response = $this->as($this->sender)->getJson('/api/sent')->assertOk();

        $response->assertJsonPath('data.0.receiver.username', 'alice');
        $this->assertStringNotContainsString($this->receiver->email, $response->getContent());
        $this->assertStringNotContainsString($this->receiver->id, $response->getContent());
    }

    public function test_strangers_get_404_not_403_for_other_peoples_threads(): void
    {
        $this->send($this->sender, 'hi');
        $thread = Thread::firstOrFail();
        $stranger = User::factory()->create();

        $this->as($stranger)->getJson("/api/threads/{$thread->id}/messages")->assertNotFound();
        $this->as($stranger)->postJson("/api/threads/{$thread->id}/messages", ['body' => 'x'])->assertNotFound();
        $this->as($stranger)->postJson("/api/threads/{$thread->id}/read")->assertNotFound();
        $this->as($stranger)->putJson("/api/threads/{$thread->id}/block")->assertNotFound();
        $this->as($stranger)->getJson('/api/threads/not-a-uuid/messages')->assertNotFound();
    }

    // ---- conversation ----

    public function test_a_reply_reaches_the_sender_and_tracks_unread_counts(): void
    {
        $this->send($this->sender, 'question?');
        $thread = Thread::firstOrFail();

        $this->as($this->receiver)->postJson("/api/threads/{$thread->id}/messages", ['body' => 'answer!'])
            ->assertCreated()
            ->assertJsonPath('data.mine', true);

        $this->as($this->sender)->getJson('/api/sent')
            ->assertJsonPath('data.0.unread_count', 1)
            ->assertJsonPath('data.0.last_message.body', 'answer!')
            ->assertJsonPath('data.0.last_message.mine', false);

        $this->as($this->sender)->postJson("/api/threads/{$thread->id}/read")->assertNoContent();

        $this->as($this->sender)->getJson('/api/sent')->assertJsonPath('data.0.unread_count', 0);
        $this->assertNotNull(Message::where('body', '!=', null)->get()->firstWhere('from_receiver', true)->read_at);
        $this->assertNull(Message::get()->firstWhere('from_receiver', false)->read_at);
    }

    public function test_history_is_newest_first_and_paginates_by_cursor(): void
    {
        $this->send($this->sender, 'first');
        $thread = Thread::firstOrFail();

        // Alternate replies so the unanswered-message cap never trips.
        for ($i = 1; $i <= 34; $i++) {
            $author = $i % 2 ? $this->receiver : $this->sender;
            $this->as($author)->postJson("/api/threads/{$thread->id}/messages", ['body' => "m$i"])->assertCreated();
        }

        $page1 = $this->as($this->receiver)->getJson("/api/threads/{$thread->id}/messages")->assertOk();
        $page1->assertJsonCount(30, 'data')->assertJsonPath('data.0.body', 'm34');

        $page2 = $this->as($this->receiver)->getJson($page1->json('links.next') ?? $page1->json('meta.next_page_url'))->assertOk();
        $page2->assertJsonCount(5, 'data')->assertJsonPath('data.4.body', 'first');
    }

    public function test_the_inbox_runs_a_constant_number_of_queries(): void
    {
        foreach (range(1, 6) as $i) {
            $this->send(User::factory()->create(), "msg $i");
        }

        $this->as($this->receiver);
        \DB::enableQueryLog();
        $this->getJson('/api/inbox')->assertOk()->assertJsonCount(6, 'data');

        // user lookup + threads + latest messages: no per-thread queries (strict mode would also throw).
        $this->assertLessThanOrEqual(6, count(\DB::getQueryLog()));
    }

    public function test_a_sender_cannot_flood_a_thread_without_a_reply(): void
    {
        $limit = config('psst.messages.max_unanswered');

        foreach (range(1, $limit) as $i) {
            $this->send($this->sender, "spam $i")->assertCreated();
        }

        $this->send($this->sender, 'one more')->assertStatus(429);

        // A reply resets the allowance.
        $thread = Thread::firstOrFail();
        $this->as($this->receiver)->postJson("/api/threads/{$thread->id}/messages", ['body' => 'ok ok'])->assertCreated();
        $this->send($this->sender, 'thanks')->assertCreated();
    }

    public function test_the_per_user_send_rate_limit_applies(): void
    {
        foreach (range(1, 20) as $i) {
            User::factory()->create(['username' => "target_$i"]);
            $this->send($this->sender, 'hi', "target_$i")->assertCreated();
        }

        User::factory()->create(['username' => 'target_21']);
        $this->send($this->sender, 'hi', 'target_21')->assertStatus(429);
    }

    // ---- blocking ----

    public function test_only_the_receiver_can_block(): void
    {
        $this->send($this->sender, 'hi');
        $thread = Thread::firstOrFail();

        $this->as($this->sender)->putJson("/api/threads/{$thread->id}/block")->assertForbidden();
        $this->assertDatabaseCount('blocks', 0);
    }

    public function test_blocking_stops_the_sender_everywhere_and_unblocking_restores_them(): void
    {
        $this->send($this->sender, 'hi');
        $thread = Thread::firstOrFail();

        $this->as($this->receiver)->putJson("/api/threads/{$thread->id}/block")->assertNoContent();
        $this->as($this->receiver)->putJson("/api/threads/{$thread->id}/block")->assertNoContent(); // idempotent

        $this->as($this->sender)->postJson("/api/threads/{$thread->id}/messages", ['body' => 'again'])->assertForbidden();
        $this->send($this->sender, 'via link')->assertForbidden();
        $this->as($this->receiver)->getJson('/api/inbox')->assertJsonPath('data.0.blocked', true);
        $this->as($this->sender)->getJson('/api/sent')->assertJsonPath('data.0.can_reply', false);

        $this->as($this->receiver)->deleteJson("/api/threads/{$thread->id}/block")->assertNoContent();

        $this->send($this->sender, 'back again')->assertCreated();
        $this->assertDatabaseCount('blocks', 0);
    }

    public function test_a_blocked_sender_cannot_open_a_new_thread_after_the_fact(): void
    {
        $this->send($this->sender, 'hi');
        $this->as($this->receiver)->putJson('/api/threads/'.Thread::firstOrFail()->id.'/block');

        $this->assertSame(1, Thread::count());
        $this->send($this->sender, 'new account who dis')->assertForbidden();
        $this->assertSame(1, Thread::count());
    }

    // ---- reporting ----

    public function test_a_receiver_can_report_a_message_and_reporting_twice_is_harmless(): void
    {
        $this->send($this->sender, 'something nasty');
        $message = Message::firstOrFail();

        $this->as($this->receiver)->postJson("/api/messages/{$message->id}/report", ['reason' => 'harassment'])->assertAccepted();
        $this->as($this->receiver)->postJson("/api/messages/{$message->id}/report", ['reason' => 'harassment again'])->assertAccepted();

        $this->assertSame(1, Report::count());
        $this->assertDatabaseHas('reports', ['reporter_id' => $this->receiver->id, 'message_id' => $message->id, 'status' => 'open']);
    }

    public function test_you_cannot_report_your_own_message_or_someone_elses_thread(): void
    {
        $this->send($this->sender, 'hi');
        $message = Message::firstOrFail();

        $this->as($this->sender)->postJson("/api/messages/{$message->id}/report", ['reason' => 'self report'])->assertForbidden();
        $this->as(User::factory()->create())->postJson("/api/messages/{$message->id}/report", ['reason' => 'nosy'])->assertNotFound();
        $this->as($this->receiver)->postJson("/api/messages/{$message->id}/report", ['reason' => ''])->assertUnprocessable();

        $this->assertSame(0, Report::count());
    }

    public function test_a_banned_user_cannot_use_the_messaging_api(): void
    {
        $banned = User::factory()->banned()->create();

        $this->send($banned)->assertForbidden();
        $this->as($banned)->getJson('/api/inbox')->assertForbidden();
    }
}
