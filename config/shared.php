<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Money Defaults
    |--------------------------------------------------------------------------
    |
    | Default currency and rounding mode for monetary calculations.
    |
    */

    'money' => [
        'default_currency' => env('SHARED_MONEY_DEFAULT_CURRENCY', 'INR'),
        'rounding_mode'    => env('SHARED_MONEY_ROUNDING_MODE', 'half_even'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Identifier Strategy
    |--------------------------------------------------------------------------
    |
    | Identifiers are generated for all persistent entities. The strategy
    | determines which generator implementation is used by the container.
    |
    | Supported values: ulid, uuid (future).
    |
    */

    'identifiers' => [
        'strategy' => env('SHARED_IDENTIFIER_STRATEGY', 'ulid'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Time Zone
    |--------------------------------------------------------------------------
    */

    'timezone' => env('APP_TIMEZONE', 'Asia/Kolkata'),

];