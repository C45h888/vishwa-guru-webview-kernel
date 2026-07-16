<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Adapters\Common;

/**
 * Shared value object for gateway credentials.
 * Encapsulates the three credential fields used across Razorpay and PayPal.
 */
final readonly class GatewayCredentials
{
    public function __construct(
        public string $keyId,
        public string $keySecret,
        public string $webhookSecret = '',
        public ?string $merchantId = null,
        public ?string $environment = null,
    ) {}

    public function hasWebhookSecret(): bool
    {
        return $this->webhookSecret !== '';
    }

    public function isSandbox(): bool
    {
        return $this->environment === 'sandbox'
            || $this->environment === 'test'
            || str_contains($this->keyId, 'test')
            || str_contains($this->keyId, 'sandbox');
    }
}
