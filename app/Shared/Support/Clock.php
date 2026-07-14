<?php

declare(strict_types=1);

namespace App\Shared\Support;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Time abstraction.
 *
 * Implementations:
 *   - SystemClock: production, returns host's wall-clock time.
 *   - FrozenClock: tests, advances only via setTo()/advance().
 *
 * Domain code depends on this contract, not on DateTimeImmutable
 * directly, so that tests can pin time deterministically.
 */
interface Clock
{
    /**
     * Get the current time as an immutable datetime.
     */
    public function now(): DateTimeImmutable;

    /**
     * Get the current Unix timestamp (seconds).
     */
    public function timestamp(): int;

    /**
     * Get the timezone used by this clock.
     */
    public function timezone(): DateTimeZone;
}
