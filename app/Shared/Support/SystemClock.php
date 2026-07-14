<?php

declare(strict_types=1);

namespace App\Shared\Support;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Default Clock implementation that returns the host's wall-clock time.
 *
 * This is the production implementation. Tests are expected to swap it
 * with {@see FrozenClock} through the service container.
 */
final class SystemClock implements Clock
{
    private readonly DateTimeZone $timezone;

    public function __construct(?DateTimeZone $timezone = null)
    {
        $this->timezone = $timezone ?? new DateTimeZone(date_default_timezone_get());
    }

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->timezone);
    }

    public function timestamp(): int
    {
        return time();
    }

    public function timezone(): DateTimeZone
    {
        return $this->timezone;
    }
}
