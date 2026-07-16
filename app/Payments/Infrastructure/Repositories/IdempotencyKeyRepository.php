<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Repositories;

use App\Payments\Domain\Repositories\IdempotencyKeyRepositoryContract;
use App\Persistence\Contracts\PersistenceAdapterContract;
use DateTimeImmutable;
use RuntimeException;

final class IdempotencyKeyRepository implements IdempotencyKeyRepositoryContract
{
    public function __construct(
        private readonly PersistenceAdapterContract $adapter,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function findByKey(string $key): ?array
    {
        $result = $this->adapter->query(
            'SELECT * FROM idempotency_keys WHERE key = :key LIMIT 1',
            ['key' => $key],
        );
        if ($result->isFailure() || empty($result->value())) {
            return null;
        }

        $row = $result->value()[0];
        // Decode response_body JSON if present
        if (isset($row['response_body']) && is_string($row['response_body'])) {
            $row['response_body'] = json_decode($row['response_body'], true) ?? null;
        }

        return $row;
    }

    public function save(
        string $key,
        string $scope,
        string $entityType,
        string $entityId,
        string $requestHash,
        ?array $response = null,
        DateTimeImmutable $expiresAt = new DateTimeImmutable(),
    ): void {
        $now = (new DateTimeImmutable())->format(DATE_ATOM);
        $expiresAtStr = $expiresAt->format(DATE_ATOM);

        $sql = 'INSERT INTO idempotency_keys (
            key, scope, request_fingerprint, response_status,
            response_body, locked_by, locked_at,
            created_at, completed_at, expires_at
        ) VALUES (
            :key, :scope, :request_fingerprint, :response_status,
            :response_body, NULL, NULL,
            :created_at, NULL, :expires_at
        )';

        $params = [
            'key' => $key,
            'scope' => $scope,
            'request_fingerprint' => $requestHash,
            'response_status' => null,
            'response_body' => $response !== null
                ? json_encode($response, JSON_THROW_ON_ERROR)
                : null,
            'created_at' => $now,
            'expires_at' => $expiresAtStr,
        ];

        $exec = $this->adapter->execute($sql, $params);
        if ($exec->isFailure()) {
            throw new RuntimeException('IdempotencyKeyRepository::save failed: '.$exec->error());
        }
    }

    public function isActive(string $key, string $scope): bool
    {
        $now = (new DateTimeImmutable())->format(DATE_ATOM);

        $result = $this->adapter->query(
            'SELECT 1 FROM idempotency_keys
             WHERE key = :key AND scope = :scope
               AND expires_at > :now
               AND completed_at IS NULL
             LIMIT 1',
            ['key' => $key, 'scope' => $scope, 'now' => $now],
        );

        return ! $result->isFailure() && ! empty($result->value());
    }

    public function deleteExpired(?DateTimeImmutable $now = null): int
    {
        $now ??= new DateTimeImmutable();
        $nowStr = $now->format(DATE_ATOM);

        $result = $this->adapter->execute(
            'DELETE FROM idempotency_keys WHERE expires_at <= :now',
            ['now' => $nowStr],
        );
        if ($result->isFailure()) {
            throw new RuntimeException('IdempotencyKeyRepository::deleteExpired failed: '.$result->error());
        }

        return $result->value();
    }
}
