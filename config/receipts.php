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
    | Worker cadence (receipt substrate pipeline)
    |--------------------------------------------------------------------------
    |
    | Each worker stage under App\Payments\Receipts\Workers runs with a
    | wall-clock budget (timeout_ms) and bounded attempts (max_attempts),
    | with backoff_ms between attempts. A stage that exhausts its cadence
    | fails fast into the queue-level retry ladder (GenerateReceiptJob).
    |
    */
    'workers' => [
        'data' => [
            'timeout_ms' => (int) env('RECEIPT_WORKER_DATA_TIMEOUT_MS', 2000),
            'max_attempts' => (int) env('RECEIPT_WORKER_DATA_ATTEMPTS', 2),
        ],
        'types' => [
            'timeout_ms' => (int) env('RECEIPT_WORKER_TYPES_TIMEOUT_MS', 1000),
            'max_attempts' => (int) env('RECEIPT_WORKER_TYPES_ATTEMPTS', 2),
        ],
        'design' => [
            'timeout_ms' => (int) env('RECEIPT_WORKER_DESIGN_TIMEOUT_MS', 8000),
            'max_attempts' => (int) env('RECEIPT_WORKER_DESIGN_ATTEMPTS', 3),
        ],
        'backoff_ms' => [100, 400],
    ],

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

        // NOTE: statutory donee credentials (trust PAN, TAN, 80G
        // registration number, 12A number) live EXCLUSIVELY in the DB
        // plane — table `trust_identities`, key `canonical` — seeded by
        // the k_payments trust_identities migrations. There are
        // deliberately NO TRUST_* env keys for them: credentials must
        // flow DB → DataWorker → TypesWorker → ReceiptDocument, never
        // through env/config. Only the operational flags above stay here.
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
        'trust_email' => env('TRUST_EMAIL', 'sriramguruji@vsrsms.in'),
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
