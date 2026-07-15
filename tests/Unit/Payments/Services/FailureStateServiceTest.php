<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Services;

use App\Payments\Domain\Enums\FailureClassification;
use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Domain\Exceptions\FailureClassificationException;
use App\Payments\Domain\Repositories\AuditEventRepositoryContract;
use App\Payments\Domain\Repositories\FailureStateRepositoryContract;
use App\Payments\Domain\Repositories\PaymentRepositoryContract;
use App\Payments\Domain\StateMachines\PaymentStateMachine;
use App\Payments\Services\FailureStateService;
use App\Persistence\ValueObjects\EntityId;
use App\Shared\Support\Clock;
use App\Shared\Support\FrozenClock;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for FailureStateService.
 *
 * Coverage:
 *   - classify() table-driven mapping for known failure codes
 *   - classify() rejects unknown provider codes (programming error)
 *   - record() persists a FailureState row + audit event
 *   - markResolved() rejects already-resolved failures
 */
final class FailureStateServiceTest extends TestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->clock = new FrozenClock(new DateTimeImmutable('2026-07-15T10:00:00Z'));
    }

    public function testClassifyMapsTerminalInvalidForInvalidSignature(): void
    {
        $service = $this->makeService();

        $this->assertSame(
            FailureClassification::TERMINAL_INVALID,
            $service->classify('razorpay', 'order_x', 'failed', 'invalid_signature'),
        );
        $this->assertSame(
            FailureClassification::TERMINAL_INVALID,
            $service->classify('razorpay', 'order_x', 'failed', 'bad_signature'),
        );
    }

    public function testClassifyMapsTerminalFraudForFraudSignals(): void
    {
        $service = $this->makeService();

        $this->assertSame(
            FailureClassification::TERMINAL_FRAUD,
            $service->classify('paypal', 'order_y', 'failed', 'fraud_detected'),
        );
        $this->assertSame(
            FailureClassification::TERMINAL_FRAUD,
            $service->classify('paypal', 'order_y', 'failed', 'risk_threshold_breached'),
        );
    }

    public function testClassifyMapsRecoverableTransientForNetworkFailures(): void
    {
        $service = $this->makeService();

        $this->assertSame(
            FailureClassification::RECOVERABLE_TRANSIENT,
            $service->classify('razorpay', 'order_z', 'timeout', 'gateway_timeout'),
        );
        $this->assertSame(
            FailureClassification::RECOVERABLE_TRANSIENT,
            $service->classify('razorpay', 'order_z', 'unavailable', 'gateway_unavailable'),
        );
        $this->assertSame(
            FailureClassification::RECOVERABLE_TRANSIENT,
            $service->classify('razorpay', 'order_z', 'declined', 'insufficient_funds'),
        );
    }

    public function testClassifyDefaultsToRecoverableTerminalForUnknownCodes(): void
    {
        $service = $this->makeService();

        $this->assertSame(
            FailureClassification::RECOVERABLE_TERMINAL,
            $service->classify('razorpay', 'order_q', 'unknown', 'something_we_dont_know'),
        );
    }

    public function testClassifyThrowsForUnknownProviderCode(): void
    {
        $service = $this->makeService();

        $this->expectException(FailureClassificationException::class);
        $service->classify('stripe', 'order_x', 'failed', 'invalid_signature');
    }

    public function testRecordPersistsFailureStateAndAuditEvent(): void
    {
        $paymentId = EntityId::generate('payment');

        $failureStates = $this->createMock(FailureStateRepositoryContract::class);
        $failureStates->expects($this->once())
            ->method('save')
            ->with($this->callback(function ($failure) use ($paymentId): bool {
                return $failure->paymentId()->ulid() === $paymentId->ulid()
                    && $failure->classification() === FailureClassification::TERMINAL_INVALID
                    && $failure->failureCode() === 'invalid_signature'
                    && $failure->providerCode() === 'razorpay';
            }));

        $audit = $this->createMock(AuditEventRepositoryContract::class);
        $audit->expects($this->once())
            ->method('append')
            ->with(
                $this->equalTo('payment.failure.recorded'),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->isNull(),
                $this->equalTo('terminal_invalid'),
                $this->callback(function (array $context): bool {
                    return ($context['failure_code'] ?? null) === 'invalid_signature'
                        && ($context['classification'] ?? null) === 'terminal_invalid'
                        && ($context['is_retryable'] ?? null) === false;
                }),
                $this->anything(),
            );

        $service = new FailureStateService(
            failureStates: $failureStates,
            payments: $this->createMock(PaymentRepositoryContract::class),
            auditLog: $audit,
            paymentStateMachine: new PaymentStateMachine(),
            clock: $this->clock,
        );

        $failure = $service->record(
            paymentId: $paymentId,
            providerCode: 'razorpay',
            gatewayOrderId: 'order_x',
            observedStatus: TransactionStatus::FAILED,
            failureCode: 'invalid_signature',
            failureReason: 'sig mismatch',
        );

        $this->assertSame(FailureClassification::TERMINAL_INVALID, $failure->classification());
        $this->assertSame('invalid_signature', $failure->failureCode());
    }

    public function testMarkResolvedRejectsAlreadyResolvedFailures(): void
    {
        // Construct a real FailureState via record() factory to
        // avoid mocking the final class. The markResolved path
        // checks resolvedAt !== null.
        $paymentId = EntityId::generate('payment');
        $failure = \App\Payments\Domain\Entities\FailureState::record(
            paymentId: $paymentId,
            classification: FailureClassification::RECOVERABLE_TERMINAL,
            failureCode: 'unknown',
            finalStatus: TransactionStatus::FAILED,
            providerCode: 'razorpay',
            gatewayOrderId: 'order_z',
            correlationId: 'corr_1',
        );

        // Force the failure into the resolved state by passing an
        // updated copy with a non-null resolvedAt. The factory
        // doesn't expose this directly; we use withChanges on the
        // copy that already includes a manually-set timestamp.
        // For the test we just need resolvedAt !== null; we set it
        // by constructing a new instance with fromRow.
        $row = $failure->toArray();
        $row['resolved_at'] = '2026-07-15T10:00:00+00:00';
        $row['resolved_by'] = 'test';
        $row['resolution_notes'] = 'pre-resolved';
        $resolved = \App\Payments\Domain\Entities\FailureState::fromRow($row);

        $failureStates = $this->createMock(FailureStateRepositoryContract::class);
        $failureStates->expects($this->never())->method('markResolved');

        $audit = $this->createMock(AuditEventRepositoryContract::class);
        $audit->expects($this->never())->method('append');

        $service = new FailureStateService(
            failureStates: $failureStates,
            payments: $this->createMock(PaymentRepositoryContract::class),
            auditLog: $audit,
            paymentStateMachine: new PaymentStateMachine(),
            clock: $this->clock,
        );

        $result = $service->markResolved(
            failure: $resolved,
            notes: 'trying to resolve again',
        );

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('failure_already_resolved', $result->error());
    }

    public function testMarkResolvedPersistsAndAuditsOnFirstCall(): void
    {
        $paymentId = EntityId::generate('payment');
        $failure = \App\Payments\Domain\Entities\FailureState::record(
            paymentId: $paymentId,
            classification: FailureClassification::RECOVERABLE_TERMINAL,
            failureCode: 'unknown',
            finalStatus: TransactionStatus::FAILED,
            providerCode: 'razorpay',
            gatewayOrderId: 'order_a',
            correlationId: 'corr_2',
        );

        $resolvedCopy = \App\Payments\Domain\Entities\FailureState::fromRow(
            $failure->toArray() + [
                'resolved_at' => '2026-07-15T10:00:00+00:00',
                'resolved_by' => 'system:retry',
                'resolution_notes' => 'Auto-resolved by retry',
            ],
        );

        $failureStates = $this->createMock(FailureStateRepositoryContract::class);
        $failureStates->expects($this->once())
            ->method('markResolved')
            ->willReturn($resolvedCopy);

        $audit = $this->createMock(AuditEventRepositoryContract::class);
        $audit->expects($this->once())->method('append');

        $service = new FailureStateService(
            failureStates: $failureStates,
            payments: $this->createMock(PaymentRepositoryContract::class),
            auditLog: $audit,
            paymentStateMachine: new PaymentStateMachine(),
            clock: $this->clock,
        );

        $result = $service->markResolved(
            failure: $failure,
            notes: 'operator intervention',
            resolvedBy: 'operator:1',
        );

        $this->assertTrue($result->isOk());
        $this->assertSame($resolvedCopy, $result->value());
    }

    private function makeService(): FailureStateService
    {
        return new FailureStateService(
            failureStates: $this->createMock(FailureStateRepositoryContract::class),
            payments: $this->createMock(PaymentRepositoryContract::class),
            auditLog: $this->createMock(AuditEventRepositoryContract::class),
            paymentStateMachine: new PaymentStateMachine(),
            clock: $this->clock,
        );
    }
}