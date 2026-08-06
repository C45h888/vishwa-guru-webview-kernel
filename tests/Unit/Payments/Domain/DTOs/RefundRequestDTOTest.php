<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Domain\DTOs;

use App\Payments\Domain\DTOs\RefundRequestDTO;
use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Exceptions\RefundExceededException;
use App\Shared\ValueObjects\Identifier;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RefundRequestDTOTest extends TestCase
{
    private function makeRequest(int $amount = 5000): RefundRequestDTO
    {
        return new RefundRequestDTO(
            transactionId: new Identifier('01HFAZ3NDEKTSV4RRFFQ69G5FA'),
            amountMinor: $amount,
            currency: Currency::INR,
            reason: 'Customer request',
            idempotencyKey: 'refund_1',
        );
    }

    public function testConstruction(): void
    {
        $r = $this->makeRequest();
        $this->assertSame(5000, $r->amountMinor());
        $this->assertSame(Currency::INR, $r->currency());
        $this->assertSame('Customer request', $r->reason());
        $this->assertTrue($r->hasReason());
        $this->assertTrue($r->hasIdempotencyKey());
    }

    public function testZeroAmount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->makeRequest(0);
    }

    public function testNegativeAmount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->makeRequest(-100);
    }

    public function testNoReasonAndNoKey(): void
    {
        $r = new RefundRequestDTO(
            transactionId: new Identifier('01HFAZ3NDEKTSV4RRFFQ69G5FA'),
            amountMinor: 100,
            currency: Currency::INR,
        );
        $this->assertNull($r->reason());
        $this->assertNull($r->idempotencyKey());
        $this->assertFalse($r->hasReason());
        $this->assertFalse($r->hasIdempotencyKey());
    }

    public function testCheckAgainstCapturedWithinBounds(): void
    {
        $r = $this->makeRequest(3000);
        $this->assertNull($r->checkAgainstCaptured(10000, 0));
        $this->assertNull($r->checkAgainstCaptured(10000, 7000));
    }

    public function testCheckAgainstCapturedExceeds(): void
    {
        $r = $this->makeRequest(5000);
        $err = $r->checkAgainstCaptured(10000, 7000);

        $this->assertInstanceOf(RefundExceededException::class, $err);
    }
}
