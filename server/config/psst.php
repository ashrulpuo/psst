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

    'auth' => [
        'token_ttl_days' => 30,
    ],

];
