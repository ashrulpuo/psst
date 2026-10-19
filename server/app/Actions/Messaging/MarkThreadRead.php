<?php

namespace App\Actions\Messaging;

use App\Models\Thread;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MarkThreadRead
{
    public function handle(Thread $thread, User $reader): void
    {
        $readerIsReceiver = $thread->receiver_id === $reader->id;

        DB::transaction(function () use ($thread, $readerIsReceiver) {
            // Mark the other party's messages as read.
            $thread->messages()
                ->where('from_receiver', ! $readerIsReceiver)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            $thread->newQuery()->whereKey($thread->id)->update([
                $readerIsReceiver ? 'receiver_unread_count' : 'sender_unread_count' => 0,
            ]);
        });
    }
}
