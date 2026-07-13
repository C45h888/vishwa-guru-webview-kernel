<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Enums;

use App\Payments\Enums\Currency;
use PHPUnit\Framework\TestCase;

class CurrencyTest extends TestCase
{
    public function testMinorUnitFactor(): void
    {
        $this->assertSame(100, Currency::INR->minorUnitFactor());
        $this->assertSame(100, Currency::USD->minorUnitFactor());
        $this->assertSame(1, Currency::JPY->minorUnitFactor());
    }

    public function testExponent(): void
    {
        $this->assertSame(2, Currency::INR->exponent());
        $this->assertSame(0, Currency::JPY->exponent());
    }

    public function testSupported(): void
    {
        $this->assertTrue(Currency::INR->isSupported());
        $this->assertTrue(Currency::USD->isSupported());
        $this->assertFalse(Currency::JPY->isSupported());
    }

    public function testSymbols(): void
    {
        $this->assertSame('₹', Currency::INR->symbol());
        $this->assertSame('$', Currency::USD->symbol());
        $this->assertSame('¥', Currency::JPY->symbol());
    }
}