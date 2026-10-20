<?php

use App\Http\Controllers\Auth\DevLoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\SessionLoginController;
use App\Http\Controllers\Auth\TokenLoginController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Threads\ThreadListController;
use App\Http\Controllers\Threads\ThreadMessageController;
use App\Http\Controllers\Threads\ThreadStateController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->middleware('throttle:auth')->group(function () {
    // Cookie session for browsers (call /sanctum/csrf-cookie first), bearer token for native apps.
    Route::post('{provider}', SessionLoginController::class)->whereIn('provider', ['google', 'apple']);
    Route::post('{provider}/token', TokenLoginController::class)->whereIn('provider', ['google', 'apple']);
});

// Public: the browser needs the VAPID public key before it can subscribe.
Route::get('push/config', [PushController::class, 'config'])->middleware('throttle:profile');

// Public: the link page needs this before sign-in.
Route::get('profiles/{username}', [ProfileController::class, 'show'])
    ->where('username', '[a-z0-9_.]{3,24}')
    ->middleware('throttle:profile');

if (config('psst.dev_login') && ! app()->isProduction()) {
    Route::post('dev/login', DevLoginController::class)->middleware('throttle:auth');
}

Route::middleware(['auth:sanctum', 'not-banned'])->group(function () {
    Route::post('auth/logout', LogoutController::class);

    Route::get('me', [MeController::class, 'show']);
    Route::patch('me/username', [MeController::class, 'setUsername']);
    Route::patch('me/preferences', [MeController::class, 'updatePreferences']);

    Route::post('push/subscriptions', [PushController::class, 'store'])->middleware('throttle:reports');
    Route::delete('push/subscriptions', [PushController::class, 'destroy']);

    Route::post('profiles/{username}/messages', [ProfileController::class, 'send'])
        ->where('username', '[a-z0-9_.]{3,24}')
        ->middleware('throttle:messages');

    Route::get('inbox', [ThreadListController::class, 'inbox']);
    Route::get('sent', [ThreadListController::class, 'sent']);

    Route::prefix('threads/{thread}')->whereUuid('thread')->group(function () {
        Route::get('messages', [ThreadMessageController::class, 'index']);
        Route::post('messages', [ThreadMessageController::class, 'store'])->middleware('throttle:messages');
        Route::post('read', [ThreadStateController::class, 'read']);
        Route::put('block', [ThreadStateController::class, 'block']);
        Route::delete('block', [ThreadStateController::class, 'unblock']);
    });

    Route::post('messages/{message}/report', [ReportController::class, 'store'])
        ->whereUuid('message')
        ->middleware('throttle:reports');
});
