<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Base TestCase for both Unit and Feature suites.
 *
 * Phase 0.25 only requires that the test harness boot the Laravel
 * application through the public index.php bootstrap. Feature-level
 * browser tests, database tests, and queue tests will be added as
 * their respective subsystems come online.
 */
abstract class TestCase extends BaseTestCase
{
    public function createApplication(): \Illuminate\Foundation\Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }
}