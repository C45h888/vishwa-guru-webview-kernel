<?php

declare(strict_types=1);

namespace App\Payments\ValueObjects;

use App\Payments\Enums\Currency;
use App\Shared\ValueObjects\Identifier;
use InvalidArgumentException;

/**
 * Represents an immutable payment request.
 * Used to initialize a transaction at the gateway.
 *
 * Contains everything needed to start a payment:
 *   - who is paying (donor)
 *   - how much (amount in minor units)
 *   - what currency
 *   - what for (purpose)
 *   - where the gateway should redirect on success/failure
 */
final class PaymentRequest
{
    /**
     * @param Identifier $donorIdentifier The donor's identifier
     * @param int $amount Amount in MINOR units (e.g. paise for INR)
     * @param Currency $currency
     * @param string $purpose Free-text purpose (e.g. "General Donation")
     * @param array<string, mixed> $metadata Additional gateway-specific metadata
     * @param string|null $successUrl Gateway redirect URL on success
     * @param string|null $failureUrl Gateway redirect URL on failure
     * @param string|null $idempotencyKey Idempotency key for safe retries
     */
    public function __construct(
        private readonly Identifier $donorIdentifier,
        private readonly int $amount,
        private readonly Currency $currency,
        private readonly string $purpose,
        private readonly array $metadata = [],
        private readonly ?string $successUrl = null,
        private readonly ?string $failureUrl = null,
        private readonly ?string $idempotencyKey = null,
    ) {
        if ($amount <= 0) {
            throw new InvalidArgumentException(
                "Payment amount must be positive (got {$amount})"
            );
        }
        if (empty($purpose)) {
            throw new InvalidArgumentException(
                "Payment purpose cannot be empty"
            );
        }
    }

    public function donorIdentifier(): Identifier
    {
        return $this->donorIdentifier;
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function purpose(): string
    {
        return $this->purpose;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    public function successUrl(): ?string
    {
        return $this->successUrl;
    }

    public function failureUrl(): ?string
    {
        return $this->failureUrl;
    }

    public function idempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'donor_id' => $this->donorIdentifier->value(),
            'amount' => $this->amount,
            'currency' => $this->currency->value,
            'purpose' => $this->purpose,
            'metadata' => $this->metadata,
            'success_url' => $this->successUrl,
            'failure_url' => $this->failureUrl,
            'idempotency_key' => $this->idempotencyKey,
        ];
    }
}