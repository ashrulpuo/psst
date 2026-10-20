<?php

namespace App\Jobs;

use App\Models\Message;
use App\Models\PushSubscription;
use App\Models\Thread;
use App\Models\User;
use App\Push\FcmSender;
use App\Push\PushPayload;
use App\Push\PushResult;
use App\Push\WebPushSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Deliver one push to one device. One job per subscription so a dead endpoint never delays the others.
 */
class SendPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 4;

    public function __construct(
        public readonly string $subscriptionId,
        public readonly string $messageId,
    ) {
        // Only queue once the surrounding transaction (the message insert) has committed.
        $this->afterCommit();
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [15, 60, 300];
    }

    public function handle(WebPushSender $webPush, FcmSender $fcm): void
    {
        $subscription = PushSubscription::query()->find($this->subscriptionId);
        $message = Message::query()->find($this->messageId);

        if (! $subscription || ! $message) {
            return; // unsubscribed or deleted in the meantime
        }

        $thread = Thread::query()->findOrFail($message->thread_id);
        $recipient = User::query()->findOrFail($subscription->user_id);
        $receiverName = $recipient->id === $thread->receiver_id
            ? $recipient->name
            : (string) User::query()->whereKey($thread->receiver_id)->value('name');

        // One job per device; the device's channel decides which push service carries it.
        $sender = $subscription->channel === 'fcm' ? $fcm : $webPush;

        $result = $sender->send(
            $subscription,
            PushPayload::for($thread, $message, $recipient, $receiverName),
            PushPayload::topic($thread),
        );

        match ($result) {
            PushResult::Delivered => null,
            PushResult::Gone => $subscription->delete(),
            PushResult::Retry => throw new RuntimeException('Push service unavailable; retrying.'),
            PushResult::Failed => Log::warning('push.rejected', ['subscription' => $subscription->id]),
        };
    }

    public function failed(Throwable $e): void
    {
        Log::warning('push.gave_up', ['subscription' => $this->subscriptionId, 'error' => $e->getMessage()]);
    }
}
