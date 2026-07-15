<?php

declare(strict_types=1);

namespace App\Payments\Domain\DTOs;

use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Exceptions\RefundExceededException;
use App\Shared\ValueObjects\Identifier;
use InvalidArgumentException;

/**
 * Request to refund (partially or fully) a verified payment.
 *
 * Construction validates amount positivity and currency code; refund
 * ceilings against amount_captured_minor are NOT checked here —
 * that belongs to the RefundService (Pass 1.3 / future) so this VO
 * stays a pure data carrier.
 */
final readonly class RefundRequestDTO
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        private Identifier $transactionId,
        private int $amountMinor,
        private Currency $currency,
        private ?string $reason = null,
        private ?string $idempotencyKey = null,
        private array $metadata = [],
    ) {
        if ($amountMinor <= 0) {
            throw new InvalidArgumentException(
                'RefundRequest amountMinor must be positive (got ' . $amountMinor . ')'
            );
        }
        if (empty($idempotencyKey) && strlen($idempotencyKey ?? '') > 0) {
            throw new InvalidArgumentException('RefundRequest idempotencyKey cannot be empty string');
        }
    }

    public function transactionId(): Identifier
    {
        return $this->transactionId;
    }

    public function amountMinor(): int
    {
        return $this->amountMinor;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }

    public function idempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    public function metadata(): array
    {
        return $this->metadata;
    }

    public function hasReason(): bool
    {
        return $this->reason !== null && $this->reason !== '';
    }

    public function hasIdempotencyKey(): bool
    {
        return $this->idempotencyKey !== null && $this->idempotencyKey !== '';
    }

    /**
     * Pre-flight check against an already-captured payment.
     * Returns null if the refund is within bounds.
     * Throws RefundExceededException otherwise.
     */
    public function checkAgainstCaptured(int $capturedMinor, int $alreadyRefundedMinor): ?RefundExceededException
    {
        $remaining = $capturedMinor - $alreadyRefundedMinor;
        if ($this->amountMinor > $remaining) {
            return RefundExceededException::exceedsCaptured(
                $this->transactionId->value(),
                $capturedMinor,
                $alreadyRefundedMinor,
                $this->amountMinor,
            );
        }

        return null;
    }

    public function toArray(): array
    {
        return [
            'transaction_id' => $this->transactionId->value(),
            'amount_minor' => $this->amountMinor,
            'currency' => $this->currency->value,
            'reason' => $this->reason,
            'idempotency_key' => $this->idempotencyKey,
            'metadata' => $this->metadata,
        ];
    }
}
