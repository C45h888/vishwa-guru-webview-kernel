<?php

declare(strict_types=1);

namespace App\Payments\Services;

use App\Persistence\Contracts\PersistenceAdapterContract;
use App\Persistence\ValueObjects\EntityId;
use App\Payments\Domain\Exceptions\PaymentVerificationFailedException;
use App\Shared\Support\Result;

/**
 * Single point of contact between services and the persistence adapter.
 *
 * Services MUST NOT call PersistenceAdapterContract directly. The
 * coordinator centralizes transaction boundaries and ensures that
 * every persistence write happens inside a managed transaction whose
 * commit/rollback semantics are observable to the orchestrator.
 *
 * In Pass 1.3 the coordinator exposes only the `execute(callable)`
 * primitive. The row-locking primitive (`withRowLock`) is deferred to
 * Pass 1.4 where the PostgresAdapter gains the `query()` method
 * needed to issue `SELECT ... FOR UPDATE` against the connection.
 * Until then, optimistic locking via the
 * `payments.version` column (incremented in Pass 1.4) is the
 * fallback for concurrency-sensitive paths.
 */
final class TransactionCoordinator
{
    public function __construct(
        private readonly PersistenceAdapterContract $adapter,
    ) {}

    /**
     * Run the callback inside a transaction. Commits on a successful
     * (non-throwing) return; rolls back on Throwable.
     *
     * The callback may return any type. Its return value is wrapped
     * into Result::success. If the callback throws, Result::failure
     * is returned carrying the exception's message; the transaction
     * has already been rolled back before this return.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @phpstan-return Result<T>|Result<null>
     * @return Result<T>
     */
    public function execute(callable $callback): Result
    {
        if (! $this->adapter->isConnected()) {
            $connect = $this->adapter->connect();
            if ($connect->isFailure()) {
                return Result::failure(
                    'persistence_unavailable: '.$connect->error(),
                );
            }
        }

        return $this->adapter->transaction($callback);
    }

    /**
     * Placeholder for the row-locking primitive. Deferred to Pass 1.4.
     *
     * Calling this method surfaces a programmer error in Pass 1.3
     * because the adapter contract does not yet expose a `query()`
     * method. Pass 1.4 will replace this stub with the actual
     * SELECT ... FOR UPDATE implementation backed by the adapter.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return Result<T>
     */
    public function withRowLock(EntityId $paymentId, callable $callback): Result
    {
        throw new PaymentVerificationFailedException(
            sprintf(
                'withRowLock is deferred to Pass 1.4 (paymentId=%s). '.
                'Use execute() in Pass 1.3; rely on optimistic locking '.
                'via payments.version once the column lands.',
                $paymentId->ulid(),
            ),
            $paymentId->ulid(),
            \App\Payments\Domain\Enums\TransactionStatus::PENDING,
            ['deferred' => 'Pass 1.4', 'method' => 'withRowLock'],
        );
    }

    /**
     * Check if the current transaction has been aborted by PostgreSQL.
     *
     * PostgreSQL marks a transaction as aborted when any statement fails,
     * even if the application doesn't detect the failure. This method
     * allows callers to check the transaction status explicitly.
     *
     * @return string 'active', 'aborted', or 'idle'
     */
    public function getTransactionStatus(): string
    {
        try {
            $result = $this->adapter->query('SELECT 1');
            return $result->isFailure() ? 'aborted' : 'active';
        } catch (\Throwable) {
            return 'aborted';
        }
    }
}