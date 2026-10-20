<?php

namespace Tests\Feature\Push;

use App\Models\PushSubscription;
use App\Models\Thread;
use App\Models\User;
use App\Push\FcmSender;
use App\Push\PushResult;
use App\Push\WebPushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FakeFcmSender;
use Tests\Support\FakeWebPushSender;
use Tests\TestCase;

class FcmDevicesTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'dQw4w9WgXcQ:APA91bHun4MxP5egoKMwt2KZFBaFUH-1RYqx_AbCdEf.12345';

    private User $receiver;

    private User $sender;

    private FakeFcmSender $fcm;

    private FakeWebPushSender $web;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.fcm.project_id' => 'psst-test', 'services.fcm.credentials' => '{}']);

        $this->fcm = new FakeFcmSender;
        $this->web = new FakeWebPushSender;
        $this->app->instance(FcmSender::class, $this->fcm);
        $this->app->instance(WebPushSender::class, $this->web);

        $this->receiver = User::factory()->create(['username' => 'alice', 'name' => 'Alice Receiver']);
        $this->sender = User::factory()->create(['username' => 'sneaky_bob', 'name' => 'Bob Secretname', 'email' => 'bob.secret@example.com']);
    }

    private function as(User $user): static
    {
        Sanctum::actingAs($user);

        return $this;
    }

    private function register(User $user, string $token = self::TOKEN, string $platform = 'android'): void
    {
        $this->as($user)->postJson('/api/push/devices', ['token' => $token, 'platform' => $platform])->assertNoContent();
    }

    private function send(string $body = 'hello'): void
    {
        $this->as($this->sender)->postJson('/api/profiles/alice/messages', ['body' => $body])->assertCreated();
    }

    // ---- registering ----

    public function test_registering_requires_sign_in(): void
    {
        $this->postJson('/api/push/devices', ['token' => self::TOKEN, 'platform' => 'ios'])->assertUnauthorized();
    }

    public function test_a_device_is_stored_encrypted_and_registering_again_is_idempotent(): void
    {
        $this->register($this->receiver);
        $this->register($this->receiver, platform: 'ios');

        $this->assertSame(1, PushSubscription::count());
        $row = DB::table('push_subscriptions')->first();
        $this->assertSame('fcm', $row->channel);
        $this->assertStringNotContainsString('APA91b', $row->token, 'token must be encrypted at rest');
        $this->assertSame(hash('sha256', self::TOKEN), $row->token_hash);
        $this->assertSame('capacitor-ios', $row->user_agent);
        $this->assertSame(self::TOKEN, PushSubscription::firstOrFail()->token);
    }

    public function test_a_device_that_changes_hands_moves_to_the_new_account(): void
    {
        $this->register($this->receiver);
        $this->register($this->sender);

        $this->assertSame(1, PushSubscription::count());
        $this->assertSame($this->sender->id, PushSubscription::firstOrFail()->user_id);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function badRegistrations(): array
    {
        return [
            'no token' => [['platform' => 'ios']],
            'empty token' => [['token' => '', 'platform' => 'ios']],
            'spaces in token' => [['token' => 'not a token', 'platform' => 'ios']],
            'script in token' => [['token' => '<script>alert(1)</script>', 'platform' => 'ios']],
            'huge token' => [['token' => str_repeat('a', 4097), 'platform' => 'ios']],
            'unknown platform' => [['token' => self::TOKEN, 'platform' => 'windows']],
            'no platform' => [['token' => self::TOKEN]],
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    #[DataProvider('badRegistrations')]
    public function test_malformed_registrations_are_rejected(array $body): void
    {
        $this->as($this->receiver)->postJson('/api/push/devices', $body)->assertUnprocessable();

        $this->assertSame(0, PushSubscription::count());
    }

    public function test_you_can_only_unregister_your_own_device(): void
    {
        $this->register($this->receiver);

        $this->as($this->sender)->deleteJson('/api/push/devices', ['token' => self::TOKEN])->assertNoContent();
        $this->assertSame(1, PushSubscription::count());

        $this->as($this->receiver)->deleteJson('/api/push/devices', ['token' => self::TOKEN])->assertNoContent();
        $this->assertSame(0, PushSubscription::count());
    }

    public function test_native_and_browser_devices_share_one_cap(): void
    {
        foreach (range(1, 6) as $i) {
            $this->register($this->receiver, "token-$i");
            $this->travel(1)->seconds();
        }
        foreach (range(1, 6) as $i) {
            $this->as($this->receiver)->postJson('/api/push/subscriptions', [
                'endpoint' => "https://fcm.googleapis.com/fcm/send/web-$i",
                'keys' => ['p256dh' => str_repeat('B', 87), 'auth' => 'tBHItJI5svbpez7KI4CCXg'],
            ])->assertNoContent();
            $this->travel(1)->seconds();
        }

        $this->assertSame(10, PushSubscription::where('user_id', $this->receiver->id)->count());
    }

    public function test_the_config_endpoint_says_whether_native_push_is_on(): void
    {
        $this->getJson('/api/push/config')->assertOk()->assertJsonPath('data.fcm', true);

        config(['services.fcm.credentials' => null]);
        $this->getJson('/api/push/config')->assertJsonPath('data.fcm', false);
    }

    // ---- sending ----

    public function test_a_native_device_is_pushed_without_revealing_the_sender_or_the_text(): void
    {
        $this->register($this->receiver);

        $this->send('you are great');

        $this->assertCount(1, $this->fcm->sent);
        $payload = $this->fcm->sent[0]['payload'];
        $this->assertSame('Anonymous #1', $payload['title']);
        $this->assertSame('You have a new anonymous message', $payload['body']);
        $this->assertStringStartsWith('/inbox/', $payload['url']);

        $json = json_encode($payload);
        foreach (['you are great', $this->sender->id, 'sneaky_bob', 'Bob Secretname', 'bob.secret@example.com'] as $secret) {
            $this->assertStringNotContainsString($secret, $json, "leaked [$secret]");
        }
        $this->assertCount(0, $this->web->sent, 'a native device must not be sent through Web Push');
    }

    public function test_a_user_with_both_a_browser_and_a_phone_gets_both(): void
    {
        config(['services.vapid.public_key' => 'test-public-key', 'services.vapid.private_key' => 'k']);
        $this->register($this->receiver);
        $this->as($this->receiver)->postJson('/api/push/subscriptions', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/web',
            'keys' => ['p256dh' => str_repeat('B', 87), 'auth' => 'tBHItJI5svbpez7KI4CCXg'],
        ])->assertNoContent();

        $this->send();

        $this->assertCount(1, $this->fcm->sent);
        $this->assertCount(1, $this->web->sent);
    }

    public function test_native_push_is_off_until_firebase_is_configured(): void
    {
        $this->register($this->receiver);
        config(['services.fcm.project_id' => null]);

        $this->send();

        $this->assertCount(0, $this->fcm->sent);
    }

    public function test_a_reply_reaches_the_senders_phone_with_the_receivers_name(): void
    {
        $this->send();
        $this->register($this->sender, 'sender-phone-token');
        $thread = Thread::firstOrFail();

        $this->as($this->receiver)->postJson("/api/threads/{$thread->id}/messages", ['body' => 'hi back'])->assertCreated();

        $this->assertCount(1, $this->fcm->sent);
        $this->assertSame('Alice Receiver', $this->fcm->sent[0]['payload']['title']);
        $this->assertSame("/sent/{$thread->id}", $this->fcm->sent[0]['payload']['url']);
    }

    public function test_uninstalled_apps_are_removed_when_fcm_says_unregistered(): void
    {
        $this->register($this->receiver);
        $this->fcm->result = PushResult::Gone;

        $this->send();

        $this->assertSame(0, PushSubscription::count());
    }
}
