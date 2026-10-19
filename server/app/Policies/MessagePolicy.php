<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\Thread;
use App\Models\User;

class MessagePolicy
{
    /**
     * You can only report what the other person wrote, never your own messages.
     */
    public function report(User $user, Message $message): bool
    {
        $userIsReceiver = Thread::query()->whereKey($message->thread_id)->where('receiver_id', $user->id)->exists();

        return $message->from_receiver !== $userIsReceiver;
    }
}
