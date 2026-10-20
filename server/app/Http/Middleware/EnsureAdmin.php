<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin API gate, stage one: an admin on a cookie session. Bearer tokens are refused outright, because
 * the two-factor check lives in the session and a token would sidestep it.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->isAdmin() || ! $request->hasSession()) {
            abort(403, 'Admin access only.');
        }

        return $next($request);
    }
}
