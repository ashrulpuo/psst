<?php

namespace App\Actions\Messaging;

use App\Messaging\CannotMessage;
use App\Messaging\GuestKey;
use App\Models\Thread;
use App\Models\User;

/**
 * An anonymous message from someone who has not signed in.
 *
 * Blocked guests are dropped silently (the response looks like success) so a blocked person is never told, and
 * cannot probe for it. A block follows the browser token and the IP: a receiver who blocks one guest is not
 * flooded again by the same device or network.
 */
class SendGuestMessage
{
    public function __construct(
        private readonly OpenThread $openThread,
        private readonly PostMessage $postMessage,
    ) {}

    /**
     * @return bool whether the message was delivered (false = silently dropped)
     */
    public function handle(User $receiver, string $guestToken, ?string $ip, string $body): bool
    {
        $keyHash = GuestKey::hashToken($guestToken);
        $ipHash = GuestKey::hashIp($ip);

        if ($this->isBlocked($receiver, $keyHash, $ipHash)) {
            return false;
        }

        $thread = $this->openThread->forGuest($receiver, $keyHash, $ipHash);

        try {
            $this->postMessage->handle($thread, null, $body);
        } catch (CannotMessage) {
            return false;
        }

        return true;
    }

    private function isBlocked(User $receiver, string $keyHash, string $ipHash): bool
    {
        return Thread::query()
            ->where('receiver_id', $receiver->id)
            ->whereNotNull('blocked_at')
            ->where(fn ($q) => $q->where('guest_key_hash', $keyHash)->orWhere('ip_hash', $ipHash))
            ->exists();
    }
}
