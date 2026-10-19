<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\SessionLoginController;
use App\Http\Controllers\Auth\TokenLoginController;
use App\Http\Controllers\MeController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->middleware('throttle:auth')->group(function () {
    // Cookie session for browsers (call /sanctum/csrf-cookie first), bearer token for native apps.
    Route::post('{provider}', SessionLoginController::class)->whereIn('provider', ['google', 'apple']);
    Route::post('{provider}/token', TokenLoginController::class)->whereIn('provider', ['google', 'apple']);
});

Route::middleware(['auth:sanctum', 'not-banned'])->group(function () {
    Route::post('auth/logout', LogoutController::class);

    Route::get('me', [MeController::class, 'show']);
    Route::patch('me/username', [MeController::class, 'setUsername']);
});
