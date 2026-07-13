<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\ValueObjects;

use App\Shared\ValueObjects\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function testCreateFromMinorUnits(): void
    {
        $money = new Money(12345, 'INR');
        $this->assertSame(12345, $money->amountInMinorUnits);
        $this->assertSame('INR', $money->currency);
    }

    public function testNegativeAmountThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Money(-1, 'INR');
    }

    public function testInvalidCurrencyCodeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Money(100, 'inr'); // lowercase not allowed
    }

    public function testCurrencyCodeMustBe3Chars(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Money(100, 'IN'); // too short
    }

    public function testAddSameCurrency(): void
    {
        $sum = (new Money(100, 'INR'))->add(new Money(250, 'INR'));
        $this->assertSame(350, $sum->amountInMinorUnits);
        $this->assertSame('INR', $sum->currency);
    }

    public function testAddDifferentCurrencyThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Money(100, 'INR'))->add(new Money(100, 'USD'));
    }

    public function testSubtractSameCurrency(): void
    {
        $result = (new Money(500, 'INR'))->subtract(new Money(150, 'INR'));
        $this->assertSame(350, $result->amountInMinorUnits);
    }

    public function testSubtractNegativeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Money(100, 'INR'))->subtract(new Money(150, 'INR'));
    }

    public function testMultiply(): void
    {
        $result = (new Money(100, 'INR'))->multiply(3);
        $this->assertSame(300, $result->amountInMinorUnits);
    }

    public function testComparison(): void
    {
        $a = new Money(100, 'INR');
        $b = new Money(200, 'INR');

        $this->assertTrue($b->greaterThan($a));
        $this->assertTrue($a->lessThan($b));
        $this->assertFalse($a->greaterThan($b));
    }

    public function testEquals(): void
    {
        $a = new Money(999, 'INR');
        $b = new Money(999, 'INR');
        $c = new Money(1000, 'INR');

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }

    public function testFromMajorString(): void
    {
        $money = Money::fromMajor('123.45', 'INR');
        $this->assertSame(12345, $money->amountInMinorUnits);
    }

    public function testFromMajorWithoutDecimals(): void
    {
        $money = Money::fromMajor('100', 'INR');
        $this->assertSame(10000, $money->amountInMinorUnits);
    }

    public function testIsZero(): void
    {
        $this->assertTrue((new Money(0, 'INR'))->isZero());
        $this->assertFalse((new Money(1, 'INR'))->isZero());
    }

    public function testToString(): void
    {
        $money = new Money(12345, 'INR');
        $this->assertSame('123.45 INR', $money->toString());
    }
}