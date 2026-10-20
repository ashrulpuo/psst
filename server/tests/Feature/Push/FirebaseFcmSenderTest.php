<?php

namespace Tests\Feature\Push;

use App\Models\PushSubscription;
use App\Models\User;
use App\Push\FirebaseFcmSender;
use App\Push\PushResult;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The real sender against a faked Google: OAuth (service-account JWT) and the FCM v1 endpoint.
 */
class FirebaseFcmSenderTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const SEND_URL = 'https://fcm.googleapis.com/v1/projects/psst-test/messages:send';

    private string $publicKey;

    private PushSubscription $device;

    /** @var array<string, mixed> */
    private array $payload = [
        'title' => 'Anonymous #1',
        'body' => 'You have a new anonymous message',
        'tag' => 'thread:abc',
        'url' => '/inbox/abc',
        'thread_id' => 'abc',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privatePem);
        $this->publicKey = openssl_pkey_get_details($key)['key'];

        config([
            'services.fcm.project_id' => 'psst-test',
            'services.fcm.credentials' => json_encode([
                'client_email' => 'psst@psst-test.iam.gserviceaccount.com',
                'private_key' => $privatePem,
                'token_uri' => self::TOKEN_URL,
            ]),
        ]);

        $user = User::factory()->create();
        $this->device = PushSubscription::create([
            'user_id' => $user->id, 'channel' => 'fcm', 'token' => 'device-token-123',
            'token_hash' => hash('sha256', 'device-token-123'),
        ]);
    }

    private function sender(): FirebaseFcmSender
    {
        return app(FirebaseFcmSender::class);
    }

    private function fakeGoogle(int $status = 200, array $body = ['name' => 'projects/psst-test/messages/1']): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'ya29.fake-access-token', 'expires_in' => 3600]),
            self::SEND_URL => Http::response($body, $status),
        ]);
    }

    public function test_it_authenticates_with_a_signed_service_account_jwt_and_sends_the_message(): void
    {
        $this->fakeGoogle();

        $result = $this->sender()->send($this->device, $this->payload, 'topic');

        $this->assertSame(PushResult::Delivered, $result);

        // OAuth: a JWT signed with the service account's private key, scoped to FCM.
        Http::assertSent(function (Request $request) {
            if ($request->url() !== self::TOKEN_URL) {
                return false;
            }
            $data = $request->data();
            $claims = JWT::decode($data['assertion'], new Key($this->publicKey, 'RS256')); // throws on a bad signature

            return $data['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
                && $claims->iss === 'psst@psst-test.iam.gserviceaccount.com'
                && $claims->scope === 'https://www.googleapis.com/auth/firebase.messaging'
                && $claims->aud === self::TOKEN_URL
                && $claims->exp - $claims->iat === 3600;
        });

        // FCM v1: bearer token, and a message with string-only data, collapse keys and the device token.
        Http::assertSent(function (Request $request) {
            if ($request->url() !== self::SEND_URL) {
                return false;
            }
            $message = $request->data()['message'];

            return $request->hasHeader('Authorization', 'Bearer ya29.fake-access-token')
                && $message['token'] === 'device-token-123'
                && $message['notification'] === ['title' => 'Anonymous #1', 'body' => 'You have a new anonymous message']
                && $message['data'] === ['url' => '/inbox/abc', 'thread_id' => 'abc', 'tag' => 'thread:abc']
                && $message['android']['notification']['tag'] === 'thread:abc'
                && $message['android']['priority'] === 'HIGH'
                && $message['apns']['headers']['apns-collapse-id'] === 'thread:abc'
                && $message['apns']['payload']['aps']['thread-id'] === 'abc';
        });
    }

    public function test_the_oauth_token_is_cached_between_sends(): void
    {
        $this->fakeGoogle();

        $this->sender()->send($this->device, $this->payload, 't');
        $this->sender()->send($this->device, $this->payload, 't');

        Http::assertSentCount(3); // one OAuth exchange, two sends
    }

    /**
     * @return array<string, array{0: int, 1: array<string, mixed>, 2: PushResult}>
     */
    public static function fcmResponses(): array
    {
        return [
            'accepted' => [200, ['name' => 'x'], PushResult::Delivered],
            'not found' => [404, ['error' => ['status' => 'NOT_FOUND']], PushResult::Gone],
            'unregistered' => [400, ['error' => ['status' => 'INVALID_ARGUMENT', 'details' => [['errorCode' => 'UNREGISTERED']]]], PushResult::Gone],
            'rate limited' => [429, ['error' => ['status' => 'RESOURCE_EXHAUSTED']], PushResult::Retry],
            'server error' => [503, ['error' => ['status' => 'UNAVAILABLE']], PushResult::Retry],
            'bad request' => [400, ['error' => ['status' => 'INVALID_ARGUMENT', 'message' => 'bad payload']], PushResult::Failed],
            'forbidden' => [403, ['error' => ['status' => 'PERMISSION_DENIED']], PushResult::Failed],
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    #[DataProvider('fcmResponses')]
    public function test_fcm_responses_map_to_the_right_outcome(int $status, array $body, PushResult $expected): void
    {
        $this->fakeGoogle($status, $body);

        $this->assertSame($expected, $this->sender()->send($this->device, $this->payload, 't'));
    }

    public function test_an_expired_oauth_token_is_dropped_and_retried_with_a_fresh_one(): void
    {
        $this->fakeGoogle(401, ['error' => ['status' => 'UNAUTHENTICATED']]);

        $this->assertSame(PushResult::Retry, $this->sender()->send($this->device, $this->payload, 't'));
        $this->assertNull(Cache::get('fcm.access_token'), 'the stale token must be forgotten');
    }

    public function test_bad_credentials_fail_without_contacting_fcm(): void
    {
        config(['services.fcm.credentials' => '{"client_email":"x"}']);
        Http::fake();

        $this->assertSame(PushResult::Failed, $this->sender()->send($this->device, $this->payload, 't'));
        Http::assertNothingSent();
    }

    public function test_google_oauth_being_down_is_a_retry_and_a_rejected_account_is_a_failure(): void
    {
        // Stubs registered first win, so script both answers as a sequence.
        Http::fake([self::TOKEN_URL => Http::sequence()->push('', 503)->push(['error' => 'invalid_grant'], 400)]);

        $this->assertSame(PushResult::Retry, $this->sender()->send($this->device, $this->payload, 't'));

        Cache::flush();
        $this->assertSame(PushResult::Failed, $this->sender()->send($this->device, $this->payload, 't'));
    }

    public function test_credentials_can_be_given_as_base64_or_a_file_path(): void
    {
        $json = config('services.fcm.credentials');
        $path = tempnam(sys_get_temp_dir(), 'fcm');
        file_put_contents($path, $json);

        foreach ([base64_encode($json), $path] as $form) {
            Cache::flush();
            config(['services.fcm.credentials' => $form]);
            $this->fakeGoogle();

            $this->assertSame(PushResult::Delivered, $this->sender()->send($this->device, $this->payload, 't'), 'form: '.substr($form, 0, 12));
        }

        unlink($path);
    }
}
