<?php

namespace App\Actions\Messaging;

use App\Messaging\GuestKey;
use App\Models\Block;
use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use App\Realtime\Broadcasts;
use Illuminate\Support\Facades\DB;

/**
 * Signing in turns a guest's anonymous threads into the user's own, which is what lets the receiver reply.
 * Proof of ownership is possession of the secret token that created the thread.
 */
class ClaimGuestThreads
{
    public function __construct(private readonly Broadcasts $broadcasts) {}

    /**
     * @return int number of threads claimed
     */
    public function handle(User $user, string $guestToken): int
    {
        $keyHash = GuestKey::hashToken($guestToken);

        return DB::transaction(function () use ($user, $keyHash) {
            $claimed = 0;

            $threads = Thread::query()
                ->where('guest_key_hash', $keyHash)
                ->where('receiver_id', '<>', $user->id) // never your own link
                ->lockForUpdate()
                ->get();

            foreach ($threads as $guest) {
                $existing = Thread::query()
                    ->where('receiver_id', $guest->receiver_id)
                    ->where('sender_id', $user->id)
                    ->first();

                $existing ? $this->mergeInto($existing, $guest) : $this->adopt($guest, $user);
                $claimed++;
            }

            return $claimed;
        });
    }

    private function adopt(Thread $guest, User $user): void
    {
        $guest->forceFill(['sender_id' => $user->id, 'guest_key_hash' => null, 'ip_hash' => null])->save();

        if ($guest->isBlocked()) {
            $this->recordBlock($guest, $user);
        }

        $this->broadcasts->threadClaimed($guest);
    }

    /** The user already had a conversation with this receiver: fold the guest messages into it. */
    private function mergeInto(Thread $existing, Thread $guest): void
    {
        Message::query()->where('thread_id', $guest->id)->update(['thread_id' => $existing->id]);

        $latest = Message::query()->where('thread_id', $existing->id)->orderByDesc('id')->first();

        $existing->forceFill([
            'receiver_unread_count' => $existing->receiver_unread_count + $guest->receiver_unread_count,
            'sender_unread_count' => $existing->sender_unread_count + $guest->sender_unread_count,
            'last_message_id' => $latest?->id,
            'last_message_at' => $latest?->created_at,
            'blocked_at' => $existing->blocked_at ?? $guest->blocked_at,
        ])->save();

        if ($existing->isBlocked()) {
            $this->recordBlock($existing, User::query()->findOrFail($existing->sender_id));
        }

        $guest->delete();
    }

    private function recordBlock(Thread $thread, User $user): void
    {
        Block::query()->insertOrIgnore([
            'receiver_id' => $thread->receiver_id,
            'sender_id' => $user->id,
            'created_at' => now(),
        ]);
    }
}
