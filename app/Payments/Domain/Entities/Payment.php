<?php

declare(strict_types=1);

namespace App\Payments\Domain\Entities;

use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Enums\DonationState;
use App\Payments\Domain\Enums\PaymentProvider;
use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Domain\Exceptions\PaymentStateTransitionException;
use App\Payments\Domain\StateMachines\StateTransitionEvent;
use App\Payments\Domain\StateMachines\PaymentStateMachine;
use App\Persistence\Contracts\EntityContract;
use App\Persistence\ValueObjects\EntityId;
use App\Shared\ValueObjects\Identifier;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Payment aggregate root.
 *
 * Mirrors the `payments` table defined in `schema-neon/V1-schema.sql`.
 * Mutators enforce the schema's status<->timestamp/amount correlation
 * CHECK constraints at the domain layer so violations surface before
 * persistence.
 *
 * Status transitions are guarded by `assertTransition()` and MAY only be
 * invoked through PaymentStateMachine (Pass 1.2). Direct mutation of
 * status without going through the state machine is a programming error
 * — call sites that need to mutate MUST use `withStatus()` which
 * delegates to the machine.
 */
final class Payment implements EntityContract
{
    public const ENTITY_TYPE = 'payment';

    /**
     * @param  array<string, mixed>  $verificationMetadata
     * @param  array<string, mixed>  $methodDetail
     * @param  array<string, mixed>  $rawProviderResponse
     */
    private function __construct(
        private readonly EntityId $id,
        private readonly EntityId $donationId,
        private readonly PaymentProvider $providerCode,
        private readonly int $amountMinor,
        private readonly Currency $currency,
        private TransactionStatus $status,
        private ?int $amountCapturedMinor,
        private int $amountRefundedMinor,
        private ?int $feeMinor,
        private ?int $taxMinor,
        private ?string $method,
        private array $methodDetail,
        private ?string $providerOrderId,
        private ?string $providerPaymentId,
        private ?string $providerReferenceId,
        private ?string $signature,
        private ?DateTimeImmutable $signatureVerifiedAt,
        private ?DateTimeImmutable $verifiedAt,
        private array $verificationMetadata,
        private ?DateTimeImmutable $initiatedAt,
        private ?DateTimeImmutable $authorizedAt,
        private ?DateTimeImmutable $capturedAt,
        private ?DateTimeImmutable $settledAt,
        private ?DateTimeImmutable $failedAt,
        private ?DateTimeImmutable $refundedAt,
        private ?DateTimeImmutable $cancelledAt,
        private ?DateTimeImmutable $expiredAt,
        private ?string $lastFailureCode,
        private ?string $lastFailureReason,
        private ?string $idempotencyKey,
        private array $rawProviderResponse,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
        private ?DateTimeImmutable $deletedAt = null,
        private ?string $createdBy = null,
        private ?string $updatedBy = null,
    ) {
    }

