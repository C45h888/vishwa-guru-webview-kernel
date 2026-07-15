<?php

declare(strict_types=1);

namespace App\Payments\Domain\ValueObjects;

use App\Payments\Domain\Enums\Currency;
use App\Shared\ValueObjects\Identifier;
use InvalidArgumentException;

/**
 * Intent to create a Donation. The DonationService constructs a Donation
 * from a DonationIntent and hands it to the PaymentService for gateway
 * initialization.
 *
 * DonationIntent captures the business intent (campaign, donor, amount).
 * Gateway-specific fields are added by PaymentIntent on the way to the
 * PaymentService.
 */
final class DonationIntent
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        private readonly Identifier $campaignId,
        private readonly DonorIdentity $donor,
        private readonly int $amountMinor,
        private readonly Currency $currency,
        private readonly ?string $dedication = null,
        private readonly ?string $donorMessage = null,
        private readonly ?string $internalNotes = null,
        private readonly ?string $idempotencyKey = null,
        private readonly array $metadata = [],
    ) {
        if ($amountMinor <= 0) {
            throw new InvalidArgumentException(
                "DonationIntent amount must be positive (got {$amountMinor})"
            );
        }
        if ($donor->isAnonymous() && ($dedication !== null || $donorMessage !== null)) {
            throw new InvalidArgumentException(
                'Anonymous donations cannot carry dedication or donor message'
            );
        }
    }

    public function campaignId(): Identifier
    {
        return $this->campaignId;
    }

    public function donor(): DonorIdentity
    {
        return $this->donor;
    }

    public function amountMinor(): int
    {
        return $this->amountMinor;
    }

    public function currency(): Currency
    {
        return $this->currency;
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

    public function isAnonymous(): bool
    {
        return $this->donor->isAnonymous();
    }

    public function hasDedication(): bool
    {
        return $this->dedication !== null && trim($this->dedication) !== '';
    }

    public function hasDonorMessage(): bool
    {
        return $this->donorMessage !== null && trim($this->donorMessage) !== '';
    }

    /**
     * Generate an idempotency key when none was supplied.
     * Combines campaign, donor fingerprint, amount, and currency.
     */
    public function resolveIdempotencyKey(): string
    {
        if ($this->idempotencyKey !== null) {
            return $this->idempotencyKey;
        }

        $fingerprint = hash('sha256', sprintf(
            '%s|%s|%d|%s',
            $this->campaignId->value(),
            $this->donor->email() ?? 'anon:'.$this->donor->phone() ?? 'anon:unknown',
            $this->amountMinor,
            $this->currency->value,
        ));

        return substr($fingerprint, 0, 32);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'campaign_id' => $this->campaignId->value(),
            'donor' => $this->donor->toArray(),
            'amount_minor' => $this->amountMinor,
            'currency' => $this->currency->value,
            'dedication' => $this->dedication,
            'donor_message' => $this->donorMessage,
            'internal_notes' => $this->internalNotes,
            'idempotency_key' => $this->resolveIdempotencyKey(),
            'metadata' => $this->metadata,
        ];
    }
}