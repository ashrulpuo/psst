<?php

namespace App\Actions\Auth;

use App\Auth\AccountBanned;
use App\Auth\VerifiedIdentity;
use App\Models\Identity;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class SignInWithIdentity
{
    /**
     * Resolve (or create) the user for a verified provider identity.
     *
     * Order: known provider identity, then a user with the same verified email (link), then a new user.
     *
     * @throws AccountBanned
     */
    public function handle(VerifiedIdentity $identity): User
    {
        try {
            $user = DB::transaction(fn () => $this->resolve($identity));
        } catch (UniqueConstraintViolationException) {
            // Two first sign-ins raced; the winner's rows exist now.
            $user = DB::transaction(fn () => $this->resolve($identity));
        }

        if ($user->isBanned()) {
            throw new AccountBanned;
        }

        $user->forceFill([
            'last_login_at' => now(),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        return $user;
    }

    private function resolve(VerifiedIdentity $identity): User
    {
        $existing = Identity::query()
            ->where('provider', $identity->provider)
            ->where('provider_user_id', $identity->subject)
            ->first();

        if ($existing) {
            return $existing->user;
        }

        $user = User::query()->where('email', $identity->email)->first()
            ?? User::create([
                'name' => $identity->name,
                'email' => $identity->email,
                'avatar_url' => $identity->avatarUrl,
            ]);

        $user->identities()->create([
            'provider' => $identity->provider,
            'provider_user_id' => $identity->subject,
        ]);

        return $user;
    }
}
