<?php

declare(strict_types=1);

namespace App\Payments\Domain\ValueObjects;

use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Enums\PaymentProvider;
use App\Payments\Domain\Enums\TransactionStatus;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * The outcome of a verified gateway callback.
 *
 * PaymentVerification is the ONLY signal that the PaymentService treats as
 * authoritative truth about a transaction's state. Until a verification is
 * produced (signature checked, amount matched, duplicate-scanned), the
 * Payment MUST remain in status=INITIALIZED or PENDING.
 *
 * The verifiedAt timestamp is captured by the PaymentVerificationPipeline
 * and written verbatim to payments.verified_at.
 */
final class PaymentVerification
{
    /**
     * @param  array<string, mixed>  $metadata  Pipeline metadata (signature time, gateway latency, etc.)
     */
    public function __construct(
        private readonly PaymentProvider $provider,
        private readonly string $gatewayOrderId,
        private readonly string $gatewayPaymentId,
        private readonly TransactionStatus $status,
        private readonly int $amountMinor,
        private readonly Currency $currency,
        private readonly DateTimeImmutable $verifiedAt,
        private readonly ?string $method = null,
        private readonly array $metadata = [],
    ) {
        if (empty($gatewayOrderId)) {
            throw new InvalidArgumentException('PaymentVerification gatewayOrderId cannot be empty');
        }
        if (empty($gatewayPaymentId)) {
            throw new InvalidArgumentException('PaymentVerification gatewayPaymentId cannot be empty');
        }
        if ($amountMinor <= 0) {
            throw new InvalidArgumentException(
                "PaymentVerification amount must be positive (got {$amountMinor})"
            );
        }
        if (! $status->isSuccessful() && ! in_array($status, [
            TransactionStatus::FAILED,
            TransactionStatus::CANCELLED,
            TransactionStatus::EXPIRED,
        ], true)) {
            throw new InvalidArgumentException(
                "PaymentVerification status [{$status->value}] is not a verifiable terminal status"
            );
        }
    }

    public function provider(): PaymentProvider
    {
        return $this->provider;
    }

    public function gatewayOrderId(): string
    {
        return $this->gatewayOrderId;
    }

    public function gatewayPaymentId(): string
    {
        return $this->gatewayPaymentId;
    }

    public function status(): TransactionStatus
    {
        return $this->status;
    }

    public function amountMinor(): int
    {
        return $this->amountMinor;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function verifiedAt(): DateTimeImmutable
    {
        return $this->verifiedAt;
    }

    public function method(): ?string
    {
        return $this->method;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    public function isSuccess(): bool
    {
        return $this->status->isSuccessful();
    }

    public function isFailure(): bool
    {
        return $this->status === TransactionStatus::FAILED;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider->value,
            'gateway_order_id' => $this->gatewayOrderId,
            'gateway_payment_id' => $this->gatewayPaymentId,
            'status' => $this->status->value,
            'amount_minor' => $this->amountMinor,
            'currency' => $this->currency->value,
            'verified_at' => $this->verifiedAt->format(DATE_ATOM),
            'method' => $this->method,
            'metadata' => $this->metadata,
        ];
    }
}