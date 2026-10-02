<?php

declare(strict_types=1);

namespace App\Payments\Domain\Entities;

use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Enums\DonationState;
use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Domain\Exceptions\PaymentStateTransitionException;
use App\Payments\Domain\StateMachines\StateTransitionEvent;
use App\Payments\Domain\StateMachines\DonationStateMachine;
use App\Payments\Domain\ValueObjects\CheckoutPolicyAcceptance;
use App\Payments\Domain\ValueObjects\DonorIdentity;
use App\Payments\Domain\ValueObjects\MarketingEmailConsent;
use App\Persistence\Contracts\EntityContract;
use App\Persistence\ValueObjects\EntityId;
use App\Shared\ValueObjects\Identifier;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Donation aggregate root.
 *
 * Mirrors the `donations` table defined in `schema-neon/V1-schema.sql`.
 * Mutators enforce the schema's state<->timestamp correlation CHECK
 * constraints and the anonymous-no-PII CHECK constraint at the domain
 * layer.
 *
 * Donation state transitions are decided by the PaymentStateMachine
 * (Pass 1.2). Use withChanges() to apply changes; direct mutation is
 * prevented.
 */
final class Donation implements EntityContract
{
    public const ENTITY_TYPE = 'donation';

    /**
     * @param  array<string, string>|null  $donorAddressSnapshot
     * @param  array<string, mixed>  $metadata
     */
    private function __construct(
        private readonly EntityId $id,
        private readonly EntityId $campaignId,
        private readonly ?EntityId $donorId,
        private readonly DonationState $state,
        private readonly int $amountMinor,
        private readonly Currency $currency,
        private readonly bool $isAnonymous,
        private readonly ?string $donorNameSnapshot,
        private readonly ?string $donorEmailSnapshot,
        private readonly ?string $donorPhoneSnapshot,
        private readonly ?string $donorPanSnapshot,
        private readonly ?array $donorAddressSnapshot,
        private readonly ?string $dedication,
        private readonly ?string $donorMessage,
        private readonly ?string $internalNotes,
        private readonly ?string $idempotencyKey,
        private readonly array $metadata,
        private readonly ?CheckoutPolicyAcceptance $policyAcceptance,
        private readonly ?MarketingEmailConsent $marketingEmailConsent,
        private readonly ?DateTimeImmutable $submittedAt,
        private readonly ?DateTimeImmutable $paymentInitiatedAt,
        private readonly ?DateTimeImmutable $paymentVerifiedAt,
        private readonly ?DateTimeImmutable $receiptGeneratedAt,
        private readonly ?DateTimeImmutable $completedAt,
        private readonly ?DateTimeImmutable $failedAt,
        private readonly ?DateTimeImmutable $cancelledAt,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt,
        private readonly ?DateTimeImmutable $deletedAt = null,
        private readonly ?string $createdBy = null,
        private readonly ?string $updatedBy = null,
    ) {
    }

