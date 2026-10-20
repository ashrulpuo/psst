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
        $channels = $this->enabledChannels();

        if ($channels === []) {
            return;
        }

        $recipientId = $message->from_receiver ? $thread->sender_id : $thread->receiver_id;
        $recipient = User::query()->select(['id', 'banned_at'])->find($recipientId);

        if (! $recipient || $recipient->isBanned()) {
            return;
        }

        PushSubscription::query()
            ->where('user_id', $recipientId)
            ->whereIn('channel', $channels)
            ->pluck('id')
            ->each(fn (string $subscriptionId) => SendPush::dispatch($subscriptionId, $message->id));
    }

    /**
     * Channels that are actually configured on this server.
     *
     * @return list<string>
     */
    private function enabledChannels(): array
    {
        return array_values(array_filter([
            config('services.vapid.public_key') ? 'webpush' : null,
            config('services.fcm.project_id') && config('services.fcm.credentials') ? 'fcm' : null,
        ]));
    }
}
