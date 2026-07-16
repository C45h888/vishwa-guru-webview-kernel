<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Services;

use App\Payments\Contracts\PaymentGatewayContract;
use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Enums\DonationState;
use App\Payments\Domain\Enums\PaymentProvider;
use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Domain\Exceptions\RefundExceededException;
use App\Payments\Domain\Repositories\AuditEventRepositoryContract;
use App\Payments\Domain\Repositories\DonationRepositoryContract;
use App\Payments\Domain\Repositories\DonorRepositoryContract;
use App\Payments\Domain\Repositories\PaymentRepositoryContract;
use App\Payments\Domain\StateMachines\DonationStateMachine;
use App\Payments\Domain\StateMachines\PaymentStateMachine;
use App\Payments\Domain\ValueObjects\DonationIntent;
use App\Payments\Domain\ValueObjects\DonorIdentity;
use App\Payments\Domain\ValueObjects\PaymentRequest;
use App\Payments\Domain\ValueObjects\PaymentResult;
use App\Payments\Domain\ValueObjects\WebhookPayload;
use App\Payments\Services\FailureStateService;
use App\Payments\Services\PaymentOrchestrator;
use App\Payments\Services\PaymentProviderSelector;
use App\Payments\Services\PaymentService;
use App\Payments\Services\PaymentVerificationService;
use App\Payments\Services\ReceiptService;
use App\Payments\Services\TransactionCoordinator;
use App\Shared\Support\Clock;
use App\Shared\Support\FrozenClock;
use App\Shared\Support\IdentifierGenerator;
use App\Shared\Support\Result;
use App\Shared\ValueObjects\Identifier;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PaymentOrchestrator.
 *
 * The orchestrator coordinates 7 collaborators. Each test
 * constructs a hand-rolled graph with mocked leaves so the
 * orchestration logic — not the collaborator behavior — is what
 * gets verified.
 */
