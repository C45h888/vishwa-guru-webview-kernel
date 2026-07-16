<?php

declare(strict_types=1);

namespace App\Payments\Domain\Entities;

use App\Payments\Domain\Enums\Currency;
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
 * Receipts are immutable once issued for all financial fields;
 * only delivery-tracking fields may be updated, and ONLY via
 * transitionDelivery() with a ReceiptStateMachine.
 *
 * Financial fields (donation_id, payment_id, campaign_id, amount_minor,
 * currency_code, donor_name, donor_pan, donor_address, content_hash,
 * receipt_number) are immutable post-generation. State transitions
 * (generated → delivered → archived) and delivery_metadata may UPDATE.
 *
 * @property EntityId      $id
 * @property EntityId      $donationId
 * @property EntityId      $paymentId
 * @property EntityId      $campaignId
 * @property string        $receiptNumber
 * @property string        $campaignTitleSnapshot
 * @property string       $donorName
 * @property string|null   $donorEmail
 * @property string|null   $donorPan
 * @property array|null    $donorAddress
 * @property int          $amountMinor
 * @property Currency     $currency
 * @property string|null  $amountInWords
 * @property bool         $isTaxDeductible
 * @property bool         $tax80gEligible
 * @property string|null   $tax80gCertificateNumber
 * @property string       $contentHash
 * @property string       $state
 * @property DateTimeImmutable $generatedAt
 * @property DateTimeImmutable|null $deliveredAt
 * @property string       $deliveryStatus
 * @property string|null  $deliveryChannel
 * @property array        $deliveryMetadata
 * @property EntityId|null $receiptFileId
 * @property EntityId|null $certificate80gFileId
 * @property array        $metadata
 * @property DateTimeImmutable $createdAt
 * @property DateTimeImmutable $updatedAt
 * @property DateTimeImmutable|null $deletedAt
 */
final class Receipt implements EntityContract
{
    public const ENTITY_TYPE = 'receipt';

    // Delivery status values
    public const DELIVERY_PENDING = ReceiptDeliveryState::PENDING->value;
    public const DELIVERY_DELIVERED = ReceiptDeliveryState::DELIVERED->value;
    public const DELIVERY_FAILED = ReceiptDeliveryState::FAILED->value;
    public const DELIVERY_BOUNCED = ReceiptDeliveryState::BOUNCED->value;

    /**
     * @param  array<string, mixed>|null  $donorAddress
     * @param  array<string, mixed>       $deliveryMetadata
     * @param  array<string, mixed>       $metadata
     */
    private function __construct(
        private readonly EntityId $id,
        private readonly EntityId $donationId,
        private readonly EntityId $paymentId,
        private readonly EntityId $campaignId,
        private readonly string $receiptNumber,
        private readonly string $campaignTitleSnapshot,
        private readonly string $donorName,
        private readonly ?string $donorEmail,
        private readonly ?string $donorPan,
        private readonly ?array $donorAddress,
        private readonly int $amountMinor,
        private readonly Currency $currency,
        private readonly ?string $amountInWords,
        private readonly bool $isTaxDeductible,
        private readonly bool $tax80gEligible,
        private readonly ?string $tax80gCertificateNumber,
        private readonly string $contentHash,
        private readonly string $state,
        private readonly DateTimeImmutable $generatedAt,
        private readonly ?DateTimeImmutable $deliveredAt,
        private readonly string $deliveryStatus,
        private readonly ?string $deliveryChannel,
        private readonly array $deliveryMetadata,
        private readonly ?EntityId $receiptFileId,
        private readonly ?EntityId $certificate80gFileId,
        private readonly array $metadata,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt,
        private readonly ?DateTimeImmutable $deletedAt = null,
    ) {
    }

