<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Repositories;

use App\Payments\Domain\Repositories\IdempotencyKeyRepositoryContract;
use App\Persistence\Contracts\PersistenceAdapterContract;
use App\Shared\Support\Clock;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

final class IdempotencyKeyRepository implements IdempotencyKeyRepositoryContract
{
    public function __construct(
        private readonly PersistenceAdapterContract $adapter,
        private readonly Clock $clock = new \App\Shared\Support\SystemClock(),
    ) {}

    /**
     * Atomically reserve a key + scope.
     *
     * Doctrine:
     *   - MUST NOT throw. Failure is reported via false (caller decides
     *     whether to abort or run-with-dedupe-off).
     *   - Atomic at the DB layer. `INSERT ... ON CONFLICT DO NOTHING`
     *     works on both Postgres (>=9.5) and SQLite (>=3.24). The
     *     V1 schema declares `idempotency_keys.key` UNIQUE — the
     *     ON CONFLICT (key, scope) is the explicit guard.
     *   - Used by the inbound HTTP middleware as the DB fallback when
     *     Redis SETEX is unavailable.
     */
    public function reserve(string $key, string $scope, int $ttlSeconds): bool
    {
        try {
            $now = $this->clock->now();
            $expiresAt = $now->modify('+' . $ttlSeconds . ' seconds');

            $sql = 'INSERT INTO idempotency_keys (
                        key, scope, request_fingerprint,
                        locked_at, created_at, expires_at
                    ) VALUES (
                        :key, :scope, :fingerprint,
                        :locked_at, :created_at, :expires_at
                    )
                    ON CONFLICT (key, scope) DO NOTHING';

            $params = [
                'key'         => $key,
                'scope'       => $scope,
                'fingerprint' => substr(hash('sha256', $key . '|' . $scope), 0, 64),
                'locked_at'   => $now->format(DATE_ATOM),
                'created_at'  => $now->format(DATE_ATOM),
                'expires_at'  => $expiresAt->format(DATE_ATOM),
            ];

            $result = $this->adapter->execute($sql, $params);

            if ($result->isFailure()) {
                return false;
            }

            // Doctrine: `execute()` returns the number of affected rows.
            // For ON CONFLICT DO NOTHING: 1 = we own this slot, 0 = duplicate.
            // Cast to int for safety; some drivers return string.
            $rows = (int) $result->value();
            return $rows === 1;
        } catch (Throwable) {
            // Doctrine: never throw. Catch all, return false.
            return false;
        }
    }

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
