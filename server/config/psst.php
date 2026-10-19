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

    'auth' => [
        'token_ttl_days' => 30,
    ],

];
