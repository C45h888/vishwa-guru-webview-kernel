<?php

declare(strict_types=1);

namespace App\Payments\Domain\Entities;

use App\Payments\Domain\Enums\ReceiptDeliveryState;
use App\Payments\Domain\Exceptions\PaymentStateTransitionException;
use App\Payments\Domain\StateMachines\ReceiptStateMachine;
use App\Payments\Domain\StateMachines\StateTransitionEvent;
use App\Persistence\Contracts\EntityContract;
use App\Persistence\ValueObjects\EntityId;
use App\Shared\ValueObjects\Identifier;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Receipt aggregate root.
 *
 * Mirrors the `receipts` table defined in `schema-neon/V1-schema.sql`.
 * Receipts are immutable once issued; withChanges() supports only the
 * delivery-tracking fields (delivery_status, delivered_at, delivery_address).
 * Any other attempt to mutate the receipt's content fields throws
 * PaymentStateTransitionException.
 */
final class Receipt implements EntityContract
{
    public const ENTITY_TYPE = 'receipt';

    // Delivery status values delegate to the ReceiptDeliveryState enum.
    // (ReceiptDeliveryState::PENDING, ::DELIVERED, ::FAILED, ::BOUNCED)
    public const DELIVERY_PENDING = ReceiptDeliveryState::PENDING->value;
    public const DELIVERY_DELIVERED = ReceiptDeliveryState::DELIVERED->value;
    public const DELIVERY_FAILED = ReceiptDeliveryState::FAILED->value;
    public const DELIVERY_BOUNCED = ReceiptDeliveryState::BOUNCED->value;

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function __construct(
        private readonly EntityId $id,
        private readonly EntityId $donationId,
        private readonly EntityId $transactionId,
        private readonly EntityId $fileAssetId,
        private readonly string $receiptNumber,
        private readonly string $contentHash,
        private readonly DateTimeImmutable $issuedAt,
        private readonly ?DateTimeImmutable $deliveredAt,
        private readonly string $deliveryStatus,
        private readonly ?string $deliveryChannel,
        private readonly ?string $deliveryAddress,
        private readonly array $metadata,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt,
        private readonly ?DateTimeImmutable $deletedAt = null,
    ) {
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function issue(
        EntityId $donationId,
        EntityId $transactionId,
        EntityId $fileAssetId,
        string $receiptNumber,
        string $contentHash,
        DateTimeImmutable $issuedAt,
        ?string $deliveryChannel = null,
        ?string $deliveryAddress = null,
        array $metadata = [],
        ?EntityId $id = null,
    ): self {
        if (empty($receiptNumber)) {
            throw new InvalidArgumentException('Receipt receiptNumber cannot be empty');
        }
        if (! preg_match('/^TR-\d{4}-[A-Z0-9]{4,32}$/', $receiptNumber)) {
            throw new InvalidArgumentException(
                "Receipt receiptNumber must match TR-YYYY-{shortId}: got {$receiptNumber}"
            );
        }
        if (empty($contentHash)) {
            throw new InvalidArgumentException('Receipt contentHash cannot be empty');
        }

        $now = new DateTimeImmutable();

        return new self(
            id: $id ?? EntityId::generate(self::ENTITY_TYPE),
            donationId: $donationId,
            transactionId: $transactionId,
            fileAssetId: $fileAssetId,
            receiptNumber: $receiptNumber,
            contentHash: $contentHash,
            issuedAt: $issuedAt,
            deliveredAt: null,
            deliveryStatus: self::DELIVERY_PENDING,
            deliveryChannel: $deliveryChannel,
            deliveryAddress: $deliveryAddress,
            metadata: $metadata,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromRow(array $row): self
    {
        $required = ['id', 'donation_id', 'transaction_id', 'file_asset_id', 'receipt_number', 'content_hash', 'issued_at', 'delivery_status', 'created_at', 'updated_at'];
        foreach ($required as $key) {
            if (! array_key_exists($key, $row)) {
                throw new InvalidArgumentException("Receipt row missing required key: {$key}");
            }
        }

        return new self(
            id: EntityId::fromString($row['id']),
            donationId: EntityId::fromString($row['donation_id']),
            transactionId: EntityId::fromString($row['transaction_id']),
            fileAssetId: EntityId::fromString($row['file_asset_id']),
            receiptNumber: (string) $row['receipt_number'],
            contentHash: (string) $row['content_hash'],
            issuedAt: self::parseDate($row['issued_at']) ?? new DateTimeImmutable(),
            deliveredAt: self::parseDate($row['delivered_at'] ?? null),
            deliveryStatus: (string) $row['delivery_status'],
            deliveryChannel: $row['delivery_channel'] ?? null,
            deliveryAddress: $row['delivery_address'] ?? null,
            metadata: self::decodeJson($row['metadata'] ?? '{}'),
            createdAt: self::parseDate($row['created_at']) ?? new DateTimeImmutable(),
            updatedAt: self::parseDate($row['updated_at']) ?? new DateTimeImmutable(),
            deletedAt: self::parseDate($row['deleted_at'] ?? null),
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
            'donation_id' => $this->donationId->value(),
            'transaction_id' => $this->transactionId->value(),
            'file_asset_id' => $this->fileAssetId->value(),
            'receipt_number' => $this->receiptNumber,
            'content_hash' => $this->contentHash,
            'issued_at' => $this->issuedAt->format(DATE_ATOM),
            'delivered_at' => $this->deliveredAt?->format(DATE_ATOM),
            'delivery_status' => $this->deliveryStatus,
            'delivery_channel' => $this->deliveryChannel,
            'delivery_address' => $this->deliveryAddress,
            'metadata' => json_encode($this->metadata, JSON_THROW_ON_ERROR),
            'created_at' => $this->createdAt->format(DATE_ATOM),
            'updated_at' => $this->updatedAt->format(DATE_ATOM),
            'deleted_at' => $this->deletedAt?->format(DATE_ATOM),
        ];
    }

    /**
     * Apply entity-level changes. Content fields are immutable post-issue;
     * only delivery tracking fields can be changed, and ONLY via
     * transitionDelivery() with a ReceiptStateMachine. Direct mutation
     * of delivery_status via withChanges() is rejected.
     *
     * @param  array<string, mixed>  $changes
     */
    public function withChanges(array $changes): static
    {
        if (array_key_exists('delivery_status', $changes)) {
            throw new \LogicException(
                'Receipt::withChanges() cannot set delivery_status directly. Use transitionDelivery() with a ReceiptStateMachine.'
            );
        }

        $immutableFields = [
            'donation_id', 'transaction_id', 'file_asset_id',
            'receipt_number', 'content_hash', 'issued_at',
        ];
        foreach ($immutableFields as $field) {
            if (array_key_exists($field, $changes)) {
                throw new PaymentStateTransitionException(
                    sprintf('Receipt field [%s] is immutable post-issue', $field),
                    \App\Payments\Domain\Enums\TransactionStatus::SETTLED,
                    \App\Payments\Domain\Enums\TransactionStatus::SETTLED,
                    ['entity' => 'receipt', 'field' => $field],
                );
            }
        }

        $row = $this->toArray();
        $merged = array_merge($row, $changes);
        $merged['updated_at'] = (new DateTimeImmutable())->format(DATE_ATOM);

        return self::fromRow($merged);
    }

    /**
     * Apply a state-machine-validated delivery-status transition.
     * Returns a new Receipt instance reflecting the delivery update.
     *
     * @param  array<string, mixed>  $context
     */
    public function transitionDelivery(
        ReceiptStateMachine $machine,
        StateTransitionEvent $event,
        array $context = [],
    ): self {
        $from = ReceiptDeliveryState::from($this->deliveryStatus);
        $result = $machine->transition($from, $event, $context);

        $row = $this->toArray();
        foreach ($result->timestampChanges() as $column => $ts) {
            $row[$column] = $ts instanceof \DateTimeImmutable ? $ts->format(DATE_ATOM) : null;
        }
        foreach ($result->entityChanges() as $field => $value) {
            $row[$field] = $value;
        }

        return self::fromRow($row);
    }

    // ─── Getters ────────────────────────────────────────────────────────

    public function donationId(): EntityId
    {
        return $this->donationId;
    }

    public function transactionId(): EntityId
    {
        return $this->transactionId;
    }

    public function fileAssetId(): EntityId
    {
        return $this->fileAssetId;
    }

    public function receiptNumber(): string
    {
        return $this->receiptNumber;
    }

    public function contentHash(): string
    {
        return $this->contentHash;
    }

    public function issuedAt(): DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function deliveredAt(): ?DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function deliveryStatus(): string
    {
        return $this->deliveryStatus;
    }

    public function deliveryChannel(): ?string
    {
        return $this->deliveryChannel;
    }

    public function deliveryAddress(): ?string
    {
        return $this->deliveryAddress;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function deletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function isDelivered(): bool
    {
        return $this->deliveryStatus === self::DELIVERY_DELIVERED;
    }

    public function isPending(): bool
    {
        return $this->deliveryStatus === self::DELIVERY_PENDING;
    }

    public function hasFailedDelivery(): bool
    {
        return in_array($this->deliveryStatus, [
            self::DELIVERY_FAILED,
            self::DELIVERY_BOUNCED,
        ], true);
    }

    public function identifier(): Identifier
    {
        return new Identifier($this->id->ulid());
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