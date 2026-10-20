<?php

namespace App\Push;

use App\Models\PushSubscription;
use Firebase\JWT\JWT;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Sends to iOS/Android devices through FCM's HTTP v1 API, authenticating as a Google service account
 * (a signed JWT exchanged for a short-lived OAuth token, cached).
 */
class FirebaseFcmSender implements FcmSender
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const TOKEN_CACHE_KEY = 'fcm.access_token';

    public function __construct(
        private readonly Http $http,
        private readonly Cache $cache,
    ) {}

    public function send(PushSubscription $subscription, array $payload, string $topic): PushResult
    {
        $project = (string) config('services.fcm.project_id');

        try {
            $response = $this->http
                ->withToken($this->accessToken())
                ->timeout(10)
                ->post("https://fcm.googleapis.com/v1/projects/$project/messages:send", [
                    'message' => $this->message($subscription->token, $payload),
                ]);
        } catch (ConnectionException) {
            return PushResult::Retry;
        } catch (Throwable $e) {
            // Missing or malformed service-account credentials: retrying cannot help.
            Log::warning('fcm.auth_error', ['error' => $e::class, 'message' => mb_substr($e->getMessage(), 0, 200)]);

            return PushResult::Failed;
        }

        return $this->interpret($response->status(), $response->json());
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function message(string $deviceToken, array $payload): array
    {
        return [
            'token' => $deviceToken,
            'notification' => ['title' => $payload['title'], 'body' => $payload['body']],
            // FCM data values must all be strings. The app routes a tap using `url`.
            'data' => [
                'url' => (string) $payload['url'],
                'thread_id' => (string) $payload['thread_id'],
                'tag' => (string) $payload['tag'],
            ],
            'android' => [
                'priority' => 'HIGH',
                // One notification per conversation: a newer message replaces the older one.
                'notification' => ['tag' => $payload['tag'], 'channel_id' => 'messages'],
            ],
            'apns' => [
                'headers' => ['apns-collapse-id' => $payload['tag']],
                'payload' => ['aps' => ['thread-id' => $payload['thread_id'], 'sound' => 'default']],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $body
     */
    private function interpret(int $status, ?array $body): PushResult
    {
        if ($status >= 200 && $status < 300) {
            return PushResult::Delivered;
        }

        $errorStatus = $body['error']['status'] ?? null;
        $codes = collect($body['error']['details'] ?? [])->pluck('errorCode')->filter()->all();

        // The device uninstalled the app or rotated its token: stop sending to it.
        if ($status === 404 || $errorStatus === 'NOT_FOUND' || in_array('UNREGISTERED', $codes, true)) {
            return PushResult::Gone;
        }

        if ($status === 401) {
            $this->cache->forget(self::TOKEN_CACHE_KEY); // our OAuth token expired early: fetch a fresh one on retry

            return PushResult::Retry;
        }

        if ($status === 429 || $status >= 500) {
            return PushResult::Retry;
        }

        Log::warning('fcm.rejected', ['status' => $status, 'error' => $errorStatus, 'message' => mb_substr((string) ($body['error']['message'] ?? ''), 0, 200)]);

        return PushResult::Failed;
    }

    private function accessToken(): string
    {
        return $this->cache->remember(self::TOKEN_CACHE_KEY, 3300, function (): string {
            $account = $this->serviceAccount();
            $now = time();

            $assertion = JWT::encode([
                'iss' => $account['client_email'],
                'scope' => self::SCOPE,
                'aud' => $account['token_uri'] ?? 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ], $account['private_key'], 'RS256');

            $response = $this->http->asForm()->timeout(10)->post($account['token_uri'] ?? 'https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

            if ($response->serverError()) {
                throw new ConnectionException('Google OAuth is unavailable.');
            }
            if (! $response->successful() || ! is_string($response->json('access_token'))) {
                throw new RuntimeException('Google rejected the service account ('.$response->status().').');
            }

            return $response->json('access_token');
        });
    }

    /**
     * @return array{client_email: string, private_key: string, token_uri?: string}
     */
    private function serviceAccount(): array
    {
        $raw = trim((string) config('services.fcm.credentials'));

        if (! str_starts_with($raw, '{')) {
            $raw = is_file($raw) ? (string) file_get_contents($raw) : (string) base64_decode($raw, true);
        }

        $account = json_decode($raw, true);

        if (! is_array($account) || empty($account['client_email']) || empty($account['private_key'])) {
            throw new RuntimeException('FCM_CREDENTIALS is not a service-account key.');
        }

        return $account;
    }
}
