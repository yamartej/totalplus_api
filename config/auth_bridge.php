<?php

return [
    /*
    |--------------------------------------------------------------------------
    | NextAuth -> Laravel authentication bridge
    |--------------------------------------------------------------------------
    |
    | This secret is shared ONLY between the Next.js server and Laravel.
    | Never expose it with a NEXT_PUBLIC_ prefix.
    |
    */
    'secret' => env('AUTH_BRIDGE_SECRET'),

    // Maximum age, in seconds, for a signed provider-login assertion.
    'ttl' => (int) env('AUTH_BRIDGE_TTL', 60),

    'providers' => [
        'google',
        'github',
        'facebook',
    ],
];
