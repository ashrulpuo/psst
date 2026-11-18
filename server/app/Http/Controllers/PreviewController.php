<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Response;

/**
 * Link-preview page for social crawlers. Caddy rewrites crawler requests for /{username} here.
 * It exposes the public username and nothing else: no name, avatar, email or message content.
 */
class PreviewController extends Controller
{
    public function show(string $username): Response
    {
        abort_if(in_array($username, config('psst.reserved_usernames'), true), 404);

        // Same visibility rule as the public profile API: banned users do not exist.
        User::query()
            ->where('username', $username)
            ->whereNull('banned_at')
            ->firstOrFail(['id']);

        return response()
            ->view('preview', ['username' => $username, 'appUrl' => rtrim(config('app.url'), '/')])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
