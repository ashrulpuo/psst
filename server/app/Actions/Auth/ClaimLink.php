<?php

namespace App\Actions\Auth;

use App\Messaging\GuestKey;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class ClaimLink
{
    public function find(string $token): ?User
    {
        return User::query()->where('link_token_hash', GuestKey::hashToken($token))->first();
    }

    /**
     * Create the link for this browser. The same token always returns the link it already owns.
     */
    public function handle(string $token, string $username): User
    {
        if ($existing = $this->find($token)) {
            return $existing;
        }

        try {
            return User::query()->forceCreate([
                'username' => $username,
                'name' => $username,
                'email' => null,
                'link_only' => true,
                'link_token_hash' => GuestKey::hashToken($token),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'username' => 'That username is taken. Try another.',
            ]);
        }
    }
}
