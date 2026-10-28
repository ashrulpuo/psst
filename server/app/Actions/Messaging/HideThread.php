<?php

namespace App\Actions\Messaging;

use App\Models\Thread;
use App\Models\ThreadHide;
use App\Models\User;

class HideThread
{
    public function hide(Thread $thread, User $user): void
    {
        ThreadHide::query()->firstOrCreate([
            'thread_id' => $thread->id,
            'user_id' => $user->id,
        ]);
    }

    public function show(Thread $thread, User $user): void
    {
        ThreadHide::query()
            ->where('thread_id', $thread->id)
            ->where('user_id', $user->id)
            ->delete();
    }
}
