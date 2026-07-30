<?php

declare(strict_types=1);

namespace App\Payments\Domain\ValueObjects;

use App\Payments\Domain\Enums\Currency;
use InvalidArgumentException;

/**
 * Immutable value object representing an amount of money in the
 * minor-unit convention (BIGINT integer on the wire; never float).
 *
 * Doctrine:
 *   - Amounts are ALWAYS integer minor units (e.g. 12500 paise = ₹125.00).
 *     The major-unit representation is a UI concern only.
 *   - Currency is REQUIRED. Currency arithmetic that mixes currencies
 *     throws InvalidArgumentException — never silently cross-converts.
 *   - Negative amounts are not allowed (use a separate RefundMoney VO if
 *     needed in Phase 4+).
 *   - This VO replaces bare `int $amountMinor` properties across the
 *     codebase as part of the B12 (Money value object) refactor. See
 *     PR 6 step 2 for the per-entity migration path.
 *
 * Wire shape: `Money->amountMinor()` is what gets serialized to JSON.
 * `Currency->value` is the ISO 4217 code that pairs with it.
 */
final readonly class Money
{
    public function __construct(
        private int $amountMinor,
        private Currency $currency,
    ) {
        if ($amountMinor < 0) {
            throw new InvalidArgumentException(
                "Money amount cannot be negative (got {$amountMinor})",
            );
        }
    }

    public function amountMinor(): int
    {
        return $this->amountMinor;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    /**
     * Add two Money values. Both must share the same currency.
     *
     * @throws InvalidArgumentException  when currencies differ
     */
    public function add(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amountMinor + $other->amountMinor, $this->currency);
    }

    /**
     * Subtract another Money value. Result is non-negative.
     *
     * @throws InvalidArgumentException  when currencies differ OR the
     *                                    result would be negative.
     */
    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amountMinor - $other->amountMinor, $this->currency);
    }

    /**
     * Compare two Money values. Returns -1, 0, or +1.
     *
     * @throws InvalidArgumentException  when currencies differ
     */
    public function compareTo(self $other): int
    {
        $this->assertSameCurrency($other);
        return $this->amountMinor <=> $other->amountMinor;
    }

    /**
     * Format for display using the currency's exponent and symbol.
     *
     * Examples:
     *   (new Money(12500, Currency::INR))->format()  =>  "₹125.00"
     *   (new Money(1234, Currency::JPY))->format()   =>  "¥1,234"
     */
    public function format(): string
    {
        $symbol = $this->currency->symbol();
        $exp = $this->currency->exponent();
        $major = $this->amountMinor / (10 ** $exp);
        $formatted = number_format($major, $exp, '.', ',');
        return $symbol . $formatted;
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency
            && $this->amountMinor === $other->amountMinor;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Money currency mismatch: cannot combine {$this->currency->value} ".
                "with {$other->currency->value}.",
            );
        }
    }
}