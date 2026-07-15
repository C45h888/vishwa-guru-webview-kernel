<?php

declare(strict_types=1);

namespace App\Payments\Domain\DTOs;

use App\Payments\Domain\ValueObjects\PaymentIntent;

/**
 * Provider-agnostic request handed from the domain to the gateway layer.
 *
 * Adapters translate this DTO into provider-specific SDK or REST calls.
 * The DTO does NOT carry provider-shaped fields (no Razorpay customer_id,
 * no PayPal payer_id) — those are adapter concerns.
 */
final readonly class GatewayRequestDTO
{
    public function __construct(
        private PaymentIntent $intent,
        private array $providerHints = [],
    ) {
    }

    public function intent(): PaymentIntent
    {
        return $this->intent;
    }

    public function providerHints(): array
    {
        return $this->providerHints;
    }

    public function withHint(string $key, mixed $value): self
    {
        $hints = $this->providerHints;
        $hints[$key] = $value;

        return new self($this->intent, $hints);
    }

    public function toArray(): array
    {
        return [
            'intent' => $this->intent->toArray(),
            'provider_hints' => $this->providerHints,
        ];
    }
}
