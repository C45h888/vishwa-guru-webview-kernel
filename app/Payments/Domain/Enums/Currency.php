<?php

declare(strict_types=1);

namespace App\Payments\Domain\Enums;

/**
 * Supported currencies for payment processing.
 * ISO 4217 three-letter codes.
 */
enum Currency: string
{
    case INR = 'INR';
    case USD = 'USD';
    case EUR = 'EUR';
    case GBP = 'GBP';
    case AUD = 'AUD';
    case CAD = 'CAD';
    case SGD = 'SGD';
    case AED = 'AED';
    case JPY = 'JPY';

    /**
     * The smallest currency unit for this currency (exponent).
     * 0 means currency has no minor unit (e.g. JPY).
     */
    public function exponent(): int
    {
        return match ($this) {
            self::JPY => 0,
            default => 2,
        };
    }

    /**
     * Number of minor units in one major unit.
     * E.g. 100 paise = 1 INR, 1 yen = 1 JPY.
     */
    public function minorUnitFactor(): int
    {
        return 10 ** $this->exponent();
    }

    public function isSupported(): bool
    {
        return in_array($this, [
            self::INR,
            self::USD,
            self::EUR,
            self::GBP,
        ], true);
    }

    public function symbol(): string
    {
        return match ($this) {
            self::INR => '₹',
            self::USD => '$',
            self::EUR => '€',
            self::GBP => '£',
            self::AUD => 'A$',
            self::CAD => 'C$',
            self::SGD => 'S$',
            self::AED => 'د.إ',
            self::JPY => '¥',
        };
    }
}
