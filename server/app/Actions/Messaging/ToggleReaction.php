<?php

namespace App\Actions\Messaging;

use App\Models\Message;
use App\Models\User;

class ToggleReaction
{
    /** Same person, same emoji, same message: the second tap takes it back. */
    public function handle(Message $message, User $user, string $emoji): void
    {
        $existing = $message->reactions()
            ->where('user_id', $user->id)
            ->where('emoji', $emoji)
            ->first();

        if ($existing) {
            $existing->delete();

            return;
        }

        $message->reactions()->create([
            'user_id' => $user->id,
            'emoji' => $emoji,
        ]);
    }
}
