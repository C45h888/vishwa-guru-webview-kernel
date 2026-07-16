<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Repositories;

use App\Payments\Domain\Entities\FailureState;
use App\Payments\Domain\Enums\FailureClassification;
use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Domain\Repositories\FailureStateRepositoryContract;
use App\Persistence\Contracts\PersistenceAdapterContract;
use App\Persistence\ValueObjects\EntityId;
use DateTimeImmutable;
use RuntimeException;

final class FailureStateRepository implements FailureStateRepositoryContract
{
    public function __construct(
        private readonly PersistenceAdapterContract $adapter,
    ) {}

    public function findById(EntityId $id): ?FailureState
    {
        $result = $this->adapter->query(
            'SELECT * FROM failure_states WHERE id = :id',
            ['id' => $id->value()],
        );
        if ($result->isFailure() || empty($result->value())) {
            return null;
        }

        return FailureState::fromRow($result->value()[0]);
    }

    public function findByPaymentId(EntityId $paymentId): ?FailureState
    {
        $result = $this->adapter->query(
            'SELECT * FROM failure_states WHERE payment_id = :pid LIMIT 1',
            ['pid' => $paymentId->value()],
        );
        if ($result->isFailure() || empty($result->value())) {
            return null;
        }

        return FailureState::fromRow($result->value()[0]);
    }

    /**
     * @return array<int, FailureState>
     */
    public function findDueForRetry(int $limit = 50): array
    {
        $now = (new DateTimeImmutable())->format(DATE_ATOM);

        $result = $this->adapter->query(
            "SELECT * FROM failure_states
             WHERE classification = 'recoverable'
               AND resolved_at IS NULL
               AND next_retry_at IS NOT NULL
               AND next_retry_at <= :now
             ORDER BY next_retry_at ASC
             LIMIT :limit",
            ['now' => $now, 'limit' => $limit],
        );
        if ($result->isFailure()) {
            return [];
        }

        return array_map(
            static fn (array $row): FailureState => FailureState::fromRow($row),
            $result->value(),
        );
    }

    /**
     * @return array<int, FailureState>
     */
    public function findUnresolvedTerminal(int $limit = 100): array
    {
        $result = $this->adapter->query(
            "SELECT * FROM failure_states
             WHERE classification = 'terminal'
               AND resolved_at IS NULL
             ORDER BY last_failed_at DESC
             LIMIT :limit",
            ['limit' => $limit],
        );
        if ($result->isFailure()) {
            return [];
        }

        return array_map(
            static fn (array $row): FailureState => FailureState::fromRow($row),
            $result->value(),
        );
    }

    public function save(FailureState $failureState): void
    {
        $row = $failureState->toArray();

        $sql = 'INSERT INTO failure_states (
            id, payment_id, classification, failure_code, failure_reason,
            failure_metadata, first_failed_at, last_failed_at, retry_count,
            next_retry_at, max_retries, resolved_at, resolution_notes,
            resolved_by, final_status, provider_code, gateway_order_id,
            correlation_id, is_retryable, context, created_at, updated_at
        ) VALUES (
            :id, :payment_id, :classification, :failure_code, :failure_reason,
            :failure_metadata, :first_failed_at, :last_failed_at, :retry_count,
            :next_retry_at, :max_retries, :resolved_at, :resolution_notes,
            :resolved_by, :final_status, :provider_code, :gateway_order_id,
            :correlation_id, :is_retryable, :context, :created_at, :updated_at
        )';

