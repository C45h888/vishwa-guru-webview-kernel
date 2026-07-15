<?php

declare(strict_types=1);

namespace App\Payments\Domain\Repositories;

use DateTimeImmutable;

/**
 * Persistence boundary for the cross-cutting audit log.
 *
 * Every state-changing operation in the Payments domain MUST append an
 * audit_events row. The repository is intentionally write-mostly — reads
 * are reserved for the admin console.
 *
 * audit_events is APPEND-ONLY (schema: comments call out no UPDATE/DELETE).
 */
interface AuditEventRepositoryContract
{
    /**
     * Append an audit event.
     *
     * @param  array<string, mixed>  $context  Structured metadata describing the event
     */
    public function append(
        string $eventType,
        string $entityType,
        string $entityId,
        ?string $actor = null,
        ?string $correlationId = null,
        ?string $previousState = null,
        ?string $newState = null,
        array $context = [],
        ?DateTimeImmutable $occurredAt = null,
    ): string;

    /**
     * Find all audit events for a given entity, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findByEntity(string $entityType, string $entityId, int $limit = 100): array;

    /**
     * Find audit events by correlation id. Used to reconstruct a
     * distributed transaction after a partial failure.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findByCorrelationId(string $correlationId): array;

    /**
     * Count audit events of a given type in a window.
     */
    public function countByEventType(
        string $eventType,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): int;
}