<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Adapters\PayPal;

use App\Payments\Contracts\PaymentProviderContract;
use App\Payments\Domain\Enums\Currency;
use App\Shared\Contracts\ConfigurationContract;

/**
 * Pure metadata provider adapter for PayPal.
 * No SDK calls — reads configuration only.
 */
final class PayPalProviderAdapter implements PaymentProviderContract
{
    public function __construct(
        private ConfigurationContract $config,
    ) {}

    public function name(): string
    {
        return 'paypal';
    }

    public function displayName(): string
    {
        return 'PayPal';
    }

    public function isEnabled(): bool
    {
        $clientId = (string) $this->config->get('payments.providers.paypal.client_id', '');

        return $clientId !== '';
    }

    /**
     * @return array<int, Currency>
     */
    public function supportedCurrencies(): array
    {
        return [
            Currency::USD,
            Currency::EUR,
            Currency::GBP,
            Currency::CAD,
            Currency::AUD,
            Currency::SGD,
        ];
    }

    public function minimumAmount(): int
    {
        return 500; // $5.00 in cents
    }

    public function maximumAmount(): int
    {
        return 999_999_99; // ~$999,999.99 in cents
    }

    public function priority(): int
    {
        return (int) $this->config->get('payments.providers.paypal.priority', 20);
    }
}
