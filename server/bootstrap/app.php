<?php

use App\Auth\AccountBanned;
use App\Auth\InvalidIdToken;
use App\Http\Middleware\EnsureNotBanned;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->alias(['not-banned' => EnsureNotBanned::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->dontReport([InvalidIdToken::class, AccountBanned::class]);

        // The reason stays in the logs; the client only learns that sign-in failed.
        $exceptions->render(function (InvalidIdToken $e) {
            Log::warning('auth.id_token_rejected', ['reason' => $e->getMessage()]);

            return response()->json(['message' => 'Sign-in failed. Please try again.'], 401);
        });

        $exceptions->render(fn (AccountBanned $e) => response()->json(['message' => 'This account has been suspended.'], 403));
    })->create();
