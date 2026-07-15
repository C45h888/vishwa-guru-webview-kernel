<?php

declare(strict_types=1);

namespace App\Payments\Contracts;

use App\Payments\Domain\Enums\Currency;

/**
 * Marker + capability declaration for a payment provider.
 * Implementations: RazorpayProvider, PayPalProvider, NeonProvider.
 */
interface PaymentProviderContract
{
    /**
     * The unique identifier for this provider (e.g. "razorpay").
     */
    public function name(): string;

    /**
     * The display name shown to operators.
     */
    public function displayName(): string;

    /**
     * Whether this provider is enabled in the current environment.
     */
    public function isEnabled(): bool;

    /**
     * The list of currencies supported by this provider.
     *
     * @return array<int, Currency>
     */
    public function supportedCurrencies(): array;

    /**
     * The minimum amount (in minor units) accepted by this provider.
     */
    public function minimumAmount(): int;

    /**
     * The maximum amount (in minor units) accepted by this provider.
     */
    public function maximumAmount(): int;

    /**
     * Priority for fallback ordering. Lower = higher priority.
     */
    public function priority(): int;
}
