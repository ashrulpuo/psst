<?php

namespace App\Messaging;

use Illuminate\Http\Request;

/**
 * How an unsigned sender is recognised, without storing anything that identifies them:
 * a random token kept in their browser (only its SHA-256 is stored) and an HMAC of their IP address.
 */
class GuestKey
{
    public const HEADER = 'X-Guest-Token';

    public static function newToken(): string
    {
        return bin2hex(random_bytes(24));
    }

    /** The token the client presented, if it is well-formed. */
    public static function presented(Request $request): ?string
    {
        $token = $request->header(self::HEADER);

        return is_string($token) && preg_match('/^[A-Za-z0-9]{32,64}$/', $token) === 1 ? $token : null;
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function hashIp(?string $ip): string
    {
        return hash_hmac('sha256', (string) $ip, (string) config('app.key'));
    }
}
