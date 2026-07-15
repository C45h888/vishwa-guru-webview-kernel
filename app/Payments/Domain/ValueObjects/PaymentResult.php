<?php

declare(strict_types=1);

namespace App\Payments\Domain\ValueObjects;

use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Enums\PaymentProvider;
use App\Payments\Domain\Enums\TransactionStatus;
use InvalidArgumentException;

/**
 * The result returned by a gateway after a successful order initialization.
 *
 * PaymentResult does NOT yet represent a verified payment — it is the
 * gateway's acknowledgement that an order has been created and a customer
 * may now proceed to the gateway checkout surface. Verification happens
 * only after the gateway callback is received and signature-verified.
 */
final class PaymentResult
{
    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        private readonly PaymentProvider $provider,
        private readonly string $gatewayOrderId,
        private readonly int $amountMinor,
        private readonly Currency $currency,
        private readonly TransactionStatus $status,
        private readonly array $rawResponse = [],
        private readonly ?string $checkoutUrl = null,
    ) {
        if ($amountMinor <= 0) {
            throw new InvalidArgumentException(
                "PaymentResult amount must be positive (got {$amountMinor})"
            );
        }
        if (empty($gatewayOrderId)) {
            throw new InvalidArgumentException('PaymentResult gatewayOrderId cannot be empty');
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

    /**
     * @return array<string, mixed>
     */
    public function rawResponse(): array
    {
        return $this->rawResponse;
    }

    public function checkoutUrl(): ?string
    {
        return $this->checkoutUrl;
    }

    /**
     * Whether the gateway handed back a checkout URL the customer can be redirected to.
     */
    public function hasCheckoutUrl(): bool
    {
        return $this->checkoutUrl !== null && $this->checkoutUrl !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider->value,
            'gateway_order_id' => $this->gatewayOrderId,
            'amount_minor' => $this->amountMinor,
            'currency' => $this->currency->value,
            'status' => $this->status->value,
            'checkout_url' => $this->checkoutUrl,
            'raw_response' => $this->rawResponse,
        ];
    }
}