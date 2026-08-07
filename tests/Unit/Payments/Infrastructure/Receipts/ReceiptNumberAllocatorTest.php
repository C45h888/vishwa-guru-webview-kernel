<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Infrastructure\Receipts;

use App\Payments\Domain\Repositories\ReceiptRepositoryContract;
use App\Payments\Infrastructure\Receipts\ReceiptNumberAllocator;
use App\Shared\Support\Clock;
use App\Shared\Support\FrozenClock;
use PHPUnit\Framework\TestCase;

class ReceiptNumberAllocatorTest extends TestCase
{
    private ReceiptRepositoryContract $receipts;
    private Clock $clock;

    protected function setUp(): void
    {
        $this->receipts = $this->createMock(ReceiptRepositoryContract::class);
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-07-16T10:00:00+05:30'));
    }

    public function testFirstReceiptInFyIsTr2026FirstSequence(): void
    {
        $this->receipts->method('findMaxReceiptNumberForFY')->willReturn(null);

        $allocator = new ReceiptNumberAllocator($this->receipts, $this->clock);
        $result = $allocator->next();

        // Wave 1 m5 fix (2026-08-06): the receipt number now carries
        // an 8-char URL-safe random salt. The test asserts against the
        // canonical ANCHORED_PATTERN rather than a literal expected
        // value, so the test is robust against the CSPRNG.
        self::assertSame(
            1,
            preg_match(ReceiptNumberAllocator::ANCHORED_PATTERN, $result),
            "First receipt in FY2026 [{$result}] should match the canonical pattern.",
        );
        self::assertStringStartsWith('TR-2026-000001-', $result);
    }

    public function testIncrementsFromExistingSequence(): void
    {
        $this->receipts->method('findMaxReceiptNumberForFY')
            ->with(2026)
            ->willReturn('TR-2026-000042-A7c3ZpQ9');

        $allocator = new ReceiptNumberAllocator($this->receipts, $this->clock);
        $result = $allocator->next();

        // Same pattern-only check — the salt is random, only the
        // TR-YYYY-NNNNNN- prefix is deterministic from the input.
        self::assertSame(
            1,
            preg_match(ReceiptNumberAllocator::ANCHORED_PATTERN, $result),
            "Incremented receipt [{$result}] should match the canonical pattern.",
        );
        self::assertStringStartsWith('TR-2026-000043-', $result);
    }

    public function testFyBoundaryMarch31ToApril1(): void
    {
        // March 31, 2026 IST — last day of FY2025 (Indian FY runs
        // April→March). Test fixture mocks findMaxReceiptNumberForFY
        // for ANY FY to return null — so allocator emits seq=1.
        $clockMarch = new FrozenClock(new \DateTimeImmutable('2026-03-31T23:59:59+05:30'));
        $this->receipts->method('findMaxReceiptNumberForFY')->willReturn(null);

        $allocator = new ReceiptNumberAllocator($this->receipts, $clockMarch);
        $marchReceipt = $allocator->next();
        self::assertStringStartsWith('TR-2025-000001-', $marchReceipt);

        // April 1, 2026 IST — FY2026 starts
        $clockApril = new FrozenClock(new \DateTimeImmutable('2026-04-01T00:00:01+05:30'));
        $allocatorApril = new ReceiptNumberAllocator($this->receipts, $clockApril);
        $aprilReceipt = $allocatorApril->next();
        self::assertStringStartsWith('TR-2026-000001-', $aprilReceipt);
    }

    public function testFiscalYearFromClockReturnsCorrectYear(): void
    {
        // July 2026 → FY2026 (April-Dec)
        $allocator = new ReceiptNumberAllocator(
            $this->receipts,
            new FrozenClock(new \DateTimeImmutable('2026-07-16T00:00:00+05:30')),
        );
        self::assertSame(2026, $allocator->fiscalYearFromClock());

        // January 2027 → FY2026 (Jan-Mar still belongs to FY2026)
        $allocator2 = new ReceiptNumberAllocator(
            $this->receipts,
            new FrozenClock(new \DateTimeImmutable('2027-01-15T00:00:00+05:30')),
        );
        self::assertSame(2026, $allocator2->fiscalYearFromClock());

        // April 2027 → FY2027
        $allocator3 = new ReceiptNumberAllocator(
            $this->receipts,
            new FrozenClock(new \DateTimeImmutable('2027-04-01T00:00:00+05:30')),
        );
        self::assertSame(2027, $allocator3->fiscalYearFromClock());
    }

    public function testParseSeqFromExistingReceiptNumber(): void
    {
        // Use reflection to test private parseSeq. The new format
        // appends an 8-char CSPRNG salt; parseSeq extracts only the
        // sequence number and ignores the salt.
        $allocator = new ReceiptNumberAllocator($this->receipts, $this->clock);

        $reflection = new \ReflectionClass($allocator);
        $method = $reflection->getMethod('parseSeq');
        $method->setAccessible(true);

        self::assertSame(42, $method->invoke($allocator, 'TR-2026-000042-A7c3ZpQ9'));
        self::assertSame(1, $method->invoke($allocator, 'TR-2026-000001-A7c3ZpQ9'));
        self::assertSame(999999, $method->invoke($allocator, 'TR-2026-999999-A7c3ZpQ9'));
    }
}
