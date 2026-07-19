<?php

declare(strict_types=1);

/**
 * Shared bootstrap for Razorpay probe suite.
 *
 * Two non-negotiable guards:
 *   1. RAZORPAY_KEY_ID must start with `rzp_test_`
 *   2. APP_ENV must equal `testing`
 *
 * Returns the booted Laravel application. Probes MUST require this file.
 * Exit codes: 2 = FATAL guard failure (stderr message).
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$keyId = (string) config('payments.providers.razorpay.key_id', '');
$env   = (string) config('app.env');

if (! str_starts_with($keyId, 'rzp_test_')) {
    fwrite(STDERR, "FATAL: refusing to run with non-test Razorpay key. "
        . "Set RAZORPAY_KEY_ID in .env.testing to a rzp_test_* value.\n");
    exit(2);
}

if ($env !== 'testing') {
    fwrite(STDERR, "FATAL: APP_ENV must be 'testing', got '{$env}'. "
        . "Run via scripts/validate-razorpay.sh which sets APP_ENV=testing.\n");
    exit(2);
}

return $app;