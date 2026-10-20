<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

/**
 * Optional Cloudflare Turnstile check for guest sends. Off unless TURNSTILE_SECRET is set.
 */
class Turnstile
{
    public static function enabled(): bool
    {
        return (string) config('psst.turnstile.secret') !== '';
    }

    public static function passes(?string $token, ?string $ip): bool
    {
        if (! self::enabled()) {
            return true;
        }

        if ($token === null || $token === '') {
            return false;
        }

        try {
            return Http::asForm()->timeout(5)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => config('psst.turnstile.secret'),
                'response' => $token,
                'remoteip' => $ip,
            ])->json('success') === true;
        } catch (\Throwable) {
            return false; // fail closed: a broken check must not become an open door
        }
    }
}
