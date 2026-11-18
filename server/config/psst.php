<?php

return [

    /*
    | Handles nobody may claim: they collide with routes, staff impersonation or the brand.
    */
    'reserved_usernames' => [
        'admin', 'administrator', 'api', 'app', 'auth', 'login', 'logout', 'signup', 'register',
        'settings', 'support', 'help', 'about', 'terms', 'privacy', 'security', 'root', 'system',
        'moderator', 'mod', 'staff', 'team', 'official', 'psst', 'null', 'undefined', 'me', 'www',
        'mail', 'static', 'assets', 'cdn', 'status', 'inbox', 'link', 'get-link', 'messages', 'thread', 'threads',
        // Top-level SPA routes (client/src/router/routes.ts) that fit the username shape and would shadow a profile.
        'welcome', 'sent',
    ],

    /*
    | Send limits behind the `messages` limiter. Env overrides; changing them needs the app container recreated.
    */
    'rate_limits' => [
        'guest_per_ip_minute' => (int) env('PSST_GUEST_PER_IP_MINUTE', 4),
        'guest_per_ip_hour' => (int) env('PSST_GUEST_PER_IP_HOUR', 30),
        'guest_per_token_minute' => (int) env('PSST_GUEST_PER_TOKEN_MINUTE', 3),
        'guest_per_target_hour' => (int) env('PSST_GUEST_PER_TARGET_HOUR', 200),
        'user_per_minute' => (int) env('PSST_USER_PER_MINUTE', 20),
        'user_per_day' => (int) env('PSST_USER_PER_DAY', 300),
    ],

    // Pause switch: false makes anonymous (guest) sends return 503; signed-in senders are unaffected.
    'guest_sends_enabled' => (bool) env('PSST_GUEST_SENDS', true),

    'messages' => [
        // A sender may not send more than this many messages in a row without a reply.
        'max_unanswered' => 5,
    ],

    'moderation' => [
        // Inbound messages containing any of these (case-insensitive, whole words) are flagged for review but still delivered.
        'keywords' => [
            'kill you', 'kill yourself', 'kys', 'i will find you', 'going to hurt you', 'i will hurt you',
            'bomb threat', 'shoot up', 'rape you', 'want to die', 'end my life', 'suicide', 'kill myself',
        ],
        // Where flag alerts go. Empty = no email.
        'alert_email' => env('ADMIN_ALERT_EMAIL'),
        // At most one alert email per this many minutes, however many messages are flagged.
        'alert_window_minutes' => 10,
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

    'turnstile' => [
        // Cloudflare Turnstile secret key. Empty = no challenge. The site key goes in the client (QCLI_TURNSTILE_SITE_KEY).
        'secret' => env('TURNSTILE_SECRET', ''),
    ],

    'backup' => [
        'disk' => 'backups',
        // Encrypts the nightly dump. Empty = the backup command refuses to run.
        'passphrase' => env('BACKUP_PASSPHRASE', ''),
        // Prune runs only after a successful backup, so 13 keeps the "within 14 days" promise in the privacy copy true.
        'keep_days' => 13,
    ],

    'auth' => [
        'token_ttl_days' => 30,
    ],

];
