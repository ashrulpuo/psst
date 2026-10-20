<?php

namespace App\Push;

use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * What goes on the lock screen. It is end-to-end encrypted in transit, but the device displays it in the
 * clear, so message text is opt-in and sender identity is never included.
 */
class PushPayload
{
    private const PREVIEW_LENGTH = 100;

    /**
     * @return array{title: string, body: string, tag: string, url: string, thread_id: string}
     */
    public static function for(Thread $thread, Message $message, User $recipient, string $receiverName): array
    {
        $toReceiver = $recipient->id === $thread->receiver_id;

        $body = $recipient->push_previews
            ? Str::limit($message->body, self::PREVIEW_LENGTH)
            : ($toReceiver ? 'You have a new anonymous message' : 'You have a new reply');

        return [
            'title' => $toReceiver ? 'Anonymous #'.$thread->anon_label : $receiverName,
            'body' => $body,
            // One notification per conversation: a newer message replaces the previous one.
            'tag' => 'thread:'.$thread->id,
            'url' => ($toReceiver ? '/inbox/' : '/sent/').$thread->id,
            'thread_id' => $thread->id,
        ];
    }

    /** Push-service topics are limited to 32 URL-safe characters. */
    public static function topic(Thread $thread): string
    {
        return md5($thread->id);
    }
}
