<?php

namespace App\Push;

use App\Jobs\SendPush;
use App\Models\Message;
use App\Models\PushSubscription;
use App\Models\Thread;
use App\Models\User;

class PushNotifier
{
    /**
     * Queue a push to every device of the person who did NOT write the message.
     * Jobs carry ids only: message text never lands in the jobs table in the clear.
     */
    public function messagePosted(Thread $thread, Message $message): void
    {
        if (! config('services.vapid.public_key')) {
            return;
        }

        $recipientId = $message->from_receiver ? $thread->sender_id : $thread->receiver_id;
        $recipient = User::query()->select(['id', 'banned_at'])->find($recipientId);

        if (! $recipient || $recipient->isBanned()) {
            return;
        }

        PushSubscription::query()
            ->where('user_id', $recipientId)
            ->where('channel', 'webpush')
            ->pluck('id')
            ->each(fn (string $subscriptionId) => SendPush::dispatch($subscriptionId, $message->id));
    }
}
