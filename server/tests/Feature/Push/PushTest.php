<?php

namespace Tests\Feature\Push;

use App\Jobs\SendPush;
use App\Models\Message;
use App\Models\PushSubscription;
use App\Models\Thread;
use App\Models\User;
use App\Push\PushResult;
use App\Push\WebPushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\FakeFcmSender;
use Tests\Support\FakeWebPushSender;
use Tests\TestCase;

class PushTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'https://fcm.googleapis.com/fcm/send/device-1';

    private User $receiver;

    private User $sender;

    private FakeWebPushSender $push;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.vapid.public_key' => 'test-public-key',
            'services.vapid.private_key' => 'test-private-key',
        ]);

        $this->push = new FakeWebPushSender;
        $this->app->instance(WebPushSender::class, $this->push);

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

    /**
     * @return array{endpoint: string, keys: array{p256dh: string, auth: string}}
     */
    private function subscription(string $endpoint = self::ENDPOINT): array
    {
        return ['endpoint' => $endpoint, 'keys' => ['p256dh' => str_repeat('B', 87), 'auth' => 'tBHItJI5svbpez7KI4CCXg']];
    }

    private function subscribe(User $user, string $endpoint = self::ENDPOINT): void
    {
        $this->as($user)->postJson('/api/push/subscriptions', $this->subscription($endpoint))->assertNoContent();
    }

    private function send(string $body = 'hello'): void
    {
        $this->as($this->sender)->postJson('/api/profiles/alice/messages', ['body' => $body])->assertCreated();
    }

    // ---- configuration ----

    public function test_the_public_key_is_exposed_for_subscribing(): void
    {
        $this->getJson('/api/push/config')->assertOk()->assertExactJson(['data' => ['public_key' => 'test-public-key', 'fcm' => false]]);

        config(['services.vapid.public_key' => null]);
        $this->getJson('/api/push/config')->assertOk()->assertExactJson(['data' => ['public_key' => null, 'fcm' => false]]);
    }

    // ---- registering devices ----

    public function test_registering_requires_sign_in(): void
    {
        $this->postJson('/api/push/subscriptions', $this->subscription())->assertUnauthorized();
    }

    public function test_a_device_is_stored_encrypted_and_registering_again_is_idempotent(): void
    {
        $this->subscribe($this->receiver);
        $this->subscribe($this->receiver);

        $this->assertSame(1, PushSubscription::count());

        $raw = DB::table('push_subscriptions')->first();
        $this->assertStringNotContainsString('fcm.googleapis.com', $raw->token);
        $this->assertStringNotContainsString('tBHItJI5svbpez7KI4CCXg', $raw->keys);
        $this->assertSame(hash('sha256', self::ENDPOINT), $raw->token_hash);
        $this->assertSame(self::ENDPOINT, PushSubscription::firstOrFail()->token);
    }

    public function test_a_device_that_changes_hands_moves_to_the_new_account(): void
    {
        $this->subscribe($this->receiver);
        $this->subscribe($this->sender);

        $this->assertSame(1, PushSubscription::count());
        $this->assertSame($this->sender->id, PushSubscription::firstOrFail()->user_id);
    }

    public function test_each_user_keeps_only_their_most_recent_devices(): void
    {
        foreach (range(1, 12) as $i) {
            $this->subscribe($this->receiver, "https://fcm.googleapis.com/fcm/send/device-$i");
            $this->travel(1)->seconds();
        }

        $this->assertSame(10, PushSubscription::where('user_id', $this->receiver->id)->count());
        $this->assertTrue(PushSubscription::get()->contains(fn ($s) => $s->token === 'https://fcm.googleapis.com/fcm/send/device-12'));
        $this->assertFalse(PushSubscription::get()->contains(fn ($s) => $s->token === 'https://fcm.googleapis.com/fcm/send/device-1'));
    }

    public function test_you_can_only_unregister_your_own_device(): void
    {
        $this->subscribe($this->receiver);

        $this->as($this->sender)->deleteJson('/api/push/subscriptions', ['endpoint' => self::ENDPOINT])->assertNoContent();
        $this->assertSame(1, PushSubscription::count());

        $this->as($this->receiver)->deleteJson('/api/push/subscriptions', ['endpoint' => self::ENDPOINT])->assertNoContent();
        $this->assertSame(0, PushSubscription::count());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function acceptedEndpoints(): array
    {
        return [
            'chrome' => ['https://fcm.googleapis.com/fcm/send/abc'],
            'firefox' => ['https://updates.push.services.mozilla.com/wpush/v2/abc'],
            'safari' => ['https://web.push.apple.com/abc'],
            'edge' => ['https://wns2-par02p.notify.windows.com/w/?token=abc'],
        ];
    }

    #[DataProvider('acceptedEndpoints')]
    public function test_real_push_services_are_accepted(string $endpoint): void
    {
        $this->as($this->receiver)->postJson('/api/push/subscriptions', $this->subscription($endpoint))->assertNoContent();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function rejectedEndpoints(): array
    {
        return [
            'plain http' => ['http://fcm.googleapis.com/fcm/send/abc'],
            'unknown host' => ['https://evil.example.com/push'],
            'lookalike suffix' => ['https://fcm.googleapis.com.evil.com/x'],
            'prefix trick' => ['https://notfcm.googleapis.com.example/x'],
            'loopback ip' => ['https://127.0.0.1/push'],
            'cloud metadata' => ['https://169.254.169.254/latest/meta-data'],
            'credentials' => ['https://user:pw@fcm.googleapis.com/x'],
            'odd port' => ['https://fcm.googleapis.com:8443/x'],
            'not a url' => ['not a url'],
            'ftp' => ['ftp://fcm.googleapis.com/x'],
        ];
    }

    #[DataProvider('rejectedEndpoints')]
    public function test_endpoints_that_could_reach_internal_hosts_are_rejected(string $endpoint): void
    {
        $this->as($this->receiver)->postJson('/api/push/subscriptions', $this->subscription($endpoint))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('endpoint');

        $this->assertSame(0, PushSubscription::count());
    }

    public function test_keys_must_look_like_base64url(): void
    {
        $this->as($this->receiver)->postJson('/api/push/subscriptions', [
            'endpoint' => self::ENDPOINT,
            'keys' => ['p256dh' => 'not base64url!!', 'auth' => ''],
        ])->assertUnprocessable()->assertJsonValidationErrors(['keys.p256dh', 'keys.auth']);
    }

    // ---- sending ----

    public function test_the_receiver_is_notified_without_revealing_the_sender_or_the_text(): void
    {
        $this->subscribe($this->receiver);

        $this->send('you are great');

        $this->assertCount(1, $this->push->sent);
        $sent = $this->push->sent[0];
        $thread = Thread::firstOrFail();

        $this->assertSame('Anonymous #1', $sent['payload']['title']);
        $this->assertSame('You have a new anonymous message', $sent['payload']['body']);
        $this->assertSame("/inbox/{$thread->id}", $sent['payload']['url']);
        $this->assertSame("thread:{$thread->id}", $sent['payload']['tag']);
        $this->assertSame(32, strlen($sent['topic']));

        $json = json_encode($sent['payload']);
        foreach (['you are great', $this->sender->id, 'sneaky_bob', 'Bob Secretname', 'bob.secret@example.com'] as $secret) {
            $this->assertStringNotContainsString($secret, $json, "leaked [$secret]");
        }
    }

    public function test_previews_are_opt_in_and_truncated(): void
    {
        $this->receiver->forceFill(['push_previews' => true])->save();
        $this->subscribe($this->receiver);

        $this->send(str_repeat('word ', 60));

        $body = $this->push->sent[0]['payload']['body'];
        $this->assertStringStartsWith('word word', $body);
        $this->assertLessThanOrEqual(103, mb_strlen($body)); // 100 + ellipsis
    }

    public function test_a_reply_notifies_the_original_sender_with_the_receivers_name(): void
    {
        $this->send();
        $thread = Thread::firstOrFail();
        $this->subscribe($this->sender);

        $this->as($this->receiver)->postJson("/api/threads/{$thread->id}/messages", ['body' => 'hi back'])->assertCreated();

        $this->assertCount(1, $this->push->sent);
        $payload = $this->push->sent[0]['payload'];
        $this->assertSame('Alice Receiver', $payload['title']);
        $this->assertSame('You have a new reply', $payload['body']);
        $this->assertSame("/sent/{$thread->id}", $payload['url']);
    }

    public function test_the_author_is_never_notified_about_their_own_message(): void
    {
        $this->subscribe($this->sender);

        $this->send();

        $this->assertCount(0, $this->push->sent);
    }

    public function test_every_device_of_the_recipient_gets_a_push(): void
    {
        $this->subscribe($this->receiver, 'https://fcm.googleapis.com/fcm/send/phone');
        $this->subscribe($this->receiver, 'https://updates.push.services.mozilla.com/wpush/v2/laptop');

        $this->send();

        $this->assertCount(2, $this->push->sent);
    }

    public function test_nothing_is_sent_without_devices_or_vapid_keys_or_for_banned_recipients(): void
    {
        $this->send(); // no devices yet
        $this->assertCount(0, $this->push->sent);

        $this->subscribe($this->receiver);
        config(['services.vapid.public_key' => null]);
        $this->send('second');
        $this->assertCount(0, $this->push->sent);

        // A banned account gets no pushes either: the sender is banned, then the receiver replies.
        config(['services.vapid.public_key' => 'test-public-key']);
        $thread = Thread::firstOrFail();
        $this->subscribe($this->sender);
        $this->sender->forceFill(['banned_at' => now()])->save();

        $this->as($this->receiver)->postJson("/api/threads/{$thread->id}/messages", ['body' => 'hello?'])->assertCreated();
        $this->assertCount(0, $this->push->sent);
    }

    public function test_jobs_carry_ids_only_so_message_text_never_sits_in_the_queue(): void
    {
        Queue::fake();
        $this->subscribe($this->receiver);

        $this->send('very private text');

        Queue::assertPushed(SendPush::class, function (SendPush $job) {
            $this->assertStringNotContainsString('very private text', serialize($job));

            return $job->messageId === Message::firstOrFail()->id;
        });
    }

    public function test_dead_subscriptions_are_removed_when_the_push_service_says_gone(): void
    {
        $this->subscribe($this->receiver);
        $this->push->result = PushResult::Gone;

        $this->send();

        $this->assertSame(0, PushSubscription::count());
    }

    public function test_temporary_failures_are_retried_and_do_not_delete_the_device(): void
    {
        $this->subscribe($this->receiver);
        $this->send(); // delivered once, so a message exists
        $job = new SendPush(PushSubscription::firstOrFail()->id, Message::firstOrFail()->id);

        $this->push->result = PushResult::Retry;
        $this->expectException(RuntimeException::class);

        try {
            $job->handle($this->push, new FakeFcmSender);
        } finally {
            $this->assertSame(1, PushSubscription::count());
        }
    }

    public function test_permanent_rejections_are_dropped_quietly(): void
    {
        $this->subscribe($this->receiver);
        $this->push->result = PushResult::Failed;

        $this->send();

        $this->assertSame(1, PushSubscription::count());
    }

    // ---- preferences ----

    public function test_previews_can_be_toggled(): void
    {
        $this->as($this->receiver)->patchJson('/api/me/preferences', ['push_previews' => true])
            ->assertOk()
            ->assertJsonPath('data.push_previews', true);

        $this->assertTrue($this->receiver->fresh()->push_previews);

        $this->patchJson('/api/me/preferences', ['push_previews' => 'maybe'])->assertUnprocessable();
    }
}