    /**
     * Construct a new donation in DRAFT state.
     */
    public static function draft(
        EntityId $campaignId,
        DonorIdentity $donor,
        int $amountMinor,
        Currency $currency,
        ?EntityId $donorId = null,
        ?string $dedication = null,
        ?string $donorMessage = null,
        ?string $internalNotes = null,
        ?string $idempotencyKey = null,
        array $metadata = [],
        ?EntityId $id = null,
        ?CheckoutPolicyAcceptance $policyAcceptance = null,
        ?MarketingEmailConsent $marketingEmailConsent = null,
    ): self {
        if ($amountMinor <= 0) {
            throw new InvalidArgumentException("Donation amount must be positive (got {$amountMinor})");
        }
        self::assertPiiConsistency($donor, $dedication, $donorMessage, $marketingEmailConsent);

        $now = new DateTimeImmutable();

        return new self(
            id: $id ?? EntityId::generate(self::ENTITY_TYPE),
            campaignId: $campaignId,
            donorId: $donorId,
            state: DonationState::DRAFT,
            amountMinor: $amountMinor,
            currency: $currency,
            isAnonymous: $donor->isAnonymous(),
            donorNameSnapshot: $donor->name(),
            donorEmailSnapshot: $donor->email(),
            donorPhoneSnapshot: $donor->phone(),
            donorPanSnapshot: $donor->pan(),
            donorAddressSnapshot: $donor->address(),
            dedication: $dedication,
            donorMessage: $donorMessage,
            internalNotes: $internalNotes,
            idempotencyKey: $idempotencyKey,
            metadata: $metadata,
            policyAcceptance: $policyAcceptance,
            marketingEmailConsent: $marketingEmailConsent,
            submittedAt: null,
            paymentInitiatedAt: null,
            paymentVerifiedAt: null,
            receiptGeneratedAt: null,
            completedAt: null,
            failedAt: null,
            cancelledAt: null,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromRow(array $row): self
    {
        $required = ['id', 'campaign_id', 'amount_minor', 'currency_code', 'state', 'is_anonymous', 'created_at', 'updated_at'];
        foreach ($required as $key) {
            if (! array_key_exists($key, $row)) {
                throw new InvalidArgumentException("Donation row missing required key: {$key}");
            }
        }

        $policyAcceptance = self::policyAcceptanceFromRow($row);
        $marketingEmailConsent = self::marketingEmailConsentFromRow($row);

        return new self(
            id: EntityId::fromString($row['id']),
            campaignId: EntityId::fromString($row['campaign_id']),
            donorId: isset($row['donor_id']) && $row['donor_id'] !== null
                ? EntityId::fromString($row['donor_id'])
                : null,
            state: DonationState::from($row['state']),
            amountMinor: (int) $row['amount_minor'],
            currency: Currency::from($row['currency_code']),
            isAnonymous: (bool) $row['is_anonymous'],
            donorNameSnapshot: $row['donor_name_snapshot'] ?? null,
            donorEmailSnapshot: $row['donor_email_snapshot'] ?? null,
            donorPhoneSnapshot: $row['donor_phone_snapshot'] ?? null,
            donorPanSnapshot: $row['donor_pan_snapshot'] ?? null,
            donorAddressSnapshot: isset($row['donor_address_snapshot'])
                ? self::decodeJson($row['donor_address_snapshot'])
                : null,
            dedication: $row['dedication'] ?? null,
            donorMessage: $row['donor_message'] ?? null,
            internalNotes: $row['internal_notes'] ?? null,
            idempotencyKey: $row['idempotency_key'] ?? null,
            metadata: self::decodeJson($row['metadata'] ?? '{}'),
            policyAcceptance: $policyAcceptance,
            marketingEmailConsent: $marketingEmailConsent,
            submittedAt: self::parseDate($row['submitted_at'] ?? null),
            paymentInitiatedAt: self::parseDate($row['payment_initiated_at'] ?? null),
            paymentVerifiedAt: self::parseDate($row['payment_verified_at'] ?? null),
            receiptGeneratedAt: self::parseDate($row['receipt_generated_at'] ?? null),
            completedAt: self::parseDate($row['completed_at'] ?? null),
            failedAt: self::parseDate($row['failed_at'] ?? null),
            cancelledAt: self::parseDate($row['cancelled_at'] ?? null),
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
            'campaign_id' => $this->campaignId->value(),
            'donor_id' => $this->donorId?->value(),
            'state' => $this->state->value,
            'amount_minor' => $this->amountMinor,
            'currency_code' => $this->currency->value,
            'is_anonymous' => $this->isAnonymous,
            'donor_name_snapshot' => $this->donorNameSnapshot,
            'donor_email_snapshot' => $this->donorEmailSnapshot,
            'donor_phone_snapshot' => $this->donorPhoneSnapshot,
            'donor_pan_snapshot' => $this->donorPanSnapshot,
            'donor_address_snapshot' => $this->donorAddressSnapshot !== null
                ? json_encode($this->donorAddressSnapshot, JSON_THROW_ON_ERROR)
                : null,
            'dedication' => $this->dedication,
            'donor_message' => $this->donorMessage,
            'internal_notes' => $this->internalNotes,
            'idempotency_key' => $this->idempotencyKey,
            'metadata' => json_encode($this->metadata, JSON_THROW_ON_ERROR),
            'terms_version' => $this->policyAcceptance?->termsVersion(),
            'terms_accepted_at' => $this->policyAcceptance?->termsAcceptedAt()->format(DATE_ATOM),
            'privacy_notice_version' => $this->policyAcceptance?->privacyVersion(),
            'privacy_notice_acknowledged_at' => $this->policyAcceptance?->privacyAcknowledgedAt()->format(DATE_ATOM),
            'marketing_email_consent_version' => $this->marketingEmailConsent?->consentVersion(),
            'marketing_email_consented_at' => $this->marketingEmailConsent?->consentedAt()->format(DATE_ATOM),
            'submitted_at' => $this->submittedAt?->format(DATE_ATOM),
            'payment_initiated_at' => $this->paymentInitiatedAt?->format(DATE_ATOM),
            'payment_verified_at' => $this->paymentVerifiedAt?->format(DATE_ATOM),
            'receipt_generated_at' => $this->receiptGeneratedAt?->format(DATE_ATOM),
            'completed_at' => $this->completedAt?->format(DATE_ATOM),
            'failed_at' => $this->failedAt?->format(DATE_ATOM),
            'cancelled_at' => $this->cancelledAt?->format(DATE_ATOM),
            'created_at' => $this->createdAt->format(DATE_ATOM),
            'updated_at' => $this->updatedAt->format(DATE_ATOM),
            'deleted_at' => $this->deletedAt?->format(DATE_ATOM),
            'created_by' => $this->createdBy,
            'updated_by' => $this->updatedBy,
        ];
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function withChanges(array $changes): static
    {
        $row = $this->toArray();
        $merged = array_merge($row, $changes);

        if (array_key_exists('state', $changes)) {
            throw new \LogicException(
                'Donation::withChanges() cannot set state directly. Use transitionTo() with a DonationStateMachine.'
            );
        }

        if (array_key_exists('donor_id', $changes)) {
            $merged['donor_id'] = $changes['donor_id'] instanceof EntityId
                ? $changes['donor_id']->value()
                : ($changes['donor_id'] ?? null);
        }

        return self::fromRow($merged);
    }

    /**
     * Apply a state-machine-validated donation state transition.
     * Returns a new Donation instance reflecting the transition.
     *
     * @param  array<string, mixed>  $context
     */
    public function transitionTo(
        DonationStateMachine $machine,
        DonationState $to,
        array $context = [],
    ): self {
        $result = $machine->transition($this->state, $this->eventForTarget($to), $context);

        $row = $this->toArray();
        foreach ($result->timestampChanges() as $column => $ts) {
            $row[$column] = $ts->format(DATE_ATOM);
        }
        foreach ($result->entityChanges() as $field => $value) {
            $row[$field] = $value;
        }
        $row['state'] = $result->toState()->value;

        return self::fromRow($row);
    }

    /**
     * Best-effort event inference for direct transitionTo calls.
     *
     * Arm-ordering invariant: SPECIFIC (state, target) arms MUST precede
     * GENERIC (target-only) arms. PHP match(true) returns the first true
     * arm — a generic arm above a specific arm would mask the specific
     * case and the SM would reject the inferred event.
     *
     * The grouping below is therefore:
     *   1. Specific success arms (state, target)
     *   2. Specific FAILED arms with distinct events (PAYMENT_VERIFIED,
     *      RECEIPT_GENERATED) — these MUST come BEFORE the generic
     *      `target === FAILED` arm below
     *   3. Generic target-only arms (catches DRAFT, PENDING_PAYMENT → FAILED,
     *      and CANCELLED)
     */
    private function eventForTarget(DonationState $target): StateTransitionEvent
    {
        return match (true) {
            // ── Group 1: specific success arms ───────────────────────────────
            $this->state === DonationState::DRAFT && $target === DonationState::PENDING_PAYMENT
                => StateTransitionEvent::SUBMITTED,
            $this->state === DonationState::PENDING_PAYMENT && $target === DonationState::PAYMENT_VERIFIED
                => StateTransitionEvent::GATEWAY_CONFIRMED,
            $this->state === DonationState::PAYMENT_VERIFIED && $target === DonationState::RECEIPT_GENERATED
                => StateTransitionEvent::RECEIPT_ISSUED,
            $this->state === DonationState::RECEIPT_GENERATED && $target === DonationState::COMPLETED
                => StateTransitionEvent::COMPLETED,

            // ── Group 2: specific FAILED arms with distinct events ────────
            // The SM requires RECEIPT_FAILED for PAYMENT_VERIFIED→FAILED and
            // POST_COMMIT_FAIL for RECEIPT_GENERATED→FAILED. The generic
            // GATEWAY_FAILED arm below only catches DRAFT/PENDING_PAYMENT→FAILED.
            $this->state === DonationState::PAYMENT_VERIFIED && $target === DonationState::FAILED
                => StateTransitionEvent::RECEIPT_FAILED,
            $this->state === DonationState::RECEIPT_GENERATED && $target === DonationState::FAILED
                => StateTransitionEvent::POST_COMMIT_FAIL,

            // ── Group 3: generic target-only arms ─────────────────────────
            $target === DonationState::FAILED
                => StateTransitionEvent::GATEWAY_FAILED,
            $target === DonationState::CANCELLED
                => StateTransitionEvent::CUSTOMER_CANCELLED,

            default => throw new PaymentStateTransitionException(
                sprintf('No event inferred for donation transition %s -> %s', $this->state->value, $target->value),
                DonationStateMachine::mapToTransactionStatus($this->state),
                DonationStateMachine::mapToTransactionStatus($target),
            ),
        };
    }

    private static function assertPiiConsistency(
        DonorIdentity $donor,
        ?string $dedication,
        ?string $donorMessage,
        ?MarketingEmailConsent $marketingEmailConsent,
    ): void {
        if ($marketingEmailConsent !== null && ($donor->isAnonymous() || ! $donor->hasEmail())) {
            throw new InvalidArgumentException(
                'Marketing email consent requires an identified donor with an email address'
            );
        }
        if (! $donor->isAnonymous()) {
            return;
        }
        if ($dedication !== null || ($donorMessage !== null && $donorMessage !== '')) {
            throw new InvalidArgumentException(
                'Anonymous donations cannot carry dedication or donor message'
            );
        }
    }

    // ─── Getters ────────────────────────────────────────────────────────

    public function campaignId(): EntityId
    {
        return $this->campaignId;
    }

    public function donorId(): ?EntityId
    {
        return $this->donorId;
    }

    public function state(): DonationState
    {
        return $this->state;
    }

    public function amountMinor(): int
    {
        return $this->amountMinor;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function isAnonymousFlag(): bool
    {
        return $this->isAnonymous;
    }

    public function donorNameSnapshot(): ?string
    {
        return $this->donorNameSnapshot;
    }

    public function donorEmailSnapshot(): ?string
    {
        return $this->donorEmailSnapshot;
    }

    public function donorPhoneSnapshot(): ?string
    {
        return $this->donorPhoneSnapshot;
    }

    public function donorPanSnapshot(): ?string
    {
        return $this->donorPanSnapshot;
    }

    /**
     * @return array<string, string>|null
     */
    public function donorAddressSnapshot(): ?array
    {
        return $this->donorAddressSnapshot;
    }

    public function dedication(): ?string
    {
        return $this->dedication;
    }

    public function donorMessage(): ?string
    {
        return $this->donorMessage;
    }

    public function internalNotes(): ?string
    {
        return $this->internalNotes;
    }

    public function idempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    public function policyAcceptance(): ?CheckoutPolicyAcceptance
    {
        return $this->policyAcceptance;
    }

    public function marketingEmailConsent(): ?MarketingEmailConsent
    {
        return $this->marketingEmailConsent;
    }

    public function submittedAt(): ?DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function paymentInitiatedAt(): ?DateTimeImmutable
    {
        return $this->paymentInitiatedAt;
    }

    public function paymentVerifiedAt(): ?DateTimeImmutable
    {
        return $this->paymentVerifiedAt;
    }

    public function receiptGeneratedAt(): ?DateTimeImmutable
    {
        return $this->receiptGeneratedAt;
    }

    public function completedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function failedAt(): ?DateTimeImmutable
    {
        return $this->failedAt;
    }

    public function cancelledAt(): ?DateTimeImmutable
    {
        return $this->cancelledAt;
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

    public function isAnonymous(): bool
    {
        return $this->isAnonymous;
    }

    public function isTerminal(): bool
    {
        return $this->state->isTerminal();
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

    /**
     * @param array<string, mixed> $row
     */
    private static function policyAcceptanceFromRow(array $row): ?CheckoutPolicyAcceptance
    {
        $termsVersion = $row['terms_version'] ?? null;
        $termsAcceptedAt = self::parseDate($row['terms_accepted_at'] ?? null);
        $privacyVersion = $row['privacy_notice_version'] ?? null;
        $privacyAcknowledgedAt = self::parseDate($row['privacy_notice_acknowledged_at'] ?? null);
        $values = [$termsVersion, $termsAcceptedAt, $privacyVersion, $privacyAcknowledgedAt];

        if (count(array_filter($values, static fn (mixed $value): bool => $value !== null)) === 0) {
            return null;
        }
        if (! is_string($termsVersion) || ! $termsAcceptedAt instanceof DateTimeImmutable
            || ! is_string($privacyVersion) || ! $privacyAcknowledgedAt instanceof DateTimeImmutable) {
            throw new InvalidArgumentException('Donation policy acceptance columns must be all set or all null');
        }

        return new CheckoutPolicyAcceptance(
            termsVersion: $termsVersion,
            termsAcceptedAt: $termsAcceptedAt,
            privacyVersion: $privacyVersion,
            privacyAcknowledgedAt: $privacyAcknowledgedAt,
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function marketingEmailConsentFromRow(array $row): ?MarketingEmailConsent
    {
        $version = $row['marketing_email_consent_version'] ?? null;
        $consentedAt = self::parseDate($row['marketing_email_consented_at'] ?? null);

        if ($version === null && $consentedAt === null) {
            return null;
        }
        if (! is_string($version) || ! $consentedAt instanceof DateTimeImmutable) {
            throw new InvalidArgumentException('Donation marketing consent columns must be both set or both null');
        }

        return new MarketingEmailConsent($version, $consentedAt);
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