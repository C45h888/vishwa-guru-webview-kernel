<?php

declare(strict_types=1);

/*
 |--------------------------------------------------------------------------
 | Payments Configuration
 |--------------------------------------------------------------------------
 |
 | Configuration for the Financial Kernel. Each provider section maps to
 | one PaymentGatewayContract implementation in
 | app/Payments/Infrastructure/Adapters/<Provider>/.
 |
 | Per-provider "enabled" flag is the master switch. Production deployments
 | should set non-essential providers (e.g. inmemory) to false.
 */

return [

    /*
     * Default provider code used by PaymentOrchestrator when the
     * DonationIntent doesn't specify a preferred provider. Must match
     * one of the keys in `providers` below.
     */
    'default_provider' => env('PAYMENTS_DEFAULT_PROVIDER', 'razorpay'),

    /*
     * Provider registry. The provider code (key) is what PaymentProvider
     * enum cases and `payments.provider_code` columns use.
     */
    'providers' => [

        'razorpay' => [
            'enabled'                                => env('RAZORPAY_ENABLED', true),
            'key_id'                                 => env('RAZORPAY_KEY_ID', ''),
            'key_secret'                             => env('RAZORPAY_KEY_SECRET', ''),
            'webhook_secret'                         => env('RAZORPAY_WEBHOOK_SECRET', ''),
            'webhook_signature_header'               => env('RAZORPAY_WEBHOOK_HEADER', 'X-Razorpay-Signature'),
            'webhook_timestamp_tolerance_seconds'    => (int) env('RAZORPAY_WEBHOOK_TOLERANCE', 300),
        ],

        'paypal' => [
            // PayPal is OFF by default. Razorpay is the sole public
            // gateway for the Indian launch; flip to true (and provide
            // PAYPAL_CLIENT_ID / PAYPAL_CLIENT_SECRET / PAYPAL_WEBHOOK_ID)
            // only when international PayPal flows are explicitly enabled.
            'enabled'                                => env('PAYPAL_ENABLED', false),
            'client_id'                              => env('PAYPAL_CLIENT_ID', ''),
            'client_secret'                          => env('PAYPAL_CLIENT_SECRET', ''),
            'webhook_id'                             => env('PAYPAL_WEBHOOK_ID', ''),
            'webhook_signature_header'               => env('PAYPAL_WEBHOOK_HEADER', 'PAYPAL-TRANSMISSION-SIG'),
            'environment'                            => env('PAYPAL_ENV', 'sandbox'),   // sandbox|live
        ],

        'inmemory' => [
            'enabled' => env('PAYMENTS_INMEMORY_ENABLED', false),
        ],
    ],

    /*
     | Retry policy consumed by FailureStateService.
     | classifications map to FailureClassification enum case names.
     */
    'retry_policy' => [
        'max_retries' => (int) env('PAYMENTS_MAX_RETRIES', 3),
        'backoff_seconds' => [60, 300, 900],
        'classifications' => [
            'recoverable_transient' => 3,
            'recoverable_terminal'  => 5,
            'terminal_invalid'      => 0,
            'terminal_fraud'        => 0,
        ],
    ],

    /*
     | Tag name for the Laravel service container pool of gateway adapters.
     | PaymentProviderSelector reads from this tag at construction time.
     */
    'gateway_pool_tag' => 'payment_gateway',
];
