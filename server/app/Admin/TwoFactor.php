<?php

namespace App\Admin;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Admin second factor: enrol an authenticator app, confirm it, then verify at sign-in with a TOTP code or
 * a one-time recovery code. Recovery codes are stored only as hashes.
 */
class TwoFactor
{
    private const RECOVERY_CODES = 8;

    /**
     * Start (or restart) enrolment. The factor is not active until confirm() succeeds.
     *
     * @return array{secret: string, uri: string}
     */
    public function enroll(User $user): array
    {
        $secret = Totp::generateSecret();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_last_step' => null,
        ])->save();

        return ['secret' => $secret, 'uri' => Totp::uri($user->email, $secret, config('app.name', 'Psst'))];
    }

    /**
     * Activate the factor once the user proves their app works. Returns the recovery codes, shown once.
     *
     * @return list<string>|null null when the code is wrong
     */
    public function confirm(User $user, string $code): ?array
    {
        if ($user->hasTwoFactor() || $user->two_factor_secret === null) {
            return null;
        }

        $step = Totp::verify($user->two_factor_secret, $code);
        if ($step === null) {
            return null;
        }

        $codes = collect(range(1, self::RECOVERY_CODES))
            ->map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)))
            ->all();

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_last_step' => $step,
            'two_factor_recovery_codes' => array_map(fn (string $c) => hash('sha256', $c), $codes),
        ])->save();

        return $codes;
    }

    /**
     * Check a sign-in code. A TOTP step is accepted only once; a recovery code is consumed on use.
     */
    public function verify(User $user, string $code): bool
    {
        if (! $user->hasTwoFactor() || $user->two_factor_secret === null) {
            return false;
        }

        $code = trim($code);

        $step = Totp::verify($user->two_factor_secret, $code);
        if ($step !== null) {
            if ($user->two_factor_last_step !== null && $step <= $user->two_factor_last_step) {
                return false; // replay of a code that was already used
            }
            $user->forceFill(['two_factor_last_step' => $step])->save();

            return true;
        }

        return $this->consumeRecoveryCode($user, Str::lower($code));
    }

    private function consumeRecoveryCode(User $user, string $code): bool
    {
        $hash = hash('sha256', $code);
        $hashes = $user->two_factor_recovery_codes ?? [];
        $matched = null;

        foreach ($hashes as $index => $candidate) {
            if (hash_equals($candidate, $hash)) {
                $matched = $index;
            }
        }

        if ($matched === null) {
            return false;
        }

        unset($hashes[$matched]);
        $user->forceFill(['two_factor_recovery_codes' => array_values($hashes)])->save();

        return true;
    }

    public function recoveryCodesLeft(User $user): int
    {
        return count($user->two_factor_recovery_codes ?? []);
    }
}
