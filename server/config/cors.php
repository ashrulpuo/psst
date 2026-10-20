<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    // Only the native app shells need CORS (the web apps are same-origin). WebViews report these origins:
    // iOS capacitor://localhost, Android https://localhost (androidScheme: 'https'), http://localhost for dev.
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'CORS_ALLOWED_ORIGINS',
        'capacitor://localhost,https://localhost,http://localhost',
    ))))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Authorization', 'Content-Type', 'Accept', 'X-Requested-With', 'X-Socket-ID', 'X-Guest-Token'],

    'exposed_headers' => [],

    'max_age' => 600,

    'supports_credentials' => false,

];
