<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Payment Gateway Credentials
    |--------------------------------------------------------------------------
    */

    'razorpay' => [
        'key_id'     => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
        'api_base_uri' => 'https://api.razorpay.com/v1/',
    ],

    'paypal' => [
        'client_id'     => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'mode'          => env('PAYPAL_MODE', 'sandbox'),
        'webhook_id'    => env('PAYPAL_WEBHOOK_ID'),
        'api_base_uri'  => env('PAYPAL_MODE', 'sandbox') === 'sandbox'
            ? 'https://api-m.sandbox.paypal.com/'
            : 'https://api-m.paypal.com/',
    ],

    /*
    |--------------------------------------------------------------------------
    | Neon PostgreSQL
    |--------------------------------------------------------------------------
    */

    'neon' => [
        'branch'   => env('NEON_BRANCH', 'main'),
        'role'     => env('NEON_ROLE', 'app'),
    ],
];