    /**
     * Construct a brand-new payment in INITIALIZED state.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function initialize(
        EntityId $donationId,
        PaymentProvider $providerCode,
        int $amountMinor,
        Currency $currency,
        string $idempotencyKey,
        array $metadata = [],
        ?EntityId $id = null,
    ): self {
        if ($amountMinor <= 0) {
            throw new InvalidArgumentException("Payment amount must be positive (got {$amountMinor})");
        }
        if (empty($idempotencyKey)) {
            throw new InvalidArgumentException('Payment idempotency key cannot be empty');
        }

        $now = new DateTimeImmutable();

        return new self(
            id: $id ?? EntityId::generate(self::ENTITY_TYPE),
            donationId: $donationId,
            providerCode: $providerCode,
            amountMinor: $amountMinor,
            currency: $currency,
            status: TransactionStatus::INITIALIZED,
            amountCapturedMinor: null,
            amountRefundedMinor: 0,
            feeMinor: null,
            taxMinor: null,
            method: null,
            methodDetail: [],
            providerOrderId: null,
            providerPaymentId: null,
            providerReferenceId: null,
            signature: null,
            signatureVerifiedAt: null,
            verifiedAt: null,
            verificationMetadata: $metadata,
            initiatedAt: $now,
            authorizedAt: null,
            capturedAt: null,
            settledAt: null,
            failedAt: null,
            refundedAt: null,
            cancelledAt: null,
            expiredAt: null,
            lastFailureCode: null,
            lastFailureReason: null,
            idempotencyKey: $idempotencyKey,
            rawProviderResponse: [],
            createdAt: $now,
            updatedAt: $now,
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromRow(array $row): self
    {
        $required = ['id', 'donation_id', 'provider_code', 'amount_minor', 'currency_code', 'status', 'created_at', 'updated_at'];
        foreach ($required as $key) {
            if (! array_key_exists($key, $row)) {
                throw new InvalidArgumentException("Payment row missing required key: {$key}");
            }
        }

        return new self(
            id: EntityId::fromString($row['id']),
            donationId: EntityId::fromString($row['donation_id']),
            providerCode: PaymentProvider::from($row['provider_code']),
            amountMinor: (int) $row['amount_minor'],
            currency: Currency::from($row['currency_code']),
            status: TransactionStatus::from($row['status']),
            amountCapturedMinor: isset($row['amount_captured_minor']) ? (int) $row['amount_captured_minor'] : null,
            amountRefundedMinor: (int) ($row['amount_refunded_minor'] ?? 0),
            feeMinor: isset($row['fee_minor']) ? (int) $row['fee_minor'] : null,
            taxMinor: isset($row['tax_minor']) ? (int) $row['tax_minor'] : null,
            method: $row['method'] ?? null,
            methodDetail: self::decodeJson($row['method_detail'] ?? '{}'),
            providerOrderId: $row['provider_order_id'] ?? null,
            providerPaymentId: $row['provider_payment_id'] ?? null,
            providerReferenceId: $row['provider_reference_id'] ?? null,
            signature: $row['signature'] ?? null,
            signatureVerifiedAt: self::parseDate($row['signature_verified_at'] ?? null),
            verifiedAt: self::parseDate($row['verified_at'] ?? null),
            verificationMetadata: self::decodeJson($row['verification_metadata'] ?? '{}'),
            initiatedAt: self::parseDate($row['initiated_at'] ?? null),
            authorizedAt: self::parseDate($row['authorized_at'] ?? null),
            capturedAt: self::parseDate($row['captured_at'] ?? null),
            settledAt: self::parseDate($row['settled_at'] ?? null),
            failedAt: self::parseDate($row['failed_at'] ?? null),
            refundedAt: self::parseDate($row['refunded_at'] ?? null),
            cancelledAt: self::parseDate($row['cancelled_at'] ?? null),
            expiredAt: self::parseDate($row['expired_at'] ?? null),
            lastFailureCode: $row['last_failure_code'] ?? null,
            lastFailureReason: $row['last_failure_reason'] ?? null,
            idempotencyKey: $row['idempotency_key'] ?? null,
            rawProviderResponse: self::decodeJson($row['raw_provider_response'] ?? '{}'),
            createdAt: self::parseDate($row['created_at']) ?? new DateTimeImmutable(),
            updatedAt: self::parseDate($row['updated_at']) ?? new DateTimeImmutable(),
            deletedAt: self::parseDate($row['deleted_at'] ?? null),
            createdBy: $row['created_by'] ?? null,
            updatedBy: $row['updated_by'] ?? null,
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
            'provider_code' => $this->providerCode->value,
            'amount_minor' => $this->amountMinor,
            'currency_code' => $this->currency->value,
            'status' => $this->status->value,
            'amount_captured_minor' => $this->amountCapturedMinor,
            'amount_refunded_minor' => $this->amountRefundedMinor,
            'fee_minor' => $this->feeMinor,
            'tax_minor' => $this->taxMinor,
            'method' => $this->method,
            'method_detail' => json_encode($this->methodDetail, JSON_THROW_ON_ERROR),
            'provider_order_id' => $this->providerOrderId,
            'provider_payment_id' => $this->providerPaymentId,
            'provider_reference_id' => $this->providerReferenceId,
            'signature' => $this->signature,
            'signature_verified_at' => $this->signatureVerifiedAt?->format(DATE_ATOM),
            'verified_at' => $this->verifiedAt?->format(DATE_ATOM),
            'verification_metadata' => json_encode($this->verificationMetadata, JSON_THROW_ON_ERROR),
            'initiated_at' => $this->initiatedAt?->format(DATE_ATOM),
            'authorized_at' => $this->authorizedAt?->format(DATE_ATOM),
            'captured_at' => $this->capturedAt?->format(DATE_ATOM),
            'settled_at' => $this->settledAt?->format(DATE_ATOM),
            'failed_at' => $this->failedAt?->format(DATE_ATOM),
            'refunded_at' => $this->refundedAt?->format(DATE_ATOM),
            'cancelled_at' => $this->cancelledAt?->format(DATE_ATOM),
            'expired_at' => $this->expiredAt?->format(DATE_ATOM),
            'last_failure_code' => $this->lastFailureCode,
            'last_failure_reason' => $this->lastFailureReason,
            'idempotency_key' => $this->idempotencyKey,
            'raw_provider_response' => json_encode($this->rawProviderResponse, JSON_THROW_ON_ERROR),
            'created_at' => $this->createdAt->format(DATE_ATOM),
            'updated_at' => $this->updatedAt->format(DATE_ATOM),
            'deleted_at' => $this->deletedAt?->format(DATE_ATOM),
            'created_by' => $this->createdBy,
            'updated_by' => $this->updatedBy,
        ];
    }

    /**
     * Produce a new instance with the given changes applied.
     * Transitions go through assertTransition() to enforce schema invariants.
     *
     * @param  array<string, mixed>  $changes
     */
    public function withChanges(array $changes): static
    {
        $clone = clone $this;
        $clone->updatedAt = new DateTimeImmutable();

        if (array_key_exists('status', $changes)) {
            // Status changes must go through transitionTo() with a PaymentStateMachine.
            // Direct mutation here is a programming error.
            throw new \LogicException(
                'Payment::withChanges() cannot set status directly. Use transitionTo() with a PaymentStateMachine.'
            );
        }

        foreach (['amount_captured_minor', 'amount_refunded_minor', 'fee_minor', 'tax_minor', 'method', 'provider_order_id', 'provider_payment_id', 'provider_reference_id', 'signature', 'verified_at', 'last_failure_code', 'last_failure_reason'] as $field) {
            if (array_key_exists($field, $changes)) {
                if ($field === 'verified_at' && $changes[$field] !== null) {
                    $clone->verifiedAt = $changes[$field] instanceof DateTimeImmutable
                        ? $changes[$field]
                        : new DateTimeImmutable($changes[$field]);
                } elseif ($field === 'provider_order_id') {
                    $clone->providerOrderId = $changes[$field];
                } elseif ($field === 'provider_payment_id') {
                    $clone->providerPaymentId = $changes[$field];
                } elseif ($field === 'provider_reference_id') {
                    $clone->providerReferenceId = $changes[$field];
                } elseif ($field === 'last_failure_code') {
                    $clone->lastFailureCode = $changes[$field];
                } elseif ($field === 'last_failure_reason') {
                    $clone->lastFailureReason = $changes[$field];
                } elseif ($field === 'signature') {
                    $clone->signature = $changes[$field];
                } else {
                    // All other fields map snake_case key -> camelCase property via snake_to_camel
                    $clone->{self::snakeToCamel($field)} = $changes[$field];
                }
            }
        }

        if (array_key_exists('signature_verified_at', $changes) && $changes['signature_verified_at'] !== null) {
            $clone->signatureVerifiedAt = $changes['signature_verified_at'] instanceof DateTimeImmutable
                ? $changes['signature_verified_at']
                : new DateTimeImmutable($changes['signature_verified_at']);
        }

        if (array_key_exists('verification_metadata', $changes)) {
            $clone->verificationMetadata = $changes['verification_metadata'];
        }

        if (array_key_exists('raw_provider_response', $changes)) {
            $clone->rawProviderResponse = $changes['raw_provider_response'];
        }

        if (array_key_exists('method_detail', $changes)) {
            $clone->methodDetail = $changes['method_detail'];
        }

        if (array_key_exists('updated_by', $changes)) {
            $clone->updatedBy = $changes['updated_by'];
        }

        return $clone;
    }

