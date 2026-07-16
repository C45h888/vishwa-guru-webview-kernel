<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Services;

use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Enums\PaymentProvider;
use App\Payments\Domain\ValueObjects\DonationIntent;
use App\Payments\Domain\ValueObjects\DonorIdentity;
use App\Payments\Domain\ValueObjects\PaymentResult;
use App\Payments\Domain\ValueObjects\WebhookPayload;
use App\Payments\Services\PaymentOrchestrator;
use App\Payments\Services\PaymentService;
use App\Shared\Support\FrozenClock;
use App\Shared\Support\Result;
use App\Shared\ValueObjects\Identifier;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PaymentService.
 *
 * PaymentService is a thin proxy over PaymentOrchestrator. These
 * tests assert the delegation contract holds — every method
 * forwards to the orchestrator with the same arguments.
 */
final class PaymentServiceTest extends TestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->clock = new FrozenClock(new DateTimeImmutable('2026-07-15T10:00:00Z'));
    }

    public function testInitializeDelegatesToOrchestrator(): void
    {
        $intent = $this->makeIntent();

        $expectedResult = Result::success($this->makePaymentResult());

        $orchestrator = $this->createMock(PaymentOrchestrator::class);
        $orchestrator->expects($this->once())
            ->method('initialize')
            ->with($intent)
            ->willReturn($expectedResult);

        $service = $this->makeService($orchestrator);

        $result = $service->initialize($intent);

        $this->assertTrue($result->isOk());
        $this->assertSame($expectedResult->value(), $result->value());
    }

    public function testHandleWebhookDelegatesToOrchestrator(): void
    {
        $payload = $this->makeWebhookPayload();

        $expectedResult = Result::success(null);

        $orchestrator = $this->createMock(PaymentOrchestrator::class);
        $orchestrator->expects($this->once())
            ->method('handleWebhook')
            ->with($payload)
            ->willReturn($expectedResult);

        $service = $this->makeService($orchestrator);

        $result = $service->handleWebhook($payload);

        $this->assertSame($expectedResult, $result);
    }

    public function testRefundDelegatesToOrchestrator(): void
    {
        $transactionId = new Identifier(\App\Shared\Support\UlidGenerator::generate());

        $expectedResult = Result::success(null);

        $orchestrator = $this->createMock(PaymentOrchestrator::class);
        $orchestrator->expects($this->once())
            ->method('refund')
            ->with($transactionId, 5000)
            ->willReturn($expectedResult);

        $service = $this->makeService($orchestrator);

        $result = $service->refund($transactionId, 5000);

        $this->assertSame($expectedResult, $result);
    }

    public function testGetStatusDelegatesToOrchestrator(): void
    {
        $transactionId = new Identifier(\App\Shared\Support\UlidGenerator::generate());

        $expectedResult = Result::success(\App\Payments\Domain\Enums\TransactionStatus::CAPTURED);

        $orchestrator = $this->createMock(PaymentOrchestrator::class);
        $orchestrator->expects($this->once())
            ->method('getStatus')
            ->with($transactionId)
            ->willReturn($expectedResult);

        $service = $this->makeService($orchestrator);

        $result = $service->getStatus($transactionId);

        $this->assertSame($expectedResult, $result);
    }

    public function testServiceExposesConstructorDependenciesForInspection(): void
    {
        // The service MUST carry the orchestrator dependency so the
        // container can resolve it. This guards against a future
        // refactor that accidentally drops the constructor parameter.
        $orchestrator = $this->createMock(PaymentOrchestrator::class);

        $service = new PaymentService(
            orchestrator: $orchestrator,
        );

        $this->assertInstanceOf(PaymentService::class, $service);
    }

    private function makeService(\PHPUnit\Framework\MockObject\MockObject $orchestrator): PaymentService
    {
        return new PaymentService(
            orchestrator: $orchestrator,
        );
    }

    private function makeIntent(): DonationIntent
    {
        return new DonationIntent(
            campaignId: new Identifier(\App\Shared\Support\UlidGenerator::generate()),
            donor: DonorIdentity::anonymous(),
            amountMinor: 50000,
            currency: Currency::INR,
            idempotencyKey: 'idem_'.bin2hex(random_bytes(4)),
        );
    }

    private function makeWebhookPayload(): WebhookPayload
    {
        return new WebhookPayload(
            provider: PaymentProvider::RAZORPAY,
            headers: ['X-Razorpay-Signature' => 'sig'],
            rawBody: '{}',
            receivedAt: $this->clock->now(),
            providerEventId: 'evt_'.bin2hex(random_bytes(4)),
        );
    }

    private function makePaymentResult(): PaymentResult
    {
        return new PaymentResult(
            provider: PaymentProvider::RAZORPAY,
            gatewayOrderId: 'order_abc',
            amountMinor: 50000,
            currency: Currency::INR,
            status: \App\Payments\Domain\Enums\TransactionStatus::INITIALIZED,
        );
    }
}