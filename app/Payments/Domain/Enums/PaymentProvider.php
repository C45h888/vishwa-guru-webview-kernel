<?php

declare(strict_types=1);

namespace App\Payments\Domain\Enums;

/**
 * Provider codes for the payment gateway layer.
 *
 * The string value MUST match the `code` column of the `payment_providers`
 * table defined in `schema-neon/V1-schema.sql`. The Payments domain uses
 * these codes when persisting `payments.provider_code` and when resolving
 * a concrete `PaymentGatewayContract` implementation through the
 * PaymentProviderSelector.
 *
 * New gateways are added by appending a case here AND registering a new
 * PaymentGatewayContract implementation in PaymentsServiceProvider.
 */
enum PaymentProvider: string
{
    case RAZORPAY = 'razorpay';
    case PAYPAL = 'paypal';

    /**
     * Whether this provider is currently supported for production traffic.
     * Mirrors the `enabled` column on the `payment_providers` row.
     */
    public function isEnabled(): bool
    {
        return match ($this) {
            self::RAZORPAY, self::PAYPAL => true,
        };
    }

    /**
     * The Currencies this provider natively supports.
     *
     * @return array<int, Currency>
     */
    public function supportedCurrencies(): array
    {
        return match ($this) {
            self::RAZORPAY => [Currency::INR],
            self::PAYPAL => [Currency::USD, Currency::EUR, Currency::GBP, Currency::AUD, Currency::CAD, Currency::SGD, Currency::AED],
        };
    }

    /**
     * Selection priority. Lower value = higher priority.
     * The PaymentProviderSelector walks providers in ascending priority.
     */
    public function priority(): int
    {
        return match ($this) {
            self::RAZORPAY => 10,
            self::PAYPAL => 20,
        };
    }

    /**
     * Minimum amount (in the currency's MINOR units — paise, cents)
     * the provider will accept. The PaymentProviderSelector rejects
     * intents whose amount_minor falls below this floor.
     */
    public function minimumAmount(): int
    {
        return match ($this) {
            // ₹1.00 = 100 paise
            self::RAZORPAY => 100,
            // $0.01 USD = 1 cent. PayPal's real floor is higher
            // but the selector uses this as a capability check,
            // not a business policy check.
            self::PAYPAL => 1,
        };
    }

    /**
     * Maximum amount (in the currency's MINOR units) the provider
     * will accept for a single transaction. The selector rejects
     * intents whose amount_minor exceeds this ceiling.
     */
    public function maximumAmount(): int
    {
        return match ($this) {
            // ₹15,00,000.00 = 15,000,000 paise (Razorpay's published
            // per-transaction cap is INR 15 lakh)
            self::RAZORPAY => 15_000_000,
            // PayPal's per-transaction ceiling varies by currency.
            // $10,000.00 USD = 1,000,000 cents is the conservative
            // selector-level floor; per-currency ceilings are an
            // adapter concern in Pass 1.5.
            self::PAYPAL => 1_000_000,
        };
    }

    /**
     * Whether the provider supports the given currency.
     */
    public function supports(Currency $currency): bool
    {
        return in_array($currency, $this->supportedCurrencies(), true);
    }

    /**
     * Human-readable label for operator-facing surfaces.
     */
    public function label(): string
    {
        return match ($this) {
            self::RAZORPAY => 'Razorpay',
            self::PAYPAL => 'PayPal',
        };
    }
}