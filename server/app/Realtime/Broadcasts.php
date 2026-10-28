<?php

namespace App\Realtime;

use App\Events\MessagePosted;
use App\Events\MessagesRead;
use App\Events\ReactionChanged;
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
            // A guest has no account to push to.
            if (! $toReceiver && $thread->sender_id === null) {
                continue;
            }

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
                            'can_chat' => $thread->sender_id !== null,
                            'last_message_at' => $message->created_at,
                        ]
                        : [
                            'id' => $thread->id,
                            'label' => 'Anonymous #'.$thread->anon_label,
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
                        'reactions' => [],
                    ],
                ],
            );
        }
    }

    /**
     * The same tally, but each participant's `mine` flag is their own.
     * A guest has no channel to push to, so only the receiver hears about it.
     */
    public function reactionChanged(Thread $thread, Message $message): void
    {
        $summary = $message->reactions()->get()
            ->groupBy('emoji')
            ->map(fn ($group, $emoji) => [
                'emoji' => $emoji,
                'count' => $group->count(),
                'user_ids' => $group->pluck('user_id')->all(),
            ]);

        foreach ([true, false] as $toReceiver) {
            $viewerId = $toReceiver ? $thread->receiver_id : $thread->sender_id;

            if ($viewerId === null) {
                continue;
            }

            ReactionChanged::dispatch($viewerId, [
                'thread_id' => $thread->id,
                'message_id' => $message->id,
                'reactions' => $summary->map(fn ($row) => [
                    'emoji' => $row['emoji'],
                    'count' => $row['count'],
                    'mine' => in_array($viewerId, $row['user_ids'], true),
                ])->values()->all(),
            ]);
        }
    }

    public function blockChanged(Thread $thread, bool $blocked): void
    {
        ThreadBlockChanged::dispatch($thread->receiver_id, ['thread_id' => $thread->id, 'blocked' => $blocked]);
        if ($thread->sender_id !== null) {
            ThreadBlockChanged::dispatch($thread->sender_id, ['thread_id' => $thread->id, 'can_reply' => ! $blocked]);
        }
    }

    /** A guest signed in: the receiver's open inbox learns the thread can now be replied to. */
    public function threadClaimed(Thread $thread): void
    {
        ThreadBlockChanged::dispatch($thread->receiver_id, ['thread_id' => $thread->id, 'can_chat' => true]);
    }

    /**
     * Tell the *other* participant their messages were read.
     */
    public function messagesRead(Thread $thread, User $reader): void
    {
        $readerIsReceiver = $thread->receiver_id === $reader->id;
        $other = $readerIsReceiver ? $thread->sender_id : $thread->receiver_id;

        if ($other === null) {
            return; // the other side is a guest
        }

        MessagesRead::dispatch(
            $other,
            ['thread_id' => $thread->id, 'read_at' => now()->toIso8601String()],
        );
    }
}
