<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Access token lifetime
    |--------------------------------------------------------------------------
    |
    | Keep this aligned with Sanctum's expiration setting.
    |
    */
    'access_minutes' => (int) env(
        'ACCESS_TOKEN_TTL',
        config('sanctum.expiration', 15)
    ),

    /*
    |--------------------------------------------------------------------------
    | Refresh token lifetime
    |--------------------------------------------------------------------------
    |
    | Refresh tokens are opaque, hashed in the database and rotated on use.
    |
    */
    'refresh_days' => (int) env('REFRESH_TOKEN_TTL_DAYS', 7),
];
