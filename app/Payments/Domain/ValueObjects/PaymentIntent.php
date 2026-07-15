<?php

declare(strict_types=1);

namespace App\Payments\Domain\ValueObjects;

use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Enums\PaymentProvider;
use App\Shared\Exceptions\ValidationFailedException;
use App\Shared\ValueObjects\Identifier;
use InvalidArgumentException;

/**
 * Internal, provider-agnostic representation of a payment about to be initiated.
 *
 * PaymentIntent is constructed by the application layer (typically the
 * DonationService) and is consumed by the PaymentService and
 * PaymentProviderSelector. It carries NO provider-specific shape — every
 * gateway adapter translates a PaymentIntent into its own SDK request.
 *
 * The class is immutable. Provider selection is performed lazily by the
 * selector; this VO carries the candidate set as a hint.
 */
final class PaymentIntent
{
    /**
     * @param  array<int, PaymentProvider>  $candidateProviders  Ordered fallback list (lowest priority first)
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        private readonly Identifier $donationId,
        private readonly DonorIdentity $donor,
        private readonly int $amountMinor,
        private readonly Currency $currency,
        private readonly string $purpose,
        private readonly string $idempotencyKey,
        private readonly array $candidateProviders = [],
        private readonly array $metadata = [],
    ) {
        if ($amountMinor <= 0) {
            throw new InvalidArgumentException(
                "PaymentIntent amount must be positive (got {$amountMinor})"
            );
        }
        if (empty($purpose)) {
            throw new InvalidArgumentException('PaymentIntent purpose cannot be empty');
        }
        if (empty($idempotencyKey)) {
            throw new InvalidArgumentException('PaymentIntent idempotency key cannot be empty');
        }
    }

    public static function fromValidated(
        Identifier $donationId,
        DonorIdentity $donor,
        int $amountMinor,
        Currency $currency,
        string $purpose,
        string $idempotencyKey,
        array $candidateProviders = [],
        array $metadata = [],
    ): self {
        // Construction-time validation throws InvalidArgumentException for
        // shape errors. ValidationFailedException is reserved for business
        // rule violations surfaced to the controller layer.
        return new self(
            $donationId,
            $donor,
            $amountMinor,
            $currency,
            $purpose,
            $idempotencyKey,
            $candidateProviders,
            $metadata,
        );
    }

    public function donationId(): Identifier
    {
        return $this->donationId;
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

    public function purpose(): string
    {
        return $this->purpose;
    }

    public function idempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    /**
     * @return array<int, PaymentProvider>
     */
    public function candidateProviders(): array
    {
        return $this->candidateProviders;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * Whether the donor must remain anonymous for this intent.
     */
    public function isAnonymous(): bool
    {
        return $this->donor->isAnonymous();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'donation_id' => $this->donationId->value(),
            'donor' => $this->donor->toArray(),
            'amount_minor' => $this->amountMinor,
            'currency' => $this->currency->value,
            'purpose' => $this->purpose,
            'idempotency_key' => $this->idempotencyKey,
            'candidate_providers' => array_map(
                static fn (PaymentProvider $p) => $p->value,
                $this->candidateProviders,
            ),
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Apply additional metadata without mutating the instance.
     *
     * @param  array<string, mixed>  $extra
     */
    public function withMetadata(array $extra): self
    {
        return new self(
            $this->donationId,
            $this->donor,
            $this->amountMinor,
            $this->currency,
            $this->purpose,
            $this->idempotencyKey,
            $this->candidateProviders,
            array_merge($this->metadata, $extra),
        );
    }
}