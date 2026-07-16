<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Receipt Generation
    |--------------------------------------------------------------------------
    */
    'enabled' => env('RECEIPTS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    */
    'storage_disk' => env('RECEIPTS_STORAGE_DISK', 'local'),

    'storage_path_template' => env(
        'RECEIPTS_STORAGE_PATH_TEMPLATE',
        'receipts/{year}/{receipt_number}.pdf',
    ),

    /*
    |--------------------------------------------------------------------------
    | 80G Certificate
    |--------------------------------------------------------------------------
    */
    '80g' => [
        // Set to true once the trust has obtained 80G registration
        'trust_registered' => env('TRUST_80G_REGISTERED', false),

        // Minimum donation amount (in minor units = paise) above which
        // an 80G certificate is issued. Default ₹500 = 500_00 minor units.
        'certificate_threshold_minor' => (int) env('RECEIPT_80G_THRESHOLD_MINOR', 500_00),

        // Trust's 80G registration number (as allotted by ITD)
        'trust_registration_number' => env('TRUST_80G_NUMBER', null),

        // Trust PAN (required on the 80G certificate)
        'trust_pan' => env('TRUST_PAN', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Form 10BD (quarterly ITD filing)
    |--------------------------------------------------------------------------
    */
    'form_10bd' => [
        // Indian fiscal year starts April (month 4)
        'fiscal_year_start_month' => 4,

        // Only donations above this amount (minor units) are reported
        'currency_code' => 'INR',
        'min_amount_minor' => (int) env('RECEIPT_FORM10BD_MIN_AMOUNT', 50_00),
    ],

    /*
    |--------------------------------------------------------------------------
    | Branding
    |--------------------------------------------------------------------------
    */
    'branding' => [
        'trust_name' => env('TRUST_NAME', 'Temple Trust'),
        'trust_address' => env('TRUST_ADDRESS', ''),
        'trust_email' => env('TRUST_EMAIL', ''),
        'trust_phone' => env('TRUST_PHONE', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF Rendering
    |--------------------------------------------------------------------------
    */
    'rendering' => [
        'paper_size' => env('RECEIPT_PAPER_SIZE', 'a4'),
        'orientation' => env('RECEIPT_ORIENTATION', 'portrait'),
    ],

];
