<?php

use App\Http\Controllers\PreviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Crawler link previews. Caddy rewrites crawler requests for /{username} here; browsers never reach it.
Route::get('/preview/{username}', [PreviewController::class, 'show'])
    ->where('username', '[a-z0-9_.]{3,24}');
