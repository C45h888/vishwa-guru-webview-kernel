<?php

declare(strict_types=1);

namespace App\Payments\Domain\Repositories;

use DateTimeImmutable;

/**
 * Persistence boundary for idempotency keys.
 *
 * Idempotency keys live in the `idempotency_keys` table and are
 * referenced by donations.idempotency_key and payments.idempotency_key.
 * The repository centralizes the lookup-or-create pattern that
 * PaymentService uses to prevent duplicate processing.
 *
 * Each idempotency key has a TTL (schema: idempotency_keys.expires_at).
 * Expired keys are deleted by a background worker and MAY be reused.
 */
interface IdempotencyKeyRepositoryContract
{
    /**
     * Find an idempotency key by its string value.
     *
     * @return array<string, mixed>|null  The stored payload (entity_type, entity_id, request_hash, response, etc.) or null
     */
    public function findByKey(string $key): ?array;

    /**
     * Persist a new idempotency key with the given payload and TTL.
     *
     * @param  array<string, mixed>  $payload
     */
    public function save(
        string $key,
        string $scope,
        string $entityType,
        string $entityId,
        string $requestHash,
        ?array $response = null,
        DateTimeImmutable $expiresAt = new DateTimeImmutable(),
    ): void;

    /**
     * Whether a non-expired key exists for the given value + scope.
     */
    public function isActive(string $key, string $scope): bool;

    /**
     * Delete expired keys (housekeeping).
     */
    public function deleteExpired(?DateTimeImmutable $now = null): int;
}