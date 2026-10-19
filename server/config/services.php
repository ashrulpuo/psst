<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | OpenID Connect providers whose ID tokens we accept (see App\Auth\IdTokenVerifier).
    | client_ids must list every audience we issue sign-in for: web, iOS, Android clients.
    */
    'oidc' => [
        'google' => [
            'client_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env('GOOGLE_CLIENT_IDS', ''))))),
            'issuers' => ['https://accounts.google.com', 'accounts.google.com'],
            'jwks_uri' => 'https://www.googleapis.com/oauth2/v3/certs',
        ],
        'apple' => [
            'client_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env('APPLE_CLIENT_IDS', ''))))),
            'issuers' => ['https://appleid.apple.com'],
            'jwks_uri' => 'https://appleid.apple.com/auth/keys',
        ],
    ],

];