        $params = [
            'id' => $row['id'],
            'payment_id' => $row['payment_id'],
            'classification' => $row['classification'],
            'failure_code' => $row['failure_code'],
            'failure_reason' => $row['failure_reason'],
            'failure_metadata' => is_string($row['failure_metadata'])
                ? $row['failure_metadata']
                : json_encode($row['failure_metadata'] ?? [], JSON_THROW_ON_ERROR),
            'first_failed_at' => $row['first_failed_at'],
            'last_failed_at' => $row['last_failed_at'],
            'retry_count' => $row['retry_count'],
            'next_retry_at' => $row['next_retry_at'],
            'max_retries' => $row['max_retries'],
            'resolved_at' => $row['resolved_at'],
            'resolution_notes' => $row['resolution_notes'],
            'resolved_by' => $row['resolved_by'],
            'final_status' => $row['final_status'],
            'provider_code' => $row['provider_code'],
            'gateway_order_id' => $row['gateway_order_id'],
            'correlation_id' => $row['correlation_id'],
            'is_retryable' => $row['is_retryable'] ? '1' : '0',
            'context' => is_string($row['context'])
                ? $row['context']
                : json_encode($row['context'] ?? [], JSON_THROW_ON_ERROR),
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];

        $exec = $this->adapter->execute($sql, $params);
        if ($exec->isFailure()) {
            throw new RuntimeException('FailureStateRepository::save failed: '.$exec->error());
        }
    }

    public function update(FailureState $failureState): void
    {
        $row = $failureState->toArray();

        $sql = 'UPDATE failure_states SET
            classification = :classification,
            failure_code = :failure_code,
            failure_reason = :failure_reason,
            failure_metadata = :failure_metadata,
            first_failed_at = :first_failed_at,
            last_failed_at = :last_failed_at,
            retry_count = :retry_count,
            next_retry_at = :next_retry_at,
            max_retries = :max_retries,
            resolved_at = :resolved_at,
            resolution_notes = :resolution_notes,
            resolved_by = :resolved_by,
            final_status = :final_status,
            provider_code = :provider_code,
            gateway_order_id = :gateway_order_id,
            correlation_id = :correlation_id,
            is_retryable = :is_retryable,
            context = :context,
            updated_at = :updated_at
        WHERE id = :id';

        $params = [
            'id' => $row['id'],
            'classification' => $row['classification'],
            'failure_code' => $row['failure_code'],
            'failure_reason' => $row['failure_reason'],
            'failure_metadata' => is_string($row['failure_metadata'])
                ? $row['failure_metadata']
                : json_encode($row['failure_metadata'] ?? [], JSON_THROW_ON_ERROR),
            'first_failed_at' => $row['first_failed_at'],
            'last_failed_at' => $row['last_failed_at'],
            'retry_count' => $row['retry_count'],
            'next_retry_at' => $row['next_retry_at'],
            'max_retries' => $row['max_retries'],
            'resolved_at' => $row['resolved_at'],
            'resolution_notes' => $row['resolution_notes'],
            'resolved_by' => $row['resolved_by'],
            'final_status' => $row['final_status'],
            'provider_code' => $row['provider_code'],
            'gateway_order_id' => $row['gateway_order_id'],
            'correlation_id' => $row['correlation_id'],
            'is_retryable' => $row['is_retryable'] ? '1' : '0',
            'context' => is_string($row['context'])
                ? $row['context']
                : json_encode($row['context'] ?? [], JSON_THROW_ON_ERROR),
            'updated_at' => $row['updated_at'],
        ];

        $exec = $this->adapter->execute($sql, $params);
        if ($exec->isFailure()) {
            throw new RuntimeException('FailureStateRepository::update failed: '.$exec->error());
        }
    }

    public function markResolved(
        EntityId $id,
        string $resolutionNotes,
        ?string $resolvedBy = null,
        ?DateTimeImmutable $resolvedAt = null,
    ): FailureState {
        $resolvedAt ??= new DateTimeImmutable();
        $updatedAt = (new DateTimeImmutable())->format(DATE_ATOM);

        $sql = 'UPDATE failure_states SET
            resolved_at = :resolved_at,
            resolution_notes = :notes,
            resolved_by = :by,
            updated_at = :updated_at
        WHERE id = :id';

        $exec = $this->adapter->execute($sql, [
            'id' => $id->value(),
            'resolved_at' => $resolvedAt->format(DATE_ATOM),
            'notes' => $resolutionNotes,
            'by' => $resolvedBy,
            'updated_at' => $updatedAt,
        ]);
        if ($exec->isFailure()) {
            throw new RuntimeException('FailureStateRepository::markResolved failed: '.$exec->error());
        }

        $found = $this->findById($id);
        if ($found === null) {
            throw new RuntimeException("FailureStateRepository::markResolved: failure {$id->value()} not found after update");
        }

        return $found;
    }

    public function incrementRetry(EntityId $id, ?DateTimeImmutable $nextRetryAt = null): FailureState
    {
        $now = (new DateTimeImmutable())->format(DATE_ATOM);
        $updatedAt = $now;

        // Compute next_retry_at from the entity if not provided
        if ($nextRetryAt === null) {
            $current = $this->findById($id);
            if ($current === null) {
                throw new RuntimeException("FailureStateRepository::incrementRetry: failure {$id->value()} not found");
            }
            // Apply default backoff: 5 minutes for transient, 1 hour for terminal-recoverable
            $backoffSeconds = match ($current->classification()) {
                FailureClassification::RECOVERABLE_TRANSIENT => 300,
                FailureClassification::RECOVERABLE_TERMINAL => 3600,
                default => null,
            };
            $nextRetryAt = $backoffSeconds !== null
                ? $current->lastFailedAt()->modify("+{$backoffSeconds} seconds")
                : null;
        }

        $nextAtStr = $nextRetryAt?->format(DATE_ATOM);

        $sql = 'UPDATE failure_states SET
            retry_count = retry_count + 1,
            last_failed_at = :now,
            next_retry_at = :next_retry_at,
            updated_at = :updated_at
        WHERE id = :id';

        $exec = $this->adapter->execute($sql, [
            'id' => $id->value(),
            'now' => $now,
            'next_retry_at' => $nextAtStr,
            'updated_at' => $updatedAt,
        ]);
        if ($exec->isFailure()) {
            throw new RuntimeException('FailureStateRepository::incrementRetry failed: '.$exec->error());
        }

        $found = $this->findById($id);
        if ($found === null) {
            throw new RuntimeException("FailureStateRepository::incrementRetry: failure {$id->value()} not found after update");
        }

        return $found;
    }

    public function countByClassification(FailureClassification $classification): int
    {
        $result = $this->adapter->query(
            'SELECT COUNT(*) as cnt FROM failure_states WHERE classification = :c AND resolved_at IS NULL',
            ['c' => $classification->value],
        );
        if ($result->isFailure()) {
            return 0;
        }

        return (int) ($result->value()[0]['cnt'] ?? 0);
    }

    /**
     * @param  array<int, EntityId>  $paymentIds
     * @return array<string, FailureState>
     */
    public function findManyByPaymentIds(array $paymentIds): array
    {
        if ($paymentIds === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($paymentIds as $i => $eid) {
            $placeholders[] = ":pid{$i}";
            $params["pid{$i}"] = $eid->value();
        }

        $sql = sprintf(
            'SELECT * FROM failure_states WHERE payment_id IN (%s)',
            implode(', ', $placeholders),
        );

        $result = $this->adapter->query($sql, $params);
        if ($result->isFailure()) {
            return [];
        }

        $states = [];
        foreach ($result->value() as $row) {
            $f = FailureState::fromRow($row);
            $states[$f->paymentId()->value()] = $f;
        }

        return $states;
    }

    public function countUnresolvedOlderThan(DateTimeImmutable $cutoff): int
    {
        $result = $this->adapter->query(
            'SELECT COUNT(*) as cnt FROM failure_states WHERE resolved_at IS NULL AND last_failed_at <= :cutoff',
            ['cutoff' => $cutoff->format(DATE_ATOM)],
        );
        if ($result->isFailure()) {
            return 0;
        }

        return (int) ($result->value()[0]['cnt'] ?? 0);
    }
}
