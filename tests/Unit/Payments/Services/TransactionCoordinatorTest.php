<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Services;

use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Domain\Exceptions\PaymentVerificationFailedException;
use App\Payments\Services\TransactionCoordinator;
use App\Persistence\Contracts\PersistenceAdapterContract;
use App\Persistence\ValueObjects\EntityId;
use App\Shared\Support\Result;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for TransactionCoordinator.
 *
 * Coverage:
 *   - execute() delegates to adapter.transaction() when connected
 *   - execute() auto-connects when adapter is disconnected
 *   - execute() wraps a connection failure as a Result::failure
 *   - execute() propagates the callback's return value as
 *     Result::success
 *   - withRowLock() throws because the primitive is deferred to
 *     Pass 1.4 (documented behavior)
 */
final class TransactionCoordinatorTest extends TestCase
{
    public function testExecuteDelegatesToAdapterTransactionWhenConnected(): void
    {
        $adapter = $this->createMock(PersistenceAdapterContract::class);
        $adapter->method('isConnected')->willReturn(true);
        $adapter->expects($this->once())
            ->method('transaction')
            ->with($this->isType('callable'))
            ->willReturnCallback(function (callable $callback): Result {
                $value = $callback();

                return Result::success($value);
            });

        $coordinator = new TransactionCoordinator($adapter);

        $result = $coordinator->execute(static fn (): string => 'committed');

        $this->assertTrue($result->isOk());
        $this->assertSame('committed', $result->value());
    }

    public function testExecuteAutoConnectsWhenAdapterIsDisconnected(): void
    {
        $adapter = $this->createMock(PersistenceAdapterContract::class);
        $adapter->method('isConnected')->willReturn(false);
        $adapter->expects($this->once())
            ->method('connect')
            ->willReturn(Result::success(null));
        $adapter->expects($this->once())
            ->method('transaction')
            ->willReturnCallback(function (callable $callback): Result {
                return Result::success($callback());
            });

        $coordinator = new TransactionCoordinator($adapter);

        $result = $coordinator->execute(static fn (): int => 42);

        $this->assertTrue($result->isOk());
        $this->assertSame(42, $result->value());
    }

    public function testExecuteReturnsFailureWhenConnectFails(): void
    {
        $adapter = $this->createMock(PersistenceAdapterContract::class);
        $adapter->method('isConnected')->willReturn(false);
        $adapter->expects($this->once())
            ->method('connect')
            ->willReturn(Result::failure('connection_refused'));
        $adapter->expects($this->never())
            ->method('transaction');

        $coordinator = new TransactionCoordinator($adapter);

        $result = $coordinator->execute(static fn (): string => 'never-runs');

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('persistence_unavailable', $result->error());
        $this->assertStringContainsString('connection_refused', $result->error());
    }

    public function testExecutePropagatesAdapterFailureResult(): void
    {
        $adapter = $this->createMock(PersistenceAdapterContract::class);
        $adapter->method('isConnected')->willReturn(true);
        $adapter->method('transaction')
            ->willReturn(Result::failure('constraint_violation'));

        $coordinator = new TransactionCoordinator($adapter);

        $result = $coordinator->execute(static fn (): string => 'never-runs');

        $this->assertTrue($result->isFailure());
        $this->assertSame('constraint_violation', $result->error());
    }

    public function testExecuteCarriesComplexReturnTypes(): void
    {
        $adapter = $this->createMock(PersistenceAdapterContract::class);
        $adapter->method('isConnected')->willReturn(true);
        $adapter->method('transaction')
            ->willReturnCallback(function (callable $callback): Result {
                return Result::success($callback());
            });

        $coordinator = new TransactionCoordinator($adapter);

        // Returning a TransactionStatus enum from the closure
        // exercises the generic <T> contract of execute().
        $result = $coordinator->execute(
            static fn (): TransactionStatus => TransactionStatus::CAPTURED,
        );

        $this->assertTrue($result->isOk());
        $this->assertSame(TransactionStatus::CAPTURED, $result->value());
    }

    public function testWithRowLockThrowsBecauseItIsDeferredToPass1Point4(): void
    {
        $adapter = $this->createMock(PersistenceAdapterContract::class);

        $coordinator = new TransactionCoordinator($adapter);

        $this->expectException(PaymentVerificationFailedException::class);
        $this->expectExceptionMessage('withRowLock is deferred to Pass 1.4');

        $coordinator->withRowLock(
            EntityId::generate('payment'),
            static fn (): string => 'never-runs',
        );
    }
}