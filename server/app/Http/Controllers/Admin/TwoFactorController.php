<?php

namespace App\Http\Controllers\Admin;

use App\Admin\AuditLogger;
use App\Admin\TwoFactor;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureAdminTwoFactor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TwoFactorController extends Controller
{
    public function __construct(
        private readonly TwoFactor $twoFactor,
        private readonly AuditLogger $audit,
    ) {}

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $verifiedAt = (int) $request->session()->get(EnsureAdminTwoFactor::SESSION_KEY, 0);

        return response()->json(['data' => [
            'enrolled' => $user->hasTwoFactor(),
            'pending' => ! $user->hasTwoFactor() && $user->two_factor_secret !== null,
            'verified' => $verifiedAt !== 0 && time() - $verifiedAt <= EnsureAdminTwoFactor::MAX_AGE_SECONDS,
            'recovery_codes_left' => $this->twoFactor->recoveryCodesLeft($user),
        ]]);
    }

    /**
     * Begin enrolment. Refused once a factor is active: replacing it is a deliberate support action
     * (`php artisan psst:reset-2fa`), so a hijacked session cannot swap in the attacker's authenticator.
     */
    public function enroll(Request $request): JsonResponse
    {
        abort_if($request->user()->hasTwoFactor(), 409, 'Two-factor authentication is already set up.');

        return response()->json(['data' => $this->twoFactor->enroll($request->user())]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $code = $request->validate(['code' => ['required', 'string', 'max:20']])['code'];

        $recoveryCodes = $this->twoFactor->confirm($request->user(), $code);
        abort_if($recoveryCodes === null, 422, 'That code did not match. Check the time on your device and try again.');

        $this->markVerified($request);
        $this->audit->record($request, 'admin.2fa_enrolled');

        // Shown exactly once: only hashes are stored.
        return response()->json(['data' => ['recovery_codes' => $recoveryCodes]]);
    }

    public function verify(Request $request): JsonResponse
    {
        $code = $request->validate(['code' => ['required', 'string', 'max:20']])['code'];

        if (! $this->twoFactor->verify($request->user(), $code)) {
            $this->audit->record($request, 'admin.2fa_failed');

            abort(422, 'That code did not work.');
        }

        $this->markVerified($request);
        $this->audit->record($request, 'admin.2fa_verified');

        return response()->json(['data' => ['verified' => true, 'recovery_codes_left' => $this->twoFactor->recoveryCodesLeft($request->user())]]);
    }

    private function markVerified(Request $request): void
    {
        // A fresh session id after the privilege change defeats session fixation.
        $request->session()->regenerate();
        $request->session()->put(EnsureAdminTwoFactor::SESSION_KEY, time());
    }
}
