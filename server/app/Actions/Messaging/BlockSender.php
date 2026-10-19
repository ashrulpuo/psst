<?php

namespace App\Actions\Messaging;

use App\Models\Block;
use App\Models\Thread;
use Illuminate\Support\Facades\DB;

class BlockSender
{
    public function block(Thread $thread): void
    {
        DB::transaction(function () use ($thread) {
            Block::query()->insertOrIgnore([
                'receiver_id' => $thread->receiver_id,
                'sender_id' => $thread->sender_id,
                'created_at' => now(),
            ]);

            $thread->newQuery()->whereKey($thread->id)->whereNull('blocked_at')->update(['blocked_at' => now()]);
        });
    }

    public function unblock(Thread $thread): void
    {
        DB::transaction(function () use ($thread) {
            Block::query()
                ->where('receiver_id', $thread->receiver_id)
                ->where('sender_id', $thread->sender_id)
                ->delete();

            $thread->newQuery()->whereKey($thread->id)->update(['blocked_at' => null]);
        });
    }
}
