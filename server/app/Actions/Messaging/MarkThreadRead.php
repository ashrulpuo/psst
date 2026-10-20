<?php

namespace App\Actions\Messaging;

use App\Models\Thread;
use App\Models\User;
use App\Realtime\Broadcasts;
use Illuminate\Support\Facades\DB;

class MarkThreadRead
{
    public function __construct(private readonly Broadcasts $broadcasts) {}

    public function handle(Thread $thread, User $reader): void
    {
        $readerIsReceiver = $thread->receiver_id === $reader->id;

        DB::transaction(function () use ($thread, $reader, $readerIsReceiver) {
            // Mark the other party's messages as read.
            $marked = $thread->messages()
                ->where('from_receiver', ! $readerIsReceiver)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            // Only tell the other side when something actually changed for them.
            if ($marked > 0) {
                $this->broadcasts->messagesRead($thread, $reader);
            }

            $thread->newQuery()->whereKey($thread->id)->update([
                $readerIsReceiver ? 'receiver_unread_count' : 'sender_unread_count' => 0,
            ]);
        });
    }
}
