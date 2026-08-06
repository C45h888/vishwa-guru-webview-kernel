<?php

declare(strict_types=1);

namespace App\Payments\Domain\Entities;

use App\Payments\Contracts\FailureStateContract;
use App\Payments\Domain\Enums\FailureClassification;
use App\Payments\Domain\Enums\TransactionStatus;
use App\Persistence\Contracts\EntityContract;
use App\Persistence\ValueObjects\EntityId;
use App\Shared\ValueObjects\Identifier;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * FailureState aggregate root.
 *
 * Mirrors the `failure_states` table defined in `schema-neon/V1-schema.sql`.
 * FailureStates are IMMUTABLE for content fields (failure_code, classification,
 * first_failed_at). Only retry/resolution tracking may be updated.
 *
 * Implements FailureStateContract so the ReceiptOrchestrator, PaymentService,
 * and admin console all read from a single canonical shape.
 */
final class FailureState implements EntityContract, FailureStateContract
{
    public const ENTITY_TYPE = 'failure_state';

    /**
     * @param  array<string, mixed>  $failureMetadata
     * @param  array<string, mixed>  $context
     */
    private function __construct(
        private readonly EntityId $id,
        private readonly EntityId $paymentId,
        private readonly FailureClassification $classification,
        private readonly string $failureCode,
        private readonly ?string $failureReason,
        private readonly array $failureMetadata,
        private readonly DateTimeImmutable $firstFailedAt,
        private readonly DateTimeImmutable $lastFailedAt,
        private readonly int $retryCount,
        private readonly ?DateTimeImmutable $nextRetryAt,
        private readonly int $maxRetries,
        private readonly ?DateTimeImmutable $resolvedAt,
        private readonly ?string $resolutionNotes,
        private readonly ?string $resolvedBy,
        private readonly TransactionStatus $finalStatus,
        private readonly string $providerCode,
        private readonly string $gatewayOrderId,
        private readonly string $correlationId,
        private readonly bool $isRetryable,
        private readonly array $context,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * @param  array<string, mixed>  $failureMetadata
     * @param  array<string, mixed>  $context
     */
    public static function record(
        EntityId $paymentId,
        FailureClassification $classification,
        string $failureCode,
        TransactionStatus $finalStatus,
        string $providerCode,
        string $gatewayOrderId,
        string $correlationId,
        ?string $failureReason = null,
        array $failureMetadata = [],
        ?int $maxRetries = null,
        ?DateTimeImmutable $nextRetryAt = null,
        array $context = [],
        ?EntityId $id = null,
    ): self {
        if (empty($failureCode)) {
            throw new InvalidArgumentException('FailureState failureCode cannot be empty');
        }
        if (empty($gatewayOrderId)) {
            throw new InvalidArgumentException('FailureState gatewayOrderId cannot be empty');
        }

        $now = new DateTimeImmutable();
        $maxRetries ??= $classification->defaultMaxRetries();
        $nextRetryAt ??= $classification->defaultBackoffSeconds() > 0
            ? $now->modify('+'.$classification->defaultBackoffSeconds().' seconds')
            : null;

        return new self(
            id: $id ?? EntityId::generate(self::ENTITY_TYPE),
            paymentId: $paymentId,
            classification: $classification,
            failureCode: $failureCode,
            failureReason: $failureReason,
            failureMetadata: $failureMetadata,
            firstFailedAt: $now,
            lastFailedAt: $now,
            retryCount: 0,
            nextRetryAt: $nextRetryAt,
            maxRetries: $maxRetries,
            resolvedAt: null,
            resolutionNotes: null,
            resolvedBy: null,
            finalStatus: $finalStatus,
            providerCode: $providerCode,
            gatewayOrderId: $gatewayOrderId,
            correlationId: $correlationId,
            isRetryable: $classification->isRetryable(),
            context: $context,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromRow(array $row): self
    {
        $required = ['id', 'payment_id', 'classification', 'failure_code', 'first_failed_at', 'last_failed_at', 'retry_count', 'max_retries', 'created_at', 'updated_at'];
        foreach ($required as $key) {
            if (! array_key_exists($key, $row)) {
                throw new InvalidArgumentException("FailureState row missing required key: {$key}");
            }
        }

        return new self(
            id: EntityId::fromString($row['id']),
            paymentId: EntityId::fromString($row['payment_id']),
            classification: FailureClassification::from($row['classification']),
            failureCode: (string) $row['failure_code'],
            failureReason: $row['failure_reason'] ?? null,
            failureMetadata: self::decodeJson($row['failure_metadata'] ?? '{}'),
            firstFailedAt: self::parseDate($row['first_failed_at']) ?? new DateTimeImmutable(),
            lastFailedAt: self::parseDate($row['last_failed_at']) ?? new DateTimeImmutable(),
            retryCount: (int) $row['retry_count'],
            nextRetryAt: self::parseDate($row['next_retry_at'] ?? null),
            maxRetries: (int) $row['max_retries'],
            resolvedAt: self::parseDate($row['resolved_at'] ?? null),
            resolutionNotes: $row['resolution_notes'] ?? null,
            resolvedBy: $row['resolved_by'] ?? null,
            // The current schema doesn't have these four columns; default
            // them so repository round-trips hydrate. The schema agent is
            // responsible for adding them — once present, these fallbacks
            // will be unused.
            finalStatus: isset($row['final_status'])
                ? TransactionStatus::from($row['final_status'])
                : TransactionStatus::FAILED,
            providerCode: (string) ($row['provider_code'] ?? 'unknown'),
            gatewayOrderId: (string) ($row['gateway_order_id'] ?? ''),
            correlationId: (string) ($row['correlation_id'] ?? ''),
            isRetryable: (bool) ($row['is_retryable'] ?? $row['classification'] !== null),
            context: self::decodeJson($row['context'] ?? '{}'),
            createdAt: self::parseDate($row['created_at']) ?? new DateTimeImmutable(),
            updatedAt: self::parseDate($row['updated_at']) ?? new DateTimeImmutable(),
        );
    }

    public function id(): EntityId
    {
        return $this->id;
    }

    public function entityType(): string
    {
        return self::ENTITY_TYPE;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id->value(),
            'payment_id' => $this->paymentId->value(),
            'classification' => $this->classification->value,
            'failure_code' => $this->failureCode,
            'failure_reason' => $this->failureReason,
            'failure_metadata' => json_encode($this->failureMetadata, JSON_THROW_ON_ERROR),
            'first_failed_at' => $this->firstFailedAt->format(DATE_ATOM),
            'last_failed_at' => $this->lastFailedAt->format(DATE_ATOM),
            'retry_count' => $this->retryCount,
            'next_retry_at' => $this->nextRetryAt?->format(DATE_ATOM),
            'max_retries' => $this->maxRetries,
            'resolved_at' => $this->resolvedAt?->format(DATE_ATOM),
            'resolution_notes' => $this->resolutionNotes,
            'resolved_by' => $this->resolvedBy,
            'final_status' => $this->finalStatus->value,
            'provider_code' => $this->providerCode,
            'gateway_order_id' => $this->gatewayOrderId,
            'correlation_id' => $this->correlationId,
            'is_retryable' => $this->isRetryable,
            'context' => json_encode($this->context, JSON_THROW_ON_ERROR),
            'created_at' => $this->createdAt->format(DATE_ATOM),
            'updated_at' => $this->updatedAt->format(DATE_ATOM),
        ];
    }

    /**
     * FailureStates are mostly immutable. Only retry tracking and
     * resolution metadata may be updated.
     *
     * @param  array<string, mixed>  $changes
     */
    public function withChanges(array $changes): static
    {
        $immutableFields = [
            'payment_id', 'classification', 'failure_code',
            'first_failed_at', 'provider_code', 'gateway_order_id',
        ];
        foreach ($immutableFields as $field) {
            if (array_key_exists($field, $changes)) {
                throw new InvalidArgumentException(
                    "FailureState field [{$field}] is immutable post-record"
                );
            }
        }

        $row = $this->toArray();
        $merged = array_merge($row, $changes);
        $merged['updated_at'] = (new DateTimeImmutable())->format(DATE_ATOM);

        return self::fromRow($merged);
    }

    // ─── FailureStateContract surface ──────────────────────────────────

    public function identifier(): Identifier
    {
        return new Identifier($this->id->ulid());
    }

    public function transactionIdentifier(): Identifier
    {
        return new Identifier($this->paymentId->ulid());
    }

    public function gatewayOrderId(): string
    {
        return $this->gatewayOrderId;
    }

    public function providerName(): string
    {
        return $this->providerCode;
    }

    public function finalStatus(): TransactionStatus
    {
        return $this->finalStatus;
    }

    public function failureCode(): string
    {
        return $this->failureCode;
    }

    public function failureReason(): string
    {
        return $this->failureReason ?? '';
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->firstFailedAt;
    }

    public function isRetryable(): bool
    {
        return $this->isRetryable;
    }

    public function retryAttempts(): int
    {
        return $this->retryCount;
    }

    public function correlationId(): string
    {
        return $this->correlationId;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }

    // ─── Getters ────────────────────────────────────────────────────────

    public function paymentId(): EntityId
    {
        return $this->paymentId;
    }

    public function classification(): FailureClassification
    {
        return $this->classification;
    }

    public function failureMetadata(): array
    {
        return $this->failureMetadata;
    }

    public function firstFailedAt(): DateTimeImmutable
    {
        return $this->firstFailedAt;
    }

    public function lastFailedAt(): DateTimeImmutable
    {
        return $this->lastFailedAt;
    }

    public function retryCount(): int
    {
        return $this->retryCount;
    }

    public function nextRetryAt(): ?DateTimeImmutable
    {
        return $this->nextRetryAt;
    }

    public function maxRetries(): int
    {
        return $this->maxRetries;
    }

    public function resolvedAt(): ?DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function resolutionNotes(): ?string
    {
        return $this->resolutionNotes;
    }

    public function resolvedBy(): ?string
    {
        return $this->resolvedBy;
    }

    public function providerCode(): string
    {
        return $this->providerCode;
    }

    public function correlationIdValue(): string
    {
        return $this->correlationId;
    }

    public function isResolved(): bool
    {
        return $this->resolvedAt !== null;
    }

    public function hasRetriesRemaining(): bool
    {
        return $this->retryCount < $this->maxRetries;
    }

    public function isDueForRetry(?DateTimeImmutable $now = null): bool
    {
        if (! $this->isRetryable || $this->isResolved) {
            return false;
        }
        if (! $this->hasRetriesRemaining()) {
            return false;
        }
        $now ??= new DateTimeImmutable();

        return $this->nextRetryAt === null || $this->nextRetryAt <= $now;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @param  array<string, mixed>|string  $value
     * @return array<string, mixed>
     */
    private static function decodeJson(array|string $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function parseDate(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        return new DateTimeImmutable((string) $value);
    }
}