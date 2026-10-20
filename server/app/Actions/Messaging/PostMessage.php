<?php

namespace App\Actions\Messaging;

use App\Messaging\CannotMessage;
use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use App\Push\PushNotifier;
use App\Realtime\Broadcasts;
use Illuminate\Support\Facades\DB;

class PostMessage
{
    public function __construct(
        private readonly Broadcasts $broadcasts,
        private readonly PushNotifier $push,
    ) {}

    /**
     * Append a message from a participant and update the thread's counters in the same transaction.
     *
     * @throws CannotMessage
     */
    public function handle(Thread $thread, User $author, string $body): Message
    {
        $fromReceiver = $thread->receiver_id === $author->id;

        return DB::transaction(function () use ($thread, $fromReceiver, $body) {
            // Re-read under lock so block state and the unanswered count cannot change mid-check.
            $thread = Thread::query()->lockForUpdate()->findOrFail($thread->id);

            if ($thread->isBlocked()) {
                throw CannotMessage::blocked();
            }

            if (! $fromReceiver) {
                $this->assertSenderNotSpamming($thread);
            }

            $message = $thread->messages()->create([
                'from_receiver' => $fromReceiver,
                'body' => $body,
            ]);

            $unreadColumn = $fromReceiver ? 'sender_unread_count' : 'receiver_unread_count';
            $thread->increment($unreadColumn, 1, [
                'last_message_at' => $message->created_at,
                'last_message_id' => $message->id,
            ]);

            // Queued and sent only after this transaction commits (events set $afterCommit).
            $this->broadcasts->messagePosted($thread, $message);
            $this->push->messagePosted($thread, $message);

            return $message;
        });
    }

    private function assertSenderNotSpamming(Thread $thread): void
    {
        $limit = (int) config('psst.messages.max_unanswered');

        $recent = $thread->messages()->latest('id')->limit($limit)->pluck('from_receiver');

        // The last N messages are all from the sender: nobody has replied.
        if ($recent->count() === $limit && $recent->every(fn ($fromReceiver) => $fromReceiver === false)) {
            throw CannotMessage::waitForReply($limit);
        }
    }
}
