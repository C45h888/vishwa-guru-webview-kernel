<?php

declare(strict_types=1);

/**
 * Shared bootstrap for the CMS kernel probe suite.
 *
 * Mirrors scripts/razorpay-probes/_bootstrap.php but adapted for
 * kernel-readiness: the CMS probes run against the in-process Laravel
 * container (no live gateway calls).
 *
 * Two non-negotiable guards:
 *   1. APP_ENV must equal `testing`
 *   2. CmsServiceProvider must be registered (the line in config/app.php
 *      that boot-loads the kernel is the entry condition).
 *
 * Exit codes: 2 = FATAL guard failure (stderr message).
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$env = (string) config('app.env');

if ($env !== 'testing') {
    fwrite(STDERR, "FATAL: APP_ENV must be 'testing', got '{$env}'. "
        . "Run via scripts/validate-cms.sh which sets APP_ENV=testing.\n");
    exit(2);
}

$providers = $app->getProviders(\App\Cms\Providers\CmsServiceProvider::class);
if (empty($providers)) {
    fwrite(STDERR, "FATAL: CmsServiceProvider is not registered. "
        . "Check config/app.php:58 — the line binding it must be active.\n");
    exit(2);
}

return $app;
