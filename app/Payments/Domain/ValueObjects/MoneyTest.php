<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Domain\ValueObjects;

use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\ValueObjects\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function test_constructs_with_minor_units_and_currency(): void
    {
        $m = new Money(12500, Currency::INR);
        self::assertSame(12500, $m->amountMinor());
        self::assertSame(Currency::INR, $m->currency());
    }

    public function test_negative_amount_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Money(-1, Currency::INR);
    }

    public function test_add_two_same_currency_values(): void
    {
        $a = new Money(100, Currency::INR);
        $b = new Money(250, Currency::INR);
        $result = $a->add($b);
        self::assertSame(350, $result->amountMinor());
        self::assertSame(Currency::INR, $result->currency());
    }

    public function test_add_throws_on_currency_mismatch(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Money(100, Currency::INR))->add(new Money(100, Currency::USD));
    }

    public function test_subtract_same_currency(): void
    {
        $result = (new Money(500, Currency::INR))->subtract(new Money(200, Currency::INR));
        self::assertSame(300, $result->amountMinor());
    }

    public function test_subtract_throws_when_result_would_be_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Money(100, Currency::INR))->subtract(new Money(200, Currency::INR));
    }

    public function test_compare_to_returns_relative_order(): void
    {
        $a = new Money(100, Currency::INR);
        $b = new Money(200, Currency::INR);
        $c = new Money(100, Currency::INR);

        self::assertSame(-1, $a->compareTo($b));
        self::assertSame(1, $b->compareTo($a));
        self::assertSame(0, $a->compareTo($c));
    }

    public function test_compare_to_throws_on_currency_mismatch(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Money(100, Currency::INR))->compareTo(new Money(100, Currency::USD));
    }

    public function test_equals_compares_both_fields(): void
    {
        $a = new Money(100, Currency::INR);
        $b = new Money(100, Currency::INR);
        $c = new Money(100, Currency::USD);
        $d = new Money(200, Currency::INR);

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
        self::assertFalse($a->equals($d));
    }

    public function test_format_inr_with_two_decimal_places(): void
    {
        self::assertSame('₹125.00', (new Money(12500, Currency::INR))->format());
    }

    public function test_format_inr_zero_padded(): void
    {
        self::assertSame('₹0.50', (new Money(50, Currency::INR))->format());
    }

    public function test_format_jpy_with_no_decimal_places(): void
    {
        self::assertSame('¥1,234', (new Money(1234, Currency::JPY))->format());
    }

    public function test_format_usd_with_thousands_separator(): void
    {
        self::assertSame('$1,234.56', (new Money(123456, Currency::USD))->format());
    }
}