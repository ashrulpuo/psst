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
    public function handle(VerifiedIdentity $identity, ?User $current = null): User
    {
        try {
            $user = DB::transaction(fn () => $this->resolve($identity, $current));
        } catch (UniqueConstraintViolationException) {
            // Two first sign-ins raced; the winner's rows exist now.
            $user = DB::transaction(fn () => $this->resolve($identity, $current));
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

    private function resolve(VerifiedIdentity $identity, ?User $current): User
    {
        $existing = Identity::query()
            ->where('provider', $identity->provider)
            ->where('provider_user_id', $identity->subject)
            ->first();

        if ($existing) {
            return $existing->user;
        }

        $byEmail = User::query()->where('email', $identity->email)->first();

        if ($byEmail) {
            $byEmail->identities()->create([
                'provider' => $identity->provider,
                'provider_user_id' => $identity->subject,
            ]);

            return $byEmail;
        }

        // This browser already picked a username. The first real sign-in keeps that link.
        if ($current?->link_only) {
            $current->forceFill([
                'name' => $identity->name ?: $current->name,
                'email' => $identity->email,
                'avatar_url' => $identity->avatarUrl,
                'link_only' => false,
            ])->save();

            $current->identities()->create([
                'provider' => $identity->provider,
                'provider_user_id' => $identity->subject,
            ]);

            return $current;
        }

        $user = User::create([
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
