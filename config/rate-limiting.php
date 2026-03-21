<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | This file defines the rate limiting rules for various endpoints.
    | These values are used by the RateLimiter facade in AppServiceProvider.
    |
    */

    'login' => [
        'max_attempts' => env('RATE_LIMIT_LOGIN_MAX', 5),
        'decay_minutes' => env('RATE_LIMIT_LOGIN_DECAY', 15),
    ],

    'register' => [
        'max_attempts' => env('RATE_LIMIT_REGISTER_MAX', 3),
        'decay_minutes' => env('RATE_LIMIT_REGISTER_DECAY', 60),
    ],

    'password_reset' => [
        'max_attempts' => env('RATE_LIMIT_PASSWORD_RESET_MAX', 3),
        'decay_minutes' => env('RATE_LIMIT_PASSWORD_RESET_DECAY', 60),
    ],

    'api' => [
        'max_attempts' => env('RATE_LIMIT_API_MAX', 60),
        'decay_minutes' => env('RATE_LIMIT_API_DECAY', 1),
    ],

    'email_verification' => [
        'max_attempts' => env('RATE_LIMIT_EMAIL_VERIFICATION_MAX', 3),
        'decay_minutes' => env('RATE_LIMIT_EMAIL_VERIFICATION_DECAY', 1),
    ],

    'uploads' => [
        'max_attempts' => env('RATE_LIMIT_UPLOADS_MAX', 20),
        'decay_minutes' => env('RATE_LIMIT_UPLOADS_DECAY', 1),
    ],

    'downloads' => [
        'max_attempts' => env('RATE_LIMIT_DOWNLOADS_MAX', 60),
        'decay_minutes' => env('RATE_LIMIT_DOWNLOADS_DECAY', 1),
    ],

];
