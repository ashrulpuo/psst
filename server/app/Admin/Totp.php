<?php

namespace App\Admin;

/**
 * RFC 6238 time-based one-time passwords (HMAC-SHA1, 6 digits, 30 s steps), as used by authenticator apps.
 */
final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public const PERIOD = 30;

    public const DIGITS = 6;

    /** 160-bit secret, base32 encoded (the form authenticator apps expect). */
    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    public static function uri(string $account, string $secret, string $issuer): string
    {
        return 'otpauth://totp/'.rawurlencode("$issuer:$account").'?'.http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public static function code(string $secret, int $step): string
    {
        $hash = hash_hmac('sha1', pack('J', $step), self::base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);

        return str_pad((string) ($binary % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Check a code within ±1 step of clock drift. Returns the matching step (so the caller can refuse a replay)
     * or null. Comparison is constant-time.
     */
    public static function verify(string $secret, string $code, ?int $now = null, int $window = 1): ?int
    {
        if (! preg_match('/^\d{'.self::DIGITS.'}$/', $code)) {
            return null;
        }

        $current = intdiv($now ?? time(), self::PERIOD);
        $matched = null;

        for ($step = $current - $window; $step <= $current + $window; $step++) {
            // No early exit: every step is compared so timing does not reveal which one matched.
            if (hash_equals(self::code($secret, $step), $code)) {
                $matched = $step;
            }
        }

        return $matched;
    }

    public static function base32Encode(string $data): string
    {
        $bits = '';
        foreach (str_split($data) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    public static function base32Decode(string $encoded): string
    {
        $bits = '';
        foreach (str_split(strtoupper(rtrim($encoded, '='))) as $char) {
            $value = strpos(self::ALPHABET, $char);
            if ($value === false) {
                throw new \InvalidArgumentException('Invalid base32 secret.');
            }
            $bits .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }

        return $out;
    }
}