    /**
     * Issue a new receipt — the canonical factory.
     *
     * @param  array<string, mixed>|null  $donorAddress
     * @param  array<string, mixed>       $deliveryMetadata
     * @param  array<string, mixed>       $metadata
     */
    public static function issue(
        EntityId $donationId,
        EntityId $paymentId,
        EntityId $campaignId,
        string $receiptNumber,
        string $campaignTitleSnapshot,
        string $donorName,
        int $amountMinor,
        Currency $currency,
        string $contentHash,
        ?string $donorEmail = null,
        ?string $donorPan = null,
        ?array $donorAddress = null,
        ?string $amountInWords = null,
        bool $isTaxDeductible = true,
        bool $tax80gEligible = false,
        ?string $tax80gCertificateNumber = null,
        ?EntityId $receiptFileId = null,
        ?EntityId $certificate80gFileId = null,
        ?string $deliveryChannel = null,
        ?string $deliveryAddress = null,
        array $deliveryMetadata = [],
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
        if ($amountMinor <= 0) {
            throw new InvalidArgumentException("Receipt amountMinor must be positive (got {$amountMinor})");
        }

        $now = new DateTimeImmutable();

        return new self(
            id: $id ?? EntityId::generate(self::ENTITY_TYPE),
            donationId: $donationId,
            paymentId: $paymentId,
            campaignId: $campaignId,
            receiptNumber: $receiptNumber,
            campaignTitleSnapshot: $campaignTitleSnapshot,
            donorName: $donorName,
            donorEmail: $donorEmail,
            donorPan: $donorPan,
            donorAddress: $donorAddress,
            amountMinor: $amountMinor,
            currency: $currency,
            amountInWords: $amountInWords,
            isTaxDeductible: $isTaxDeductible,
            tax80gEligible: $tax80gEligible,
            tax80gCertificateNumber: $tax80gCertificateNumber,
            contentHash: $contentHash,
            state: ReceiptDeliveryState::PENDING->value,
            generatedAt: $now,
            deliveredAt: null,
            deliveryStatus: self::DELIVERY_PENDING,
            deliveryChannel: $deliveryChannel,
            deliveryMetadata: $deliveryMetadata,
            receiptFileId: $receiptFileId,
            certificate80gFileId: $certificate80gFileId,
            metadata: $metadata,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    /**
     * Rehydrate from a database row.
     *
     * @param  array<string, mixed>  $row
     */
    public static function fromRow(array $row): self
    {
        $required = [
            'id', 'donation_id', 'payment_id', 'campaign_id',
            'receipt_number', 'campaign_title_snapshot', 'donor_name',
            'amount_minor', 'currency_code', 'content_hash',
            'state', 'generated_at', 'delivery_status',
            'created_at', 'updated_at',
        ];
        foreach ($required as $key) {
            if (! array_key_exists($key, $row)) {
                throw new InvalidArgumentException("Receipt row missing required key: {$key}");
            }
        }

        return new self(
            id: EntityId::fromString($row['id']),
            donationId: EntityId::fromString($row['donation_id']),
            paymentId: EntityId::fromString($row['payment_id']),
            campaignId: EntityId::fromString($row['campaign_id']),
            receiptNumber: (string) $row['receipt_number'],
            campaignTitleSnapshot: (string) $row['campaign_title_snapshot'],
            donorName: (string) $row['donor_name'],
            donorEmail: isset($row['donor_email']) ? (string) $row['donor_email'] : null,
            donorPan: isset($row['donor_pan']) ? (string) $row['donor_pan'] : null,
            donorAddress: isset($row['donor_address'])
                ? self::decodeJson($row['donor_address'])
                : null,
            amountMinor: (int) $row['amount_minor'],
            currency: Currency::from($row['currency_code']),
            amountInWords: isset($row['amount_in_words']) ? (string) $row['amount_in_words'] : null,
            isTaxDeductible: (bool) ($row['is_tax_deductible'] ?? true),
            tax80gEligible: (bool) ($row['tax_80g_eligible'] ?? false),
            tax80gCertificateNumber: isset($row['tax_80g_certificate_number'])
                ? (string) $row['tax_80g_certificate_number']
                : null,
            contentHash: (string) $row['content_hash'],
            state: (string) $row['state'],
            generatedAt: self::parseDate($row['generated_at']) ?? new DateTimeImmutable(),
            deliveredAt: self::parseDate($row['delivered_at'] ?? null),
            deliveryStatus: (string) ($row['delivery_status'] ?? self::DELIVERY_PENDING),
            deliveryChannel: isset($row['delivery_channel']) ? (string) $row['delivery_channel'] : null,
            deliveryMetadata: self::decodeJson($row['delivery_metadata'] ?? '{}'),
            receiptFileId: isset($row['receipt_file_id']) && $row['receipt_file_id'] !== null
                ? EntityId::fromString($row['receipt_file_id'])
                : null,
            certificate80gFileId: isset($row['certificate_80g_file_id']) && $row['certificate_80g_file_id'] !== null
                ? EntityId::fromString($row['certificate_80g_file_id'])
                : null,
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
            'payment_id' => $this->paymentId->value(),
            'campaign_id' => $this->campaignId->value(),
            'receipt_number' => $this->receiptNumber,
            'campaign_title_snapshot' => $this->campaignTitleSnapshot,
            'donor_name' => $this->donorName,
            'donor_email' => $this->donorEmail,
            'donor_pan' => $this->donorPan,
            'donor_address' => $this->donorAddress !== null
                ? json_encode($this->donorAddress, JSON_THROW_ON_ERROR)
                : null,
            'amount_minor' => $this->amountMinor,
            'currency_code' => $this->currency->value,
            'amount_in_words' => $this->amountInWords,
            'is_tax_deductible' => $this->isTaxDeductible,
            'tax_80g_eligible' => $this->tax80gEligible,
            'tax_80g_certificate_number' => $this->tax80gCertificateNumber,
            'receipt_file_id' => $this->receiptFileId?->value(),
            'certificate_80g_file_id' => $this->certificate80gFileId?->value(),
            'content_hash' => $this->contentHash,
            'state' => $this->state,
            'generated_at' => $this->generatedAt->format(DATE_ATOM),
            'delivered_at' => $this->deliveredAt?->format(DATE_ATOM),
            'delivery_status' => $this->deliveryStatus,
            'delivery_channel' => $this->deliveryChannel,
            'delivery_metadata' => json_encode($this->deliveryMetadata, JSON_THROW_ON_ERROR),
            'metadata' => json_encode($this->metadata, JSON_THROW_ON_ERROR),
            'created_at' => $this->createdAt->format(DATE_ATOM),
            'updated_at' => $this->updatedAt->format(DATE_ATOM),
            'deleted_at' => $this->deletedAt?->format(DATE_ATOM),
        ];
    }

    /**
     * Apply entity-level changes. Financial fields are immutable post-issue;
     * only delivery tracking fields may be changed, and ONLY via
     * transitionDelivery() with a ReceiptStateMachine.
     *
     * @param  array<string, mixed>  $changes
     * @throws LogicException       When a financial field is mutated directly.
     */
    public function withChanges(array $changes): static
    {
        $immutableFields = [
            'donation_id', 'payment_id', 'campaign_id', 'receipt_number',
            'campaign_title_snapshot', 'donor_name', 'donor_email', 'donor_pan',
            'donor_address', 'amount_minor', 'currency_code', 'amount_in_words',
            'is_tax_deductible', 'tax_80g_eligible', 'tax_80g_certificate_number',
            'receipt_file_id', 'certificate_80g_file_id', 'content_hash',
            'generated_at',
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

        // delivery_status must go through transitionDelivery()
        if (array_key_exists('delivery_status', $changes)) {
            throw new \LogicException(
                'Receipt::withChanges() cannot set delivery_status directly. '.
                'Use transitionDelivery() with a ReceiptStateMachine.'
            );
        }

        $row = $this->toArray();
        $merged = array_merge($row, $changes);
        $merged['updated_at'] = (new DateTimeImmutable())->format(DATE_ATOM);

        return self::fromRow($merged);
    }

    /**
     * Apply a state-machine-validated delivery-status transition.
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
            $row[$column] = $ts instanceof \DateTimeImmutable
                ? $ts->format(DATE_ATOM)
                : null;
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

    public function paymentId(): EntityId
    {
        return $this->paymentId;
    }

    public function campaignId(): EntityId
    {
        return $this->campaignId;
    }

    public function receiptNumber(): string
    {
        return $this->receiptNumber;
    }

    public function campaignTitleSnapshot(): string
    {
        return $this->campaignTitleSnapshot;
    }

    public function donorName(): string
    {
        return $this->donorName;
    }

    public function donorEmail(): ?string
    {
        return $this->donorEmail;
    }

    public function donorPan(): ?string
    {
        return $this->donorPan;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function donorAddress(): ?array
    {
        return $this->donorAddress;
    }

    public function amountMinor(): int
    {
        return $this->amountMinor;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function amountInWords(): ?string
    {
        return $this->amountInWords;
    }

    public function isTaxDeductible(): bool
    {
        return $this->isTaxDeductible;
    }

    public function tax80gEligible(): bool
    {
        return $this->tax80gEligible;
    }

    public function tax80gCertificateNumber(): ?string
    {
        return $this->tax80gCertificateNumber;
    }

    public function contentHash(): string
    {
        return $this->contentHash;
    }

    public function state(): string
    {
        return $this->state;
    }

    public function generatedAt(): DateTimeImmutable
    {
        return $this->generatedAt;
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

    /**
     * @return array<string, mixed>
     */
    public function deliveryMetadata(): array
    {
        return $this->deliveryMetadata;
    }

    public function receiptFileId(): ?EntityId
    {
        return $this->receiptFileId;
    }

    public function certificate80gFileId(): ?EntityId
    {
        return $this->certificate80gFileId;
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