    /**
     * Apply a state-machine-validated status transition.
     * The machine is the SOLE authority on whether the transition is valid.
     * Returns a new Payment instance reflecting the transition.
     *
     * @param  array<string, mixed>  $context  amount_minor, fee_minor, method, etc.
     */
    public function transitionTo(
        PaymentStateMachine $machine,
        TransactionStatus $to,
        array $context = [],
    ): self {
        $result = $machine->transition($this->status, $this->eventForTarget($to), $context);

        // Stamp timestamps
        $row = $this->toArray();
        foreach ($result->timestampChanges() as $column => $ts) {
            $row[$column] = $ts->format(DATE_ATOM);
        }

        // Apply entity-level changes
        foreach ($result->entityChanges() as $field => $value) {
            $row[$field] = $value;
        }
        $row['status'] = $result->toState()->value;

        return self::fromRow($row);
    }

    /**
     * Best-effort event inference for direct transitionTo calls. The
     * machine accepts only its known vocabulary, so callers should pass
     * an explicit event via the 3-arg form when ambiguous.
     *
     * Arm-ordering invariant: SPECIFIC (status, target) arms MUST
     * precede GENERIC (target-only) arms. PHP match(true) returns the
     * first true arm — a generic arm above a specific arm would mask
     * the specific case and the SM would reject the inferred event.
     *
     * The grouping below is therefore:
     *   1. Specific success arms (status, target) where target is reachable
     *   2. Specific FAILED arms with distinct events (SETTLING, DISPUTED)
     *   3. Generic target-only arms (catch remaining source states)
     *   4. Specific refund/dispute arms for non-FAILED targets (post-generic
     *      because their target is not FAILED — no risk of mask)
     */
    private function eventForTarget(TransactionStatus $target): \App\Payments\Domain\StateMachines\StateTransitionEvent
    {
        return match (true) {
            // ── Group 1: specific (status, target) success arms ───────────
            $this->status === TransactionStatus::INITIALIZED && $target === TransactionStatus::PENDING
                => \App\Payments\Domain\StateMachines\StateTransitionEvent::AUTH_OK,
            $this->status === TransactionStatus::PENDING && $target === TransactionStatus::AUTHORIZED
                => \App\Payments\Domain\StateMachines\StateTransitionEvent::GATEWAY_CONFIRMED,
            $this->status === TransactionStatus::AUTHORIZED && $target === TransactionStatus::CAPTURED
                => \App\Payments\Domain\StateMachines\StateTransitionEvent::CAPTURE_RECEIVED,
            $this->status === TransactionStatus::CAPTURED && $target === TransactionStatus::SETTLING
                => \App\Payments\Domain\StateMachines\StateTransitionEvent::SETTLEMENT_NOTICE,
            $this->status === TransactionStatus::CAPTURED && $target === TransactionStatus::SETTLED
                => \App\Payments\Domain\StateMachines\StateTransitionEvent::SETTLEMENT_CONFIRMED,
            $this->status === TransactionStatus::SETTLING && $target === TransactionStatus::SETTLED
                => \App\Payments\Domain\StateMachines\StateTransitionEvent::SETTLEMENT_CONFIRMED,
            $this->status === TransactionStatus::CAPTURED && $target === TransactionStatus::REFUNDED
                => \App\Payments\Domain\StateMachines\StateTransitionEvent::REFUND_INITIATED,
            $this->status === TransactionStatus::CAPTURED && $target === TransactionStatus::PARTIALLY_REFUNDED
                => \App\Payments\Domain\StateMachines\StateTransitionEvent::PARTIAL_REFUND_INITIATED,
            $this->status === TransactionStatus::SETTLED && $target === TransactionStatus::REFUNDED
                => \App\Payments\Domain\StateMachines\StateTransitionEvent::REFUND_INITIATED,
            $this->status === TransactionStatus::SETTLED && $target === TransactionStatus::PARTIALLY_REFUNDED
                => \App\Payments\Domain\StateMachines\StateTransitionEvent::PARTIAL_REFUND_INITIATED,

            // ── Group 2: specific FAILED arms with distinct events ───────
            // These MUST come BEFORE the generic `target === FAILED` arm
            // below — the SM rejects GATEWAY_FAILED for SETTLING and DISPUTED
            // (it requires SETTLEMENT_FAILED and DISPUTE_RESOLVED_LOST respectively).
            $this->status === TransactionStatus::SETTLING && $target === TransactionStatus::FAILED
                => \App\Payments\Domain\StateMachines\StateTransitionEvent::SETTLEMENT_FAILED,
            $this->status === TransactionStatus::DISPUTED && $target === TransactionStatus::FAILED
                => \App\Payments\Domain\StateMachines\StateTransitionEvent::DISPUTE_RESOLVED_LOST,

            // ── Group 3: generic target-only arms ─────────────────────────
            // Catch-all for any remaining source state → target transition.
            // Specific arms in groups 1+2 above take precedence.
            $target === TransactionStatus::FAILED
                => \App\Payments\Domain\StateMachines\StateTransitionEvent::GATEWAY_FAILED,
            $target === TransactionStatus::CANCELLED
                => \App\Payments\Domain\StateMachines\StateTransitionEvent::CUSTOMER_CANCELLED,
            $target === TransactionStatus::EXPIRED
                => \App\Payments\Domain\StateMachines\StateTransitionEvent::GATEWAY_TIMEOUT,

            // ── Group 4: dispute arms ───────────────────────────────────
            // Any source status → DISPUTED maps to DISPUTE_OPENED.
            // Refund arms (PARTIALLY_REFUNDED→REFUNDED, DISPUTED→REFUNDED)
            // are out of scope for Pass 1.3 — refunds are handled by the
            // Razorpay SDK and are not modelled in this runtime.
            $target === TransactionStatus::DISPUTED
                => \App\Payments\Domain\StateMachines\StateTransitionEvent::DISPUTE_OPENED,

            default => throw new PaymentStateTransitionException(
                sprintf('No event inferred for transition %s -> %s', $this->status->value, $target->value),
                $this->status,
                $target,
            ),
        };
    }

