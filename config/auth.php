<?php

use App\Models\Candidate;
use App\Models\OfficerUser;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | Candidates are the primary identity in this app and authenticate via
    | phone/email + OTP (no password). Verification officers authenticate
    | against the existing admin.users table on a separate guard, gated by
    | an additive RBAC permission — see OfficerUser::hasVerificationAccess().
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'candidate'),
        'passwords' => null,
    ],

    'guards' => [
        'candidate' => [
            'driver' => 'session',
            'provider' => 'candidates',
        ],
        'officer' => [
            'driver' => 'session',
            'provider' => 'officers',
        ],
    ],

    'providers' => [
        'candidates' => [
            'driver' => 'eloquent',
            'model' => Candidate::class,
        ],
        'officers' => [
            'driver' => 'eloquent',
            'model' => OfficerUser::class,
        ],
    ],

    'passwords' => [],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
