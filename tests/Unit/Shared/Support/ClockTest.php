<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Support;

use App\Shared\Support\FrozenClock;
use App\Shared\Support\IdentifierGenerator;
use App\Shared\Support\SystemClock;
use App\Shared\Support\UlidGenerator;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

class ClockTest extends TestCase
{
    public function testSystemClockReturnsRecentTimestamp(): void
    {
        $clock = new SystemClock;
        $before = time();
        $ts = $clock->timestamp();
        $after = time();

        $this->assertGreaterThanOrEqual($before, $ts);
        $this->assertLessThanOrEqual($after, $ts);
    }

    public function testSystemClockNowIsImmutable(): void
    {
        $clock = new SystemClock;
        $now = $clock->now();
        $this->assertInstanceOf(DateTimeImmutable::class, $now);
    }

    public function testSystemClockHasTimezone(): void
    {
        $clock = new SystemClock;
        $this->assertInstanceOf(DateTimeZone::class, $clock->timezone());
    }

    public function testFrozenClockReturnsSameInstant(): void
    {
        $instant = new DateTimeImmutable('2026-01-15T10:00:00+00:00');
        $clock = new FrozenClock($instant);

        $this->assertSame($instant->getTimestamp(), $clock->timestamp());
        $this->assertSame(
            $instant->setTimezone($clock->timezone())->format('c'),
            $clock->now()->format('c')
        );
    }

    public function testFrozenClockDoesNotAdvanceOnItsOwn(): void
    {
        $initial = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $clock = new FrozenClock($initial);

        usleep(20000); // 20ms

        $this->assertSame($initial->getTimestamp(), $clock->timestamp());
    }

    public function testFrozenClockSetTo(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00'));
        $target = new DateTimeImmutable('2030-12-31T23:59:59+00:00');
        $clock->setTo($target);

        $this->assertSame($target->getTimestamp(), $clock->timestamp());
    }

    public function testFrozenClockAdvance(): void
    {
        $initial = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $clock = new FrozenClock($initial);
        $clock->advance('+1 hour');

        $expected = $initial->getTimestamp() + 3600;
        $this->assertSame($expected, $clock->timestamp());
    }

    public function testFrozenClockAdvanceMultiple(): void
    {
        $initial = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $clock = new FrozenClock($initial);
        $clock->advance('+1 day');
        $clock->advance('+30 minutes');

        $expected = $initial->getTimestamp() + 86400 + 1800;
        $this->assertSame($expected, $clock->timestamp());
    }
}

class UlidGeneratorImplementsContractTest extends TestCase
{
    public function testUlidGeneratorImplementsIdentifierGenerator(): void
    {
        $generator = new UlidGenerator;
        $this->assertInstanceOf(IdentifierGenerator::class, $generator);
    }

    public function testUlidGeneratorNextProducesValidId(): void
    {
        $generator = new UlidGenerator;
        $id = $generator->next();

        $this->assertSame(26, strlen($id));
        $this->assertMatchesRegularExpression('/^[0-9A-Z]{26}$/', $id);
    }

    public function testUniqueSequentialIds(): void
    {
        $generator = new UlidGenerator;
        $ids = [];
        for ($i = 0; $i < 100; $i++) {
            $ids[] = $generator->next();
        }
        $this->assertCount(100, array_unique($ids));
    }
}