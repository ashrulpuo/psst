<?php

namespace Tests\Feature\Messaging;

use App\Actions\Messaging\PostMessage;
use App\Events\ThreadBlockChanged;
use App\Messaging\CannotMessage;
use App\Models\Block;
use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Admin\AdminTestCase;
use Tests\TestCase;

class GuestSendTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alice = User::factory()->create(['username' => 'alice']);
    }

    /** @return array{0: string, 1: string} thread id, guest token */
    private function guestSend(string $body = 'hello', ?string $token = null, string $ip = '203.0.113.5'): array
    {
        $this->flushHeaders(); // headers set by withHeaders() would otherwise leak into the next call
        $headers = $token ? ['X-Guest-Token' => $token] : [];

        $response = $this->withHeaders($headers)
            ->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/profiles/alice/messages', ['body' => $body])
            ->assertCreated()
            ->assertJsonPath('data.sent', true);

        return [Thread::query()->latest('id')->value('id'), $response->json('guest_token')];
    }

    public function test_anyone_can_send_without_an_account_and_gets_a_token(): void
    {
        [, $token] = $this->guestSend('you are great');

        $this->assertMatchesRegularExpression('/^[a-f0-9]{48}$/', $token);

        $thread = Thread::query()->firstOrFail();
        $this->assertNull($thread->sender_id);
        $this->assertSame(hash('sha256', $token), $thread->guest_key_hash);
        $this->assertSame(1, $thread->anon_label);
        $this->assertSame(1, $thread->receiver_unread_count);

        Sanctum::actingAs($this->alice);
        $this->getJson('/api/inbox')
            ->assertOk()
            ->assertJsonPath('data.0.label', 'Anonymous #1')
            ->assertJsonPath('data.0.can_chat', false)
            ->assertJsonPath('data.0.last_message.body', 'you are great');
    }

    public function test_the_same_browser_continues_one_thread_and_a_new_browser_gets_the_next_label(): void
    {
        [$first, $token] = $this->guestSend('one');
        [$again] = $this->guestSend('two', $token);
        [$other] = $this->guestSend('three', null, '198.51.100.7');

        $this->assertSame($first, $again);
        $this->assertNotSame($first, $other);
        $this->assertSame(2, Message::query()->where('thread_id', $first)->count());
        $this->assertSame([1, 2], Thread::query()->orderBy('anon_label')->pluck('anon_label')->all());
    }

    public function test_nothing_that_identifies_a_guest_reaches_the_receiver(): void
    {
        $this->guestSend('secret-ish', null, '203.0.113.99');

        Sanctum::actingAs($this->alice);
        $json = $this->getJson('/api/inbox')->assertOk()->getContent();

        $this->assertStringNotContainsString('203.0.113.99', $json);
        $this->assertStringNotContainsString('guest_key', $json);
        $this->assertStringNotContainsString('ip_hash', $json);
        $this->assertStringNotContainsString('sender', $json);
    }

    public function test_the_receiver_cannot_reply_until_the_guest_signs_in(): void
    {
        [$threadId] = $this->guestSend();

        Sanctum::actingAs($this->alice);
        $this->postJson("/api/threads/$threadId/messages", ['body' => 'who is this?'])->assertStatus(409);
    }

    public function test_guests_are_not_capped_by_the_unanswered_rule(): void
    {
        $this->guestSend('first');
        $thread = Thread::query()->firstOrFail();

        foreach (range(1, 8) as $i) {
            app(PostMessage::class)->handle($thread, null, "again $i");
        }

        $this->assertSame(9, Message::query()->count());
    }

    public function test_a_filled_honeypot_looks_like_success_but_stores_nothing(): void
    {
        $this->postJson('/api/profiles/alice/messages', ['body' => 'buy pills', 'website' => 'http://spam'])
            ->assertCreated()
            ->assertJsonPath('data.sent', true);

        $this->assertSame(0, Message::query()->count());
    }

    public function test_blocking_a_guest_silently_drops_their_further_messages_from_the_device_and_network(): void
    {
        [$threadId, $token] = $this->guestSend('nasty', null, '203.0.113.5');

        Sanctum::actingAs($this->alice);
        $this->putJson("/api/threads/$threadId/block")->assertNoContent();
        $this->assertSame(0, Block::query()->count()); // keyed on the thread, not an account

        // Same browser: pretends to succeed.
        $this->app['auth']->forgetGuards();
        $this->guestSend('still here', $token, '198.51.100.1');
        // New browser, same network.
        $this->guestSend('new browser', null, '203.0.113.5');
        // Different browser and network: fine.
        $this->guestSend('someone else', null, '192.0.2.44');

        $this->assertSame(['nasty', 'someone else'], Message::query()->orderBy('id')->pluck('body')->all());
    }

    public function test_unblocking_lets_them_through_again(): void
    {
        [$threadId, $token] = $this->guestSend('hi');
        Sanctum::actingAs($this->alice);
        $this->putJson("/api/threads/$threadId/block")->assertNoContent();
        $this->deleteJson("/api/threads/$threadId/block")->assertNoContent();

        $this->app['auth']->forgetGuards();
        $this->guestSend('back', $token);

        $this->assertSame(2, Message::query()->count());
    }

    public function test_guest_sending_is_throttled_per_network(): void
    {
        foreach (range(1, 4) as $i) {
            $this->guestSend("m$i", null, '203.0.113.50');
            // distinct browsers so only the network limit can trigger
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])
            ->postJson('/api/profiles/alice/messages', ['body' => 'one too many'])
            ->assertStatus(429);

        RateLimiter::clear('g-ip:203.0.113.50');
    }

    public function test_banned_receivers_cannot_be_messaged(): void
    {
        $this->alice->forceFill(['banned_at' => now()])->save();

        $this->postJson('/api/profiles/alice/messages', ['body' => 'hi'])->assertNotFound();
    }

    public function test_an_invalid_body_is_rejected_for_guests_too(): void
    {
        $this->postJson('/api/profiles/alice/messages', ['body' => '   '])->assertUnprocessable();
    }

    // ---- bot challenge (optional) ----

    public function test_turnstile_blocks_a_failed_challenge_and_accepts_a_good_one(): void
    {
        config(['psst.turnstile.secret' => 'secret']);

        Http::fake(['challenges.cloudflare.com/*' => Http::sequence()
            ->push(['success' => false])
            ->push(['success' => true])]);

        $this->postJson('/api/profiles/alice/messages', ['body' => 'hi', 'turnstile_token' => 'bad'])->assertUnprocessable();
        $this->assertSame(0, Message::query()->count());

        $this->postJson('/api/profiles/alice/messages', ['body' => 'hi', 'turnstile_token' => 'good'])->assertCreated();
        $this->assertSame(1, Message::query()->count());
    }

    public function test_turnstile_fails_closed_when_the_check_is_unreachable(): void
    {
        config(['psst.turnstile.secret' => 'secret']);
        Http::fake(fn () => throw new ConnectionException('down'));

        $this->postJson('/api/profiles/alice/messages', ['body' => 'hi', 'turnstile_token' => 'x'])->assertUnprocessable();
    }

    public function test_signed_in_senders_do_not_need_a_challenge(): void
    {
        config(['psst.turnstile.secret' => 'secret']);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/profiles/alice/messages', ['body' => 'hi'])->assertCreated();
    }

    // ---- claiming: signing in is what unlocks chat ----

    public function test_signing_in_claims_the_thread_so_the_receiver_can_reply(): void
    {
        [$threadId, $token] = $this->guestSend('hello alice');
        $bob = User::factory()->create(['username' => 'bobby']);

        Sanctum::actingAs($bob);
        $this->withHeaders(['X-Guest-Token' => $token])->postJson('/api/guest/claim')->assertOk()->assertJsonPath('data.claimed', 1);

        $thread = Thread::query()->findOrFail($threadId);
        $this->assertSame($bob->id, $thread->sender_id);
        $this->assertNull($thread->guest_key_hash);
        $this->assertNull($thread->ip_hash);

        $this->getJson('/api/sent')->assertOk()->assertJsonPath('data.0.id', $threadId);

        Sanctum::actingAs($this->alice);
        $this->postJson("/api/threads/$threadId/messages", ['body' => 'hey! who is this?'])->assertCreated();
        $this->getJson('/api/inbox')->assertJsonPath('data.0.can_chat', true);
    }

    public function test_the_receiver_is_told_live_when_a_guest_signs_in(): void
    {
        Event::fake([ThreadBlockChanged::class]);
        [$threadId, $token] = $this->guestSend();

        Sanctum::actingAs(User::factory()->create());
        $this->withHeaders(['X-Guest-Token' => $token])->postJson('/api/guest/claim')->assertOk();

        Event::assertDispatched(
            ThreadBlockChanged::class,
            fn ($e) => $e->payload === ['thread_id' => $threadId, 'can_chat' => true],
        );
    }

    public function test_claiming_merges_into_an_existing_conversation_with_the_same_receiver(): void
    {
        $bob = User::factory()->create(['username' => 'bobby']);
        Sanctum::actingAs($bob);
        $this->postJson('/api/profiles/alice/messages', ['body' => 'as bob'])->assertCreated();
        $existing = Thread::query()->firstOrFail();

        $this->app['auth']->forgetGuards();
        [, $token] = $this->guestSend('as guest');
        $this->assertSame(2, Thread::query()->count());

        Sanctum::actingAs($bob);
        $this->withHeaders(['X-Guest-Token' => $token])->postJson('/api/guest/claim')->assertOk();

        $this->assertSame(1, Thread::query()->count());
        $this->assertSame(2, Message::query()->where('thread_id', $existing->id)->count());
        $this->assertSame(2, $existing->fresh()->receiver_unread_count);
    }

    public function test_a_blocked_guest_thread_stays_blocked_after_claiming(): void
    {
        [$threadId, $token] = $this->guestSend('rude');
        Sanctum::actingAs($this->alice);
        $this->putJson("/api/threads/$threadId/block")->assertNoContent();

        $bob = User::factory()->create();
        Sanctum::actingAs($bob);
        $this->withHeaders(['X-Guest-Token' => $token])->postJson('/api/guest/claim')->assertOk();

        $this->assertSame(1, Block::query()->where('sender_id', $bob->id)->count());
        $this->postJson('/api/profiles/alice/messages', ['body' => 'again'])->assertForbidden();
    }

    public function test_a_wrong_token_claims_nothing_and_you_cannot_claim_your_own_link(): void
    {
        [, $token] = $this->guestSend('hi');
        $bob = User::factory()->create();

        Sanctum::actingAs($bob);
        $this->withHeaders(['X-Guest-Token' => str_repeat('a', 48)])->postJson('/api/guest/claim')->assertJsonPath('data.claimed', 0);
        $this->postJson('/api/guest/claim')->assertJsonPath('data.claimed', 0);

        Sanctum::actingAs($this->alice);
        $this->withHeaders(['X-Guest-Token' => $token])->postJson('/api/guest/claim')->assertJsonPath('data.claimed', 0);
        $this->assertNull(Thread::query()->firstOrFail()->sender_id);
    }

    public function test_claiming_requires_sign_in(): void
    {
        $this->postJson('/api/guest/claim')->assertUnauthorized();
    }

    public function test_the_database_requires_exactly_one_kind_of_sender(): void
    {
        $this->expectException(QueryException::class);

        Thread::create(['receiver_id' => $this->alice->id, 'anon_label' => 9]);
    }

    public function test_posting_as_the_receiver_to_a_guest_thread_is_refused_at_the_action_level(): void
    {
        $this->guestSend();

        $this->expectException(CannotMessage::class);
        app(PostMessage::class)->handle(Thread::query()->firstOrFail(), $this->alice, 'hi');
    }
}

class GuestAdminVisibilityTest extends AdminTestCase
{
    public function test_admins_see_a_guest_thread_as_guest_with_no_identity(): void
    {
        $alice = User::factory()->create(['username' => 'alice']);
        $this->postJson('/api/profiles/alice/messages', ['body' => 'hi'])->assertCreated();
        $threadId = Thread::query()->value('id');

        $row = $this->asAdmin()->getJson('/api/admin/threads')->assertOk()->json('data.0');
        $this->assertNull($row['sender']);
        $this->assertSame('guest', $row['sender_kind']);
        $this->assertSame($alice->id, $row['receiver']['id']);

        $this->getJson("/api/admin/threads/$threadId")->assertOk()->assertJsonPath('data.sender_kind', 'guest');
    }
}
