<?php

declare(strict_types=1);

namespace App\Shared\Support;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Deterministic Clock implementation for tests.
 *
 * The clock advances only when {@see self::advance()} or
 * {@see self::setTo()} is called explicitly. Tests should bind this
 * implementation through the service container during the test
 * bootstrap to obtain deterministic timestamps.
 */
final class FrozenClock implements Clock
{
    private DateTimeImmutable $now;

    private readonly DateTimeZone $timezone;

    public function __construct(
        ?DateTimeImmutable $initial = null,
        ?DateTimeZone $timezone = null,
    ) {
        $this->timezone = $timezone ?? new DateTimeZone('UTC');
        $this->now = ($initial ?? new DateTimeImmutable('now', $this->timezone))
            ->setTimezone($this->timezone);
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function timestamp(): int
    {
        return $this->now->getTimestamp();
    }

    public function timezone(): DateTimeZone
    {
        return $this->timezone;
    }

    /**
     * Pin the clock to an absolute instant.
     */
    public function setTo(DateTimeImmutable $instant): void
    {
        $this->now = $instant->setTimezone($this->timezone);
    }

    /**
     * Advance the clock by a relative interval.
     */
    public function advance(string $spec): void
    {
        $this->now = $this->now->modify($spec);
    }
}
