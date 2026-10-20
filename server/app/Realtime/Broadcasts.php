<?php

namespace App\Realtime;

use App\Events\MessagePosted;
use App\Events\MessagesRead;
use App\Events\ThreadBlockChanged;
use App\Models\Message;
use App\Models\Thread;
use App\Models\User;

/**
 * Builds and dispatches the realtime events for a thread, one payload per participant.
 *
 * Shapes mirror the REST resources (see client/src/api/types.ts) from that participant's point of view:
 * the receiver sees "Anonymous #N", the sender sees the receiver's public profile. Sender identity is
 * never put into any payload addressed to the receiver.
 */
class Broadcasts
{
    public function messagePosted(Thread $thread, Message $message): void
    {
        $receiver = User::query()->select(['id', 'username', 'name', 'avatar_url'])->findOrFail($thread->receiver_id);

        foreach ([true, false] as $toReceiver) {
            $authoredByRecipient = $message->from_receiver === $toReceiver;

            MessagePosted::dispatch(
                $toReceiver ? $thread->receiver_id : $thread->sender_id,
                [
                    'box' => $toReceiver ? 'inbox' : 'sent',
                    'thread' => $toReceiver
                        ? [
                            'id' => $thread->id,
                            'label' => 'Anonymous #'.$thread->anon_label,
                            'unread_count' => $thread->receiver_unread_count,
                            'blocked' => $thread->blocked_at !== null,
                            'last_message_at' => $message->created_at,
                        ]
                        : [
                            'id' => $thread->id,
                            'receiver' => [
                                'username' => $receiver->username,
                                'name' => $receiver->name,
                                'avatar_url' => $receiver->avatar_url,
                            ],
                            'unread_count' => $thread->sender_unread_count,
                            'can_reply' => $thread->blocked_at === null,
                            'last_message_at' => $message->created_at,
                        ],
                    'message' => [
                        'id' => $message->id,
                        'body' => $message->body,
                        'mine' => $authoredByRecipient,
                        'read_at' => null,
                        'created_at' => $message->created_at,
                    ],
                ],
            );
        }
    }

    public function blockChanged(Thread $thread, bool $blocked): void
    {
        ThreadBlockChanged::dispatch($thread->receiver_id, ['thread_id' => $thread->id, 'blocked' => $blocked]);
        ThreadBlockChanged::dispatch($thread->sender_id, ['thread_id' => $thread->id, 'can_reply' => ! $blocked]);
    }

    /**
     * Tell the *other* participant their messages were read.
     */
    public function messagesRead(Thread $thread, User $reader): void
    {
        $readerIsReceiver = $thread->receiver_id === $reader->id;

        MessagesRead::dispatch(
            $readerIsReceiver ? $thread->sender_id : $thread->receiver_id,
            ['thread_id' => $thread->id, 'read_at' => now()->toIso8601String()],
        );
    }
}
