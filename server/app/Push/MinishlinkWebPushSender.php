<?php

namespace App\Push;

use App\Models\PushSubscription;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class MinishlinkWebPushSender implements WebPushSender
{
    public function send(PushSubscription $subscription, array $payload, string $topic): PushResult
    {
        $keys = $subscription->keys ?? [];

        try {
            $webPush = new WebPush(
                ['VAPID' => [
                    'subject' => config('services.vapid.subject'),
                    'publicKey' => config('services.vapid.public_key'),
                    'privateKey' => config('services.vapid.private_key'),
                ]],
                ['TTL' => config('psst.push.ttl_seconds'), 'urgency' => 'normal', 'topic' => $topic],
                // Never follow redirects: that would let an allow-listed host bounce us to an internal address.
                new Client(['timeout' => 10, 'connect_timeout' => 5, 'allow_redirects' => false]),
            );

            $report = $webPush->sendOneNotification(
                Subscription::create([
                    'endpoint' => $subscription->token,
                    'publicKey' => $keys['p256dh'] ?? '',
                    'authToken' => $keys['auth'] ?? '',
                    'contentEncoding' => 'aes128gcm',
                ]),
                json_encode($payload, JSON_THROW_ON_ERROR),
            );
        } catch (Throwable $e) {
            // Bad keys, misconfigured VAPID, malformed endpoint: retrying will not help.
            Log::warning('push.send_error', [
                'subscription' => $subscription->id,
                'error' => $e::class,
                'message' => mb_substr($e->getMessage(), 0, 200),
            ]);

            return PushResult::Failed;
        }

        if ($report->isSuccess()) {
            return PushResult::Delivered;
        }

        if ($report->isSubscriptionExpired()) {
            return PushResult::Gone;
        }

        $status = $report->getResponse()?->getStatusCode();

        return $status === null || $status === 429 || $status >= 500 ? PushResult::Retry : PushResult::Failed;
    }
}
