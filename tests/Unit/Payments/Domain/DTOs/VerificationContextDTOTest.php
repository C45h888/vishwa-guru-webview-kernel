<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Domain\DTOs;

use App\Payments\Domain\DTOs\VerificationContextDTO;
use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Enums\PaymentProvider;
use App\Payments\Domain\ValueObjects\WebhookPayload;
use App\Shared\ValueObjects\Identifier;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class VerificationContextDTOTest extends TestCase
{
    private function makePayload(): WebhookPayload
    {
        return new WebhookPayload(
            provider: PaymentProvider::RAZORPAY,
            headers: ['x-razorpay-signature' => 'sig_abc'],
            rawBody: '{"event":"payment.captured"}',
            receivedAt: new DateTimeImmutable(),
            providerEventId: 'evt_ABC',
        );
    }

    private function makeContext(?WebhookPayload $payload = null): VerificationContextDTO
    {
        return new VerificationContextDTO(
            payload: $payload ?? $this->makePayload(),
            donationId: new Identifier('01HXYZ1DONATION00000000000000'),
            paymentId: new Identifier('01HXYZ1PAYMENT000000000000000'),
            expectedAmountMinor: 5000,
            expectedCurrency: Currency::INR,
            expectedIdempotencyKey: 'idem_test_1',
        );
    }

    public function testConstruction(): void
    {
        $ctx = $this->makeContext();
        $this->assertSame(5000, $ctx->expectedAmountMinor());
        $this->assertSame(Currency::INR, $ctx->expectedCurrency());
        $this->assertSame('idem_test_1', $ctx->expectedIdempotencyKey());
        $this->assertSame([], $ctx->stageResults());
        $this->assertSame([], $ctx->metadata());
    }

    public function testWithStageResult(): void
    {
        $ctx = $this->makeContext();
        $next = $ctx->withStageResult(VerificationContextDTO::STAGE_SIGNATURE, [
            'passed' => true,
            'verified_at' => '2026-01-01T00:00:00Z',
        ]);

        $this->assertNotSame($ctx, $next);
        $this->assertTrue($next->stagePassed(VerificationContextDTO::STAGE_SIGNATURE));
        $this->assertFalse($ctx->stagePassed(VerificationContextDTO::STAGE_SIGNATURE));
    }

    public function testStagePassed(): void
    {
        $ctx = $this->makeContext()->withStageResult('signature', ['passed' => false]);
        $this->assertFalse($ctx->stagePassed('signature'));
    }

    public function testWithMetadata(): void
    {
        $ctx = $this->makeContext();
        $next = $ctx->withMetadata(['request_id' => 'req_123']);

        $this->assertSame(['request_id' => 'req_123'], $next->metadata());
        $this->assertSame([], $ctx->metadata());
    }

    public function testZeroAmount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new VerificationContextDTO(
            payload: $this->makePayload(),
            donationId: new Identifier('01HXYZ1DONATION00000000000000'),
            paymentId: new Identifier('01HXYZ1PAYMENT000000000000000'),
            expectedAmountMinor: 0,
            expectedCurrency: Currency::INR,
            expectedIdempotencyKey: 'idem',
        );
    }

    public function testEmptyKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new VerificationContextDTO(
            payload: $this->makePayload(),
            donationId: new Identifier('01HXYZ1DONATION00000000000000'),
            paymentId: new Identifier('01HXYZ1PAYMENT000000000000000'),
            expectedAmountMinor: 1000,
            expectedCurrency: Currency::INR,
            expectedIdempotencyKey: '',
        );
    }

    public function testStageResultUnknown(): void
    {
        $ctx = $this->makeContext();
        $this->assertNull($ctx->stageResult('nonexistent'));
    }
}
