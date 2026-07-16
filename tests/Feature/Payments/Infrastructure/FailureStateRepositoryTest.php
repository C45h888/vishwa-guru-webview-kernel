<?php

declare(strict_types=1);

namespace Tests\Feature\Payments\Infrastructure;

use App\Payments\Domain\Entities\FailureState;
use App\Payments\Domain\Enums\FailureClassification;
use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Infrastructure\Repositories\FailureStateRepository;
use App\Persistence\ValueObjects\EntityId;
use DateTimeImmutable;
use RuntimeException;

/**
 * @covers FailureStateRepository
 * @covers FailureState
 */
final class FailureStateRepositoryTest extends InfrastructureTestCase
{
    private FailureStateRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new FailureStateRepository($this->adapter);
    }

    public function testSaveFindByIdRoundTrip(): void
    {
        $paymentId = EntityId::generate('payment');

        $failure = FailureState::record(
            paymentId: $paymentId,
            classification: FailureClassification::RECOVERABLE_TRANSIENT,
            failureCode: 'TIMEOUT',
            finalStatus: TransactionStatus::FAILED,
            providerCode: 'razorpay',
            gatewayOrderId: 'order_abc123',
            correlationId: 'corr_001',
            failureReason: 'Gateway timed out',
        );

        $this->repo->save($failure);

        $found = $this->repo->findById($failure->id());
        $this->assertNotNull($found);
        $this->assertSame($failure->id()->value(), $found->id()->value());
        $this->assertSame($paymentId->value(), $found->paymentId()->value());
        $this->assertSame('TIMEOUT', $found->failureCode());
        $this->assertSame(FailureClassification::RECOVERABLE_TRANSIENT->value, $found->classification()->value);
        $this->assertFalse($found->isResolved());
        $this->assertSame(0, $found->retryCount());
    }

    public function testFindByPaymentId(): void
    {
        $paymentId = EntityId::generate('payment');

        $failure = FailureState::record(
            paymentId: $paymentId,
            classification: FailureClassification::RECOVERABLE_TERMINAL,
            failureCode: 'GATEWAY_ERROR',
            finalStatus: TransactionStatus::FAILED,
            providerCode: 'stripe',
            gatewayOrderId: 'order_stripe_xyz',
            correlationId: 'corr_002',
        );

        $this->repo->save($failure);

        $found = $this->repo->findByPaymentId($paymentId);
        $this->assertNotNull($found);
        $this->assertSame($failure->id()->value(), $found->id()->value());
    }

    public function testFindByPaymentIdReturnsNullWhenNotFound(): void
    {
        $fakePaymentId = EntityId::generate('payment');
        $found = $this->repo->findByPaymentId($fakePaymentId);
        $this->assertNull($found);
    }

    public function testMarkResolvedSetsResolvedAt(): void
    {
        $paymentId = EntityId::generate('payment');

        $failure = FailureState::record(
            paymentId: $paymentId,
            classification: FailureClassification::RECOVERABLE_TRANSIENT,
            failureCode: 'NETWORK_ERROR',
            finalStatus: TransactionStatus::FAILED,
            providerCode: 'razorpay',
            gatewayOrderId: 'order_net_err',
            correlationId: 'corr_003',
        );

        $this->repo->save($failure);

        $resolved = $this->repo->markResolved($failure->id(), 'Operator resolved manually');
        $this->assertTrue($resolved->isResolved());
        $this->assertNotNull($resolved->resolvedAt());
        $this->assertSame('Operator resolved manually', $resolved->resolutionNotes());
    }

    public function testMarkResolvedThrowsWhenNotFound(): void
    {
        $this->expectException(RuntimeException::class);
        $fakeId = EntityId::generate('failure_state');
        $this->repo->markResolved($fakeId, 'Should fail');
    }

    public function testIncrementRetryIncreasesRetryCount(): void
    {
        $paymentId = EntityId::generate('payment');

        $failure = FailureState::record(
            paymentId: $paymentId,
            classification: FailureClassification::RECOVERABLE_TRANSIENT,
            failureCode: 'BANK_DECLINED',
            finalStatus: TransactionStatus::FAILED,
            providerCode: 'razorpay',
            gatewayOrderId: 'order_bank_dec',
            correlationId: 'corr_004',
        );

        $this->repo->save($failure);
        $this->assertSame(0, $failure->retryCount());

        $after1 = $this->repo->incrementRetry($failure->id());
        $this->assertSame(1, $after1->retryCount());

        $after2 = $this->repo->incrementRetry($failure->id());
        $this->assertSame(2, $after2->retryCount());
    }

    public function testIncrementRetrySetsNextRetryAt(): void
    {
        $paymentId = EntityId::generate('payment');

        $failure = FailureState::record(
            paymentId: $paymentId,
            classification: FailureClassification::RECOVERABLE_TRANSIENT,
            failureCode: 'LOCK_TIMEOUT',
            finalStatus: TransactionStatus::FAILED,
            providerCode: 'stripe',
            gatewayOrderId: 'order_lock',
            correlationId: 'corr_005',
        );

        $this->repo->save($failure);

        $incremented = $this->repo->incrementRetry($failure->id());
        $this->assertNotNull($incremented->nextRetryAt());
        // Default backoff for RECOVERABLE_TRANSIENT is 60 seconds
        $expectedBackoff = $failure->lastFailedAt()->modify('+60 seconds');
        $this->assertEquals(
            $expectedBackoff->format(DATE_ATOM),
            $incremented->nextRetryAt()->format(DATE_ATOM),
        );
    }

    public function testFindDueForRetry(): void
    {
        $paymentId1 = EntityId::generate('payment');
        $paymentId2 = EntityId::generate('payment');

        // Due for retry (next_retry_at in the past)
        $failure1 = FailureState::record(
            paymentId: $paymentId1,
            classification: FailureClassification::RECOVERABLE_TRANSIENT,
            failureCode: 'TIMEOUT',
            finalStatus: TransactionStatus::FAILED,
            providerCode: 'razorpay',
            gatewayOrderId: 'order_due1',
            correlationId: 'corr_due1',
        );
        $this->repo->save($failure1);

        // Not due (next_retry_at in the future)
        $failure2 = FailureState::record(
            paymentId: $paymentId2,
            classification: FailureClassification::RECOVERABLE_TRANSIENT,
            failureCode: 'TIMEOUT',
            finalStatus: TransactionStatus::FAILED,
            providerCode: 'razorpay',
            gatewayOrderId: 'order_notdue2',
            correlationId: 'corr_notdue2',
            nextRetryAt: (new DateTimeImmutable())->modify('+1 hour'),
        );
        $this->repo->save($failure2);

        $due = $this->repo->findDueForRetry(10);
        $this->assertCount(1, $due);
        $this->assertSame($paymentId1->value(), $due[0]->paymentId()->value());
    }

    public function testCountByClassification(): void
    {
        $paymentId1 = EntityId::generate('payment');
        $paymentId2 = EntityId::generate('payment');

        $f1 = FailureState::record(
            paymentId: $paymentId1,
            classification: FailureClassification::RECOVERABLE_TRANSIENT,
            failureCode: 'ERR_A',
            finalStatus: TransactionStatus::FAILED,
            providerCode: 'razorpay',
            gatewayOrderId: 'order_clsa',
            correlationId: 'corr_clsa',
        );
        $this->repo->save($f1);

        $f2 = FailureState::record(
            paymentId: $paymentId2,
            classification: FailureClassification::RECOVERABLE_TRANSIENT,
            failureCode: 'ERR_B',
            finalStatus: TransactionStatus::FAILED,
            providerCode: 'razorpay',
            gatewayOrderId: 'order_clsb',
            correlationId: 'corr_clsb',
        );
        $this->repo->save($f2);

        $transientCount = $this->repo->countByClassification(FailureClassification::RECOVERABLE_TRANSIENT);
        $this->assertSame(2, $transientCount);

        $terminalCount = $this->repo->countByClassification(FailureClassification::TERMINAL_INVALID);
        $this->assertSame(0, $terminalCount);
    }
}
