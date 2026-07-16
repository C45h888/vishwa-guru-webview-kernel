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

    public function testFirstReceiptInFyIsTr2026000001(): void
    {
        $this->receipts->method('findMaxReceiptNumberForFY')->willReturn(null);

        $allocator = new ReceiptNumberAllocator($this->receipts, $this->clock);
        $result = $allocator->next();

        $this->assertSame('TR-2026-000001', $result);
    }

    public function testIncrementsFromExistingSequence(): void
    {
        $this->receipts->method('findMaxReceiptNumberForFY')
            ->with(2026)
            ->willReturn('TR-2026-000042');

        $allocator = new ReceiptNumberAllocator($this->receipts, $this->clock);
        $result = $allocator->next();

        $this->assertSame('TR-2026-000043', $result);
    }

    public function testFyBoundaryMarch31ToApril1(): void
    {
        // March 31, 2026 IST — still FY2026
        $clockMarch = new FrozenClock(new \DateTimeImmutable('2026-03-31T23:59:59+05:30'));
        $this->receipts->method('findMaxReceiptNumberForFY')->willReturn(null);

        $allocator = new ReceiptNumberAllocator($this->receipts, $clockMarch);
        $this->assertSame('TR-2026-000001', $allocator->next());

        // April 1, 2026 IST — FY2027 starts
        $clockApril = new FrozenClock(new \DateTimeImmutable('2026-04-01T00:00:01+05:30'));
        $allocatorApril = new ReceiptNumberAllocator($this->receipts, $clockApril);
        $this->assertSame('TR-2027-000001', $allocatorApril->next());
    }

    public function testFiscalYearFromClockReturnsCorrectYear(): void
    {
        // July 2026 → FY2026 (April-Dec)
        $allocator = new ReceiptNumberAllocator(
            $this->receipts,
            new FrozenClock(new \DateTimeImmutable('2026-07-16T00:00:00+05:30')),
        );
        $this->assertSame(2026, $allocator->fiscalYearFromClock());

        // January 2027 → FY2026 (Jan-Mar still belongs to FY2026)
        $allocator2 = new ReceiptNumberAllocator(
            $this->receipts,
            new FrozenClock(new \DateTimeImmutable('2027-01-15T00:00:00+05:30')),
        );
        $this->assertSame(2026, $allocator2->fiscalYearFromClock());

        // April 2027 → FY2027
        $allocator3 = new ReceiptNumberAllocator(
            $this->receipts,
            new FrozenClock(new \DateTimeImmutable('2027-04-01T00:00:00+05:30')),
        );
        $this->assertSame(2027, $allocator3->fiscalYearFromClock());
    }

    public function testParseSeqFromExistingReceiptNumber(): void
    {
        // Use reflection to test private parseSeq
        $allocator = new ReceiptNumberAllocator($this->receipts, $this->clock);

        $reflection = new \ReflectionClass($allocator);
        $method = $reflection->getMethod('parseSeq');
        $method->setAccessible(true);

        $this->assertSame(42, $method->invoke($allocator, 'TR-2026-000042'));
        $this->assertSame(1, $method->invoke($allocator, 'TR-2026-000001'));
        $this->assertSame(999999, $method->invoke($allocator, 'TR-2026-999999'));
    }
}
