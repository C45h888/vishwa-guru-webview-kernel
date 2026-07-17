<?php

declare(strict_types=1);

namespace App\Runtime\Validation;

use App\Runtime\Diagnostics\KernelSnapshotFactory;
use App\Shared\Contracts\EnvironmentContract;

/**
 * Single entry point for runtime boot validation.
 *
 * Called by RuntimeServiceProvider::boot() exactly once per process.
 * Idempotent: the static $alreadyRan guard short-circuits subsequent
 * calls (cheap path for long-running PHP-FPM workers).
 *
 * Sequence:
 *   1. EnvValidator::assert($env->type())
 *      → throws EnvironmentValidationException on any missing required key.
 *   2. KernelSnapshotFactory::capture()
 *      → builds a snapshot; throws if framework state is unrecoverable.
 *
 * Doctrine: throws on failure — does NOT catch and re-emit. The
 * exception propagates to Laravel's default exception handler, which
 * logs it and renders the standard 500/CLI error. Boot failures are
 * never silenced.
 */
final class BootProbe
{
    /**
     * Static guard: process-wide "has boot probe already run?" flag.
     * Survives across container resolutions in long-running workers.
     */
    private static bool $alreadyRan = false;

    public function __construct(
        private readonly EnvValidator $envValidator,
        private readonly EnvironmentContract $env,
        private readonly KernelSnapshotFactory $snapshotFactory,
    ) {}

    /**
     * Run the full boot assertion. Throws on failure.
     */
    public function assert(): void
    {
        if (self::$alreadyRan) {
            return;
        }

        $this->envValidator->assert($this->env->type());

        // Force a snapshot capture. This is a pure read; if the framework
        // is in an unrecoverable state, capture() throws.
        $this->snapshotFactory->capture();

        self::$alreadyRan = true;
    }

    /**
     * Whether assert() has already been called in this process.
     */
    public function alreadyRan(): bool
    {
        return self::$alreadyRan;
    }

    /**
     * Test-only: reset the static guard between test runs.
     * Not for production use.
     */
    public static function resetForTesting(): void
    {
        self::$alreadyRan = false;
    }
}