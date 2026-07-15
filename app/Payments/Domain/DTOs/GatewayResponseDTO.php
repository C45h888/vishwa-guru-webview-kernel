<?php

declare(strict_types=1);

namespace App\Payments\Domain\DTOs;

use App\Payments\Domain\Enums\Currency;
use InvalidArgumentException;

/**
 * Provider-agnostic response from a gateway adapter.
 *
 * The adapter parses the provider's raw response into this DTO before
 * returning it to the PaymentService. The DTO does NOT interpret
 * provider status strings — that mapping to TransactionStatus is the
 * PaymentVerificationService's job (Pass 1.3).
 */
final readonly class GatewayResponseDTO
{
    public function __construct(
        private string $providerCode,
        private string $gatewayOrderId,
        private ?string $gatewayPaymentId,
        private string $rawStatusString,
        private int $amountMinor,
        private Currency $currency,
        private array $rawResponse = [],
        private ?string $checkoutUrl = null,
        private ?string $method = null,
    ) {
        if (empty($providerCode)) {
            throw new InvalidArgumentException('GatewayResponseDTO providerCode cannot be empty');
        }
        if (empty($gatewayOrderId)) {
            throw new InvalidArgumentException('GatewayResponseDTO gatewayOrderId cannot be empty');
        }
        if (empty($rawStatusString)) {
            throw new InvalidArgumentException('GatewayResponseDTO rawStatusString cannot be empty');
        }
        if ($amountMinor <= 0) {
            throw new InvalidArgumentException(
                'GatewayResponseDTO amountMinor must be positive (got ' . $amountMinor . ')'
            );
        }
    }

    public function providerCode(): string
    {
        return $this->providerCode;
    }

    public function gatewayOrderId(): string
    {
        return $this->gatewayOrderId;
    }

    public function gatewayPaymentId(): ?string
    {
        return $this->gatewayPaymentId;
    }

    public function rawStatusString(): string
    {
        return $this->rawStatusString;
    }

    public function amountMinor(): int
    {
        return $this->amountMinor;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function rawResponse(): array
    {
        return $this->rawResponse;
    }

    public function checkoutUrl(): ?string
    {
        return $this->checkoutUrl;
    }

    public function method(): ?string
    {
        return $this->method;
    }

    public function hasCheckoutUrl(): bool
    {
        return $this->checkoutUrl !== null && $this->checkoutUrl !== '';
    }

    public function hasPaymentId(): bool
    {
        return $this->gatewayPaymentId !== null && $this->gatewayPaymentId !== '';
    }

    public function toArray(): array
    {
        return [
            'provider_code' => $this->providerCode,
            'gateway_order_id' => $this->gatewayOrderId,
            'gateway_payment_id' => $this->gatewayPaymentId,
            'raw_status_string' => $this->rawStatusString,
            'amount_minor' => $this->amountMinor,
            'currency' => $this->currency->value,
            'raw_response' => $this->rawResponse,
            'checkout_url' => $this->checkoutUrl,
            'method' => $this->method,
        ];
    }
}
