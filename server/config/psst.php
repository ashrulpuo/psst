<?php

return [

    /*
    | Handles nobody may claim: they collide with routes, staff impersonation or the brand.
    */
    'reserved_usernames' => [
        'admin', 'administrator', 'api', 'app', 'auth', 'login', 'logout', 'signup', 'register',
        'settings', 'support', 'help', 'about', 'terms', 'privacy', 'security', 'root', 'system',
        'moderator', 'mod', 'staff', 'team', 'official', 'psst', 'null', 'undefined', 'me', 'www',
        'mail', 'static', 'assets', 'cdn', 'status', 'inbox', 'messages', 'thread', 'threads',
    ],

    'messages' => [
        // A sender may not send more than this many messages in a row without a reply.
        'max_unanswered' => 5,
    ],

    /*
    | Local-development shortcut: POST /api/dev/login {email} returns a bearer token without Google/Apple.
    | Refuses to register in production no matter what the env says.
    */
    'dev_login' => (bool) env('PSST_DEV_LOGIN', false),

    'push' => [
        // Subscriptions are user-supplied URLs the server will POST to, so only real push services are allowed
        // (suffix match on the host, https only). This is the SSRF guard.
        'allowed_hosts' => [
            'fcm.googleapis.com',
            'push.services.mozilla.com',
            'push.apple.com',
            'notify.windows.com',
        ],
        // Extra hosts for local testing (comma-separated). Ignored in production.
        'extra_hosts' => array_filter(array_map('trim', explode(',', (string) env('PUSH_EXTRA_ALLOWED_HOSTS', '')))),
        'max_subscriptions_per_user' => 10,
        'ttl_seconds' => 86400,
    ],

    'auth' => [
        'token_ttl_days' => 30,
    ],

];
