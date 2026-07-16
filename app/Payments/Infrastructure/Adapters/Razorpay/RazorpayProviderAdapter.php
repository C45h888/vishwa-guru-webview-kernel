<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Adapters\Razorpay;

use App\Payments\Contracts\PaymentProviderContract;
use App\Payments\Domain\Enums\Currency;
use App\Shared\Contracts\ConfigurationContract;

/**
 * Pure metadata provider adapter for Razorpay.
 * No SDK calls — reads configuration only.
 */
final class RazorpayProviderAdapter implements PaymentProviderContract
{
    public function __construct(
        private ConfigurationContract $config,
    ) {}

    public function name(): string
    {
        return 'razorpay';
    }

    public function displayName(): string
    {
        return 'Razorpay';
    }

    public function isEnabled(): bool
    {
        $keyId = (string) $this->config->get('payments.providers.razorpay.key_id', '');

        return $keyId !== '';
    }

    /**
     * @return array<int, Currency>
     */
    public function supportedCurrencies(): array
    {
        return [Currency::INR];
    }

    public function minimumAmount(): int
    {
        return 100; // ₹1 in paise
    }

    public function maximumAmount(): int
    {
        return 99_999_999; // ~₹1 crore in paise
    }

    public function priority(): int
    {
        return (int) $this->config->get('payments.providers.razorpay.priority', 10);
    }
}
