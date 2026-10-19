<?php

namespace App\Actions\Messaging;

use App\Messaging\CannotMessage;
use App\Models\Block;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class OpenThread
{
    /**
     * Get the thread between a sender and a receiver, creating it (with the next "Anonymous #N") on first contact.
     *
     * @throws CannotMessage
     */
    public function handle(User $sender, User $receiver): Thread
    {
        if ($sender->is($receiver)) {
            throw CannotMessage::yourself();
        }

        if (Block::query()->where('receiver_id', $receiver->id)->where('sender_id', $sender->id)->exists()) {
            throw CannotMessage::blocked();
        }

        try {
            return DB::transaction(fn () => $this->firstOrCreate($sender, $receiver));
        } catch (UniqueConstraintViolationException) {
            // A concurrent first message created it; read the winner's thread.
            return Thread::query()->where('receiver_id', $receiver->id)->where('sender_id', $sender->id)->firstOrFail();
        }
    }

    private function firstOrCreate(User $sender, User $receiver): Thread
    {
        $existing = Thread::query()->where('receiver_id', $receiver->id)->where('sender_id', $sender->id)->first();

        if ($existing) {
            return $existing;
        }

        // Serialize label allocation per receiver so two new senders never get the same number.
        User::query()->whereKey($receiver->id)->lockForUpdate()->first();

        $label = (int) Thread::query()->where('receiver_id', $receiver->id)->max('anon_label') + 1;

        return Thread::create([
            'receiver_id' => $receiver->id,
            'sender_id' => $sender->id,
            'anon_label' => $label,
        ]);
    }
}
