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

    /**
     * The thread for an unsigned sender, keyed by their browser token. Created on first contact.
     */
    public function forGuest(User $receiver, string $guestKeyHash, string $ipHash): Thread
    {
        $find = fn () => Thread::query()->where('receiver_id', $receiver->id)->where('guest_key_hash', $guestKeyHash)->first();

        try {
            return DB::transaction(fn () => $find() ?? $this->create($receiver, [
                'guest_key_hash' => $guestKeyHash,
                'ip_hash' => $ipHash,
            ]));
        } catch (UniqueConstraintViolationException) {
            return $find() ?? throw new \LogicException('Guest thread vanished after a unique violation.');
        }
    }

    private function firstOrCreate(User $sender, User $receiver): Thread
    {
        $existing = Thread::query()->where('receiver_id', $receiver->id)->where('sender_id', $sender->id)->first();

        return $existing ?? $this->create($receiver, ['sender_id' => $sender->id]);
    }

    /**
     * @param  array<string, mixed>  $sender  sender_id, or guest_key_hash + ip_hash
     */
    private function create(User $receiver, array $sender): Thread
    {
        // Serialize label allocation per receiver so two new senders never get the same number.
        User::query()->whereKey($receiver->id)->lockForUpdate()->first();

        $label = (int) Thread::query()->where('receiver_id', $receiver->id)->max('anon_label') + 1;

        return Thread::create([...$sender, 'receiver_id' => $receiver->id, 'anon_label' => $label]);
    }
}
