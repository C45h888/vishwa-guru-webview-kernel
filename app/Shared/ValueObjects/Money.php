<?php

declare(strict_types=1);

namespace App\Shared\ValueObjects;

use InvalidArgumentException;

/**
 * Represents a monetary amount in minor units (e.g. paise for INR).
 * 1 INR = 100 paise. Amounts are always non-negative integers.
 *
 * Currency codes follow ISO 4217 (3 uppercase letters).
 *
 * Immutable: every operation returns a new Money instance.
 */
final class Money
{
    /**
     * @param int $amountInMinorUnits Amount in the currency's minor unit (paise, cents, etc.)
     * @param string $currency ISO 4217 three-letter uppercase code
     */
    public function __construct(
        public readonly int $amountInMinorUnits,
        public readonly string $currency,
    ) {
        if ($amountInMinorUnits < 0) {
            throw new InvalidArgumentException(
                "Money amount cannot be negative (got {$amountInMinorUnits})"
            );
        }
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException(
                "Invalid ISO 4217 currency code: {$currency}"
            );
        }
    }

    /**
     * Construct from major-unit string with two decimals (e.g. "123.45").
     * Uses string arithmetic to avoid float precision issues.
     */
    public static function fromMajor(string $major, string $currency): self
    {
        if (!preg_match('/^-?\d+(\.\d{1,2})?$/', $major)) {
            throw new InvalidArgumentException(
                "Invalid major-unit string: {$major}"
            );
        }

        $negative = str_starts_with($major, '-');
        if ($negative) {
            $major = substr($major, 1);
        }

        if (!str_contains($major, '.')) {
            $minor = ((int) $major) * 100;
        } else {
            [$whole, $fraction] = explode('.', $major, 2);
            $fraction = str_pad($fraction, 2, '0', STR_PAD_RIGHT);
            $minor = ((int) $whole) * 100 + (int) substr($fraction, 0, 2);
        }

        if ($negative) {
            $minor = -$minor;
        }

        return new self($minor, $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amountInMinorUnits + $other->amountInMinorUnits, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);
        $result = $this->amountInMinorUnits - $other->amountInMinorUnits;
        if ($result < 0) {
            throw new InvalidArgumentException(
                'Subtraction would produce a negative Money'
            );
        }
        return new self($result, $this->currency);
    }

    public function multiply(int $factor): self
    {
        if ($factor < 0) {
            throw new InvalidArgumentException(
                'Multiply factor must be non-negative'
            );
        }
        return new self($this->amountInMinorUnits * $factor, $this->currency);
    }

    public function greaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->amountInMinorUnits > $other->amountInMinorUnits;
    }

    public function lessThan(self $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->amountInMinorUnits < $other->amountInMinorUnits;
    }

    public function isZero(): bool
    {
        return $this->amountInMinorUnits === 0;
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency
            && $this->amountInMinorUnits === $other->amountInMinorUnits;
    }

    public function toString(): string
    {
        $major = intdiv($this->amountInMinorUnits, 100);
        $minor = $this->amountInMinorUnits % 100;
        return sprintf('%d.%02d %s', $major, $minor, $this->currency);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Currency mismatch: {$this->currency} vs {$other->currency}"
            );
        }
    }
}