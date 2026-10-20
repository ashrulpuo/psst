<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin API gate, stage two: the second factor has been enrolled AND verified in this session recently.
 * The `code` lets the admin app route to the right screen.
 */
class EnsureAdminTwoFactor
{
    public const SESSION_KEY = 'admin_2fa_verified_at';

    /** Re-verify after this long, however active the session is. */
    public const MAX_AGE_SECONDS = 12 * 3600;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user->hasTwoFactor()) {
            return response()->json(['message' => 'Set up two-factor authentication first.', 'code' => 'two_factor_setup_required'], 403);
        }

        $verifiedAt = (int) $request->session()->get(self::SESSION_KEY, 0);

        if ($verifiedAt === 0 || time() - $verifiedAt > self::MAX_AGE_SECONDS) {
            return response()->json(['message' => 'Enter your two-factor code.', 'code' => 'two_factor_required'], 403);
        }

        return $next($request);
    }
}
