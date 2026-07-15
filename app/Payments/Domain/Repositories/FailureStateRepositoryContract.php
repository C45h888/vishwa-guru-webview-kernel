<?php

declare(strict_types=1);

namespace App\Payments\Domain\Repositories;

use App\Payments\Domain\Entities\FailureState;
use App\Payments\Domain\Enums\FailureClassification;
use App\Persistence\ValueObjects\EntityId;
use DateTimeImmutable;

/**
 * Persistence boundary for the FailureState aggregate.
 *
 * FailureStates are unique per payment_id (schema: failure_states_payment_unique).
 * Use findByPaymentId for the common lookup pattern; use the queue-style
 * queries (findDueForRetry, findUnresolvedTerminal) for cron workers.
 */
interface FailureStateRepositoryContract
{
    /**
     * Find a failure state by its internal identifier.
     */
    public function findById(EntityId $id): ?FailureState;

    /**
     * Find the failure state associated with a payment.
     * Returns null if the payment has no recorded failure.
     */
    public function findByPaymentId(EntityId $paymentId): ?FailureState;

    /**
     * Find all unresolved recoverable failures whose next_retry_at has passed.
     * Used by the retry worker.
     *
     * @return array<int, FailureState>
     */
    public function findDueForRetry(int $limit = 50): array;

    /**
     * Find all unresolved terminal failures.
     *
     * @return array<int, FailureState>
     */
    public function findUnresolvedTerminal(int $limit = 100): array;

    /**
     * Persist a new failure state (INSERT).
     */
    public function save(FailureState $failureState): void;

    /**
     * Apply entity-level changes and persist.
     */
    public function update(FailureState $failureState): void;

    /**
     * Mark a failure as resolved.
     */
    public function markResolved(
        EntityId $id,
        string $resolutionNotes,
        ?string $resolvedBy = null,
        ?DateTimeImmutable $resolvedAt = null,
    ): FailureState;

    /**
     * Increment retry counter and recompute next_retry_at.
     */
    public function incrementRetry(EntityId $id, ?DateTimeImmutable $nextRetryAt = null): FailureState;

    /**
     * Count failures by classification.
     */
    public function countByClassification(FailureClassification $classification): int;/**
     * Batch fetch failure states for many payments in a single query.
     *
     * @param  array<int, EntityId>  $paymentIds
     * @return array<string, FailureState>  Map of paymentId => FailureState
     */
    public function findManyByPaymentIds(array $paymentIds): array;

    /**
     * Diagnostic: count unresolved failure states whose last_failed_at
     * is older than the cutoff. Used by the observability dashboard
     * to alert on stuck failure recovery queues.
     */
    public function countUnresolvedOlderThan(DateTimeImmutable $cutoff): int;
}