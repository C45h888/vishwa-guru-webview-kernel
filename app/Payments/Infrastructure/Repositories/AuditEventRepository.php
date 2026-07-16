<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Repositories;

use App\Payments\Domain\Repositories\AuditEventRepositoryContract;
use App\Persistence\Contracts\PersistenceAdapterContract;
use DateTimeImmutable;
use RuntimeException;

final class AuditEventRepository implements AuditEventRepositoryContract
{
    public function __construct(
        private readonly PersistenceAdapterContract $adapter,
    ) {}

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
    ): string {
        $now = (new \DateTimeImmutable())->format(DATE_ATOM);
        $occurredAt ??= new DateTimeImmutable();
        $occurredAtStr = $occurredAt->format(DATE_ATOM);

        // Build metadata JSON from the structured fields
        $metadata = [
            'previous_state' => $previousState,
            'new_state' => $newState,
        ];
        $metadata = array_merge($metadata, $context);

        $sql = 'INSERT INTO audit_events (
            id, actor_type, actor_id, action,
            entity_type, entity_id, request_id,
            ip_address, user_agent,
            occurred_at, metadata
        ) VALUES (
            :id, :actor_type, :actor_id, :action,
            :entity_type, :entity_id, :request_id,
            NULL, NULL,
            :occurred_at, :metadata
        )';

        $id = 'aud_'.bin2hex(random_bytes(12));

        $params = [
            'id' => $id,
            'actor_type' => $actor ?? 'system',
            'actor_id' => null,
            'action' => $eventType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'request_id' => $correlationId,
            'occurred_at' => $occurredAtStr,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
        ];

        $exec = $this->adapter->execute($sql, $params);
        if ($exec->isFailure()) {
            throw new RuntimeException('AuditEventRepository::append failed: '.$exec->error());
        }

        return $id;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByEntity(string $entityType, string $entityId, int $limit = 100): array
    {
        $result = $this->adapter->query(
            'SELECT * FROM audit_events
             WHERE entity_type = :et AND entity_id = :eid
             ORDER BY occurred_at DESC
             LIMIT :limit',
            ['et' => $entityType, 'eid' => $entityId, 'limit' => $limit],
        );
        if ($result->isFailure()) {
            return [];
        }

        return array_map(
            fn (array $row): array => $this->decodeRow($row),
            $result->value(),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByCorrelationId(string $correlationId): array
    {
        $result = $this->adapter->query(
            'SELECT * FROM audit_events
             WHERE request_id = :rid
             ORDER BY occurred_at DESC',
            ['rid' => $correlationId],
        );
        if ($result->isFailure()) {
            return [];
        }

        return array_map(
            fn (array $row): array => $this->decodeRow($row),
            $result->value(),
        );
    }

    public function countByEventType(string $eventType, DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        $result = $this->adapter->query(
            'SELECT COUNT(*) as cnt FROM audit_events
             WHERE action = :action
               AND occurred_at >= :from AND occurred_at <= :to',
            [
                'action' => $eventType,
                'from' => $from->format(DATE_ATOM),
                'to' => $to->format(DATE_ATOM),
            ],
        );
        if ($result->isFailure()) {
            return 0;
        }

        return (int) ($result->value()[0]['cnt'] ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeRow(array $row): array
    {
        if (isset($row['metadata']) && is_string($row['metadata'])) {
            $row['metadata'] = json_decode($row['metadata'], true) ?? [];
        }

        return $row;
    }
}
