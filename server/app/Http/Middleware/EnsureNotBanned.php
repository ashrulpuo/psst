<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotBanned
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isBanned()) {
            abort(403, 'This account has been suspended.');
        }

        return $next($request);
    }
}