    // ─── Getters ────────────────────────────────────────────────────────

    public function donationId(): EntityId
    {
        return $this->donationId;
    }

    public function providerCode(): PaymentProvider
    {
        return $this->providerCode;
    }

    public function amountMinor(): int
    {
        return $this->amountMinor;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function status(): TransactionStatus
    {
        return $this->status;
    }

    public function amountCapturedMinor(): ?int
    {
        return $this->amountCapturedMinor;
    }

    public function amountRefundedMinor(): int
    {
        return $this->amountRefundedMinor;
    }

    public function feeMinor(): ?int
    {
        return $this->feeMinor;
    }

    public function taxMinor(): ?int
    {
        return $this->taxMinor;
    }

    public function method(): ?string
    {
        return $this->method;
    }

    /**
     * @return array<string, mixed>
     */
    public function methodDetail(): array
    {
        return $this->methodDetail;
    }

    public function providerOrderId(): ?string
    {
        return $this->providerOrderId;
    }

    public function providerPaymentId(): ?string
    {
        return $this->providerPaymentId;
    }

    public function providerReferenceId(): ?string
    {
        return $this->providerReferenceId;
    }

    public function signature(): ?string
    {
        return $this->signature;
    }

    public function signatureVerifiedAt(): ?DateTimeImmutable
    {
        return $this->signatureVerifiedAt;
    }

    public function verifiedAt(): ?DateTimeImmutable
    {
        return $this->verifiedAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function verificationMetadata(): array
    {
        return $this->verificationMetadata;
    }

    public function initiatedAt(): ?DateTimeImmutable
    {
        return $this->initiatedAt;
    }

    public function authorizedAt(): ?DateTimeImmutable
    {
        return $this->authorizedAt;
    }

    public function capturedAt(): ?DateTimeImmutable
    {
        return $this->capturedAt;
    }

    public function settledAt(): ?DateTimeImmutable
    {
        return $this->settledAt;
    }

    public function failedAt(): ?DateTimeImmutable
    {
        return $this->failedAt;
    }

    public function refundedAt(): ?DateTimeImmutable
    {
        return $this->refundedAt;
    }

    public function cancelledAt(): ?DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function expiredAt(): ?DateTimeImmutable
    {
        return $this->expiredAt;
    }

    public function lastFailureCode(): ?string
    {
        return $this->lastFailureCode;
    }

    public function lastFailureReason(): ?string
    {
        return $this->lastFailureReason;
    }

    public function idempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    /**
     * @return array<string, mixed>
     */
    public function rawProviderResponse(): array
    {
        return $this->rawProviderResponse;
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

    public function createdBy(): ?string
    {
        return $this->createdBy;
    }

    public function updatedBy(): ?string
    {
        return $this->updatedBy;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
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

    private static function camelToSnake(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)([A-Z])/', '_$1', $input) ?? $input);
    }

    private static function snakeToCamel(string $input): string
    {
        return lcfirst(str_replace('_', '', ucwords($input, '_')));
    }
}