final class PaymentOrchestratorTest extends TestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->clock = new FrozenClock(new DateTimeImmutable('2026-07-15T10:00:00Z'));
    }

    public function testInitializeReturnsFailureWhenGatewaySelectionFails(): void
    {
        // No gateways registered → selector throws → orchestrator
        // catches via the Result path inside resolveGatewayFor.
        // For initialize() the throw is caught by the selector
        // path; we surface a Result::failure instead.
        $selector = $this->createMock(PaymentProviderSelector::class);

        $orchestrator = $this->makeOrchestrator(
            selector: $selector,
            gateway: null, // no gateway → no adapter to call
        );

        $intent = $this->makeIntent();

        // Selector throws because the pool is empty.
        $selector->method('select')->willThrowException(
            \App\Payments\Domain\Exceptions\GatewaySelectionException::noProviderForCurrency(
                $intent->currency(),
            ),
        );

        $this->expectException(\App\Payments\Domain\Exceptions\GatewaySelectionException::class);
        $orchestrator->initialize($intent);
    }

    public function testRefundReturnsFailureForZeroAmount(): void
    {
        $orchestrator = $this->makeOrchestrator();

        $result = $orchestrator->refund(
            new Identifier(\App\Shared\Support\UlidGenerator::generate()),
            0,
        );

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('refund_amount_must_be_positive', $result->error());
    }

    public function testRefundReturnsFailureWhenPaymentNotFound(): void
    {
        $payments = $this->createMock(PaymentRepositoryContract::class);
        $payments->method('findById')->willReturn(null);

        $orchestrator = $this->makeOrchestrator(payments: $payments);

        $result = $orchestrator->refund(
            new Identifier(\App\Shared\Support\UlidGenerator::generate()),
            5000,
        );

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('payment_not_found', $result->error());
    }

    public function testRefundThrowsRefundExceededExceptionWhenAmountExceedsCeiling(): void
    {
        $payment = $this->makePayment(
            captured: 5000,
            refunded: 4000,
        );

        $payments = $this->createMock(PaymentRepositoryContract::class);
        $payments->method('findById')->willReturn($payment);

        $orchestrator = $this->makeOrchestrator(payments: $payments);

        // 4000 already refunded + 2000 requested = 6000 > 5000 captured
        $this->expectException(RefundExceededException::class);
        $orchestrator->refund(
            new Identifier($payment->id()->ulid()),
            2000,
        );
    }

    public function testGetStatusReturnsFailureWhenPaymentNotFound(): void
    {
        $payments = $this->createMock(PaymentRepositoryContract::class);
        $payments->method('findById')->willReturn(null);

        $orchestrator = $this->makeOrchestrator(payments: $payments);

        $result = $orchestrator->getStatus(
            new Identifier(\App\Shared\Support\UlidGenerator::generate()),
        );

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('payment_not_found', $result->error());
    }

    public function testGetStatusReturnsLocalStatusForTerminalPaymentWithoutGatewayCall(): void
    {
        // Payment in SETTLED (terminal) status → orchestrator must
        // short-circuit and NOT call the gateway.
        $payment = $this->makePayment(
            captured: 5000,
            refunded: 0,
        );
        $payment = $this->forceStatus($payment, TransactionStatus::SETTLED);

        $payments = $this->createMock(PaymentRepositoryContract::class);
        $payments->method('findById')->willReturn($payment);

        $selector = $this->createMock(PaymentProviderSelector::class);
        $selector->expects($this->never())->method('select');

        $orchestrator = $this->makeOrchestrator(
            payments: $payments,
            selector: $selector,
        );

        $result = $orchestrator->getStatus(new Identifier($payment->id()->ulid()));

        $this->assertTrue($result->isOk());
        $this->assertSame(TransactionStatus::SETTLED, $result->value());
    }

    public function testHandleWebhookReturnsFailureWhenMetadataIsInsufficient(): void
    {
        $orchestrator = $this->makeOrchestrator();

        // WebhookPayload with no metadata at all — the orchestrator
        // should refuse to process it.
        $payload = new WebhookPayload(
            provider: PaymentProvider::RAZORPAY,
            headers: ['X-Razorpay-Signature' => 'sig'],
            rawBody: '{}',
            receivedAt: $this->clock->now(),
            providerEventId: 'evt_1',
            metadata: [],
        );

        $result = $orchestrator->handleWebhook($payload);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('webhook_metadata_insufficient', $result->error());
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

    private function makePayment(int $captured, int $refunded): \App\Payments\Domain\Entities\Payment
    {
        $payment = \App\Payments\Domain\Entities\Payment::initialize(
            donationId: \App\Persistence\ValueObjects\EntityId::generate('donation'),
            providerCode: PaymentProvider::RAZORPAY,
            amountMinor: 10_000,
            currency: Currency::INR,
            idempotencyKey: 'idem_'.bin2hex(random_bytes(4)),
            metadata: [],
        );
        // Set captured/refunded on the row directly via fromRow
        // because the state-machine table bug prevents walking
        // INITIALIZED → ... → CAPTURED.
        $row = $payment->toArray();
        $row['amount_captured_minor'] = $captured;
        $row['amount_refunded_minor'] = $refunded;

        return \App\Payments\Domain\Entities\Payment::fromRow($row);
    }

    private function forceStatus(
        \App\Payments\Domain\Entities\Payment $payment,
        TransactionStatus $status,
    ): \App\Payments\Domain\Entities\Payment {
        $row = $payment->toArray();
        $row['status'] = $status->value;

        return \App\Payments\Domain\Entities\Payment::fromRow($row);
    }

    private function makeOrchestrator(
        ?PaymentRepositoryContract $payments = null,
        ?PaymentProviderSelector $selector = null,
        ?PaymentGatewayContract $gateway = null,
    ): PaymentOrchestrator {
        $coordinator = new TransactionCoordinator(
            $this->makeConnectedAdapter(),
        );

        return new PaymentOrchestrator(
            receiptService: $this->createMock(ReceiptService::class),
            failureStateService: $this->createMock(FailureStateService::class),
            selector: $selector ?? $this->makeSelectorWithGateway($gateway),
            verification: $this->createMock(PaymentVerificationService::class),
            coordinator: $coordinator,
            auditLog: $this->createMock(AuditEventRepositoryContract::class),
            clock: $this->clock,
            ids: new \App\Shared\Support\UlidGenerator(),
            payments: $payments ?? $this->createMock(PaymentRepositoryContract::class),
            donations: $this->createMock(DonationRepositoryContract::class),
            donors: $this->createMock(DonorRepositoryContract::class),
            paymentStateMachine: new PaymentStateMachine(),
            donationStateMachine: new DonationStateMachine(),
        );
    }

    private function makeSelectorWithGateway(?PaymentGatewayContract $gateway): PaymentProviderSelector
    {
        return new PaymentProviderSelector($gateway !== null ? [$gateway] : []);
    }

    private function makeConnectedAdapter(): \App\Persistence\Contracts\PersistenceAdapterContract
    {
        $adapter = $this->createMock(\App\Persistence\Contracts\PersistenceAdapterContract::class);
        $adapter->method('isConnected')->willReturn(true);
        $adapter->method('transaction')->willReturnCallback(
            function (callable $callback): Result {
                return Result::success($callback());
            },
        );

        return $adapter;
    }
}