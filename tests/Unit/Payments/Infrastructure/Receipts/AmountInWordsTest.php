<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Infrastructure\Receipts;

use App\Payments\Infrastructure\Receipts\AmountInWords;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class AmountInWordsTest extends TestCase
{
    public function testConvertZero(): void
    {
        $this->assertSame('Zero Rupees Only', AmountInWords::convert(0));
    }

    public function testConvertOneHundred(): void
    {
        $this->assertSame(
            'One Hundred Rupees Only',
            AmountInWords::convert(100_00),
        );
    }

    public function testConvertOneThousand(): void
    {
        $this->assertSame(
            'One Thousand Rupees Only',
            AmountInWords::convert(1_000_00),
        );
    }

    public function testConvertOneLakh(): void
    {
        // 1,00,000 = One Lakh
        $this->assertSame(
            'One Lakh Rupees Only',
            AmountInWords::convert(1_00_000_00),
        );
    }

    public function testConvertOneLakhTwentyThreeThousandFourHundredFiftySix(): void
    {
        // 1,23,456 = One Lakh Twenty Three Thousand Four Hundred Fifty Six
        $this->assertSame(
            'One Lakh Twenty Three Thousand Four Hundred Fifty Six Rupees Only',
            AmountInWords::convert(1_23_456_00),
        );
    }

    public function testConvertTenLakhs(): void
    {
        // 10,00,000 = Ten Lakhs
        $this->assertSame(
            'Ten Lakhs Rupees Only',
            AmountInWords::convert(10_00_000_00),
        );
    }

    public function testConvertOneCrore(): void
    {
        // 1,00,00,000 = One Crore
        $this->assertSame(
            'One Crore Rupees Only',
            AmountInWords::convert(1_00_00_000_00),
        );
    }

    public function testConvertFiftyCrore(): void
    {
        // 50,00,00,000 = Fifty Crores
        $this->assertSame(
            'Fifty Crores Rupees Only',
            AmountInWords::convert(50_00_00_000_00),
        );
    }

    public function testConvertFiftyRupees(): void
    {
        $this->assertSame(
            'Fifty Rupees Only',
            AmountInWords::convert(50_00),
        );
    }

    public function testUppercaseFlagCapitalizes(): void
    {
        $result = AmountInWords::convert(1_00_000_00, uppercase: true);
        $this->assertSame('One Lakh Rupees Only', $result);
        $this->assertSame('O', $result[0]); // first char is uppercase
    }

    public function testNegativeThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        AmountInWords::convert(-100_00);
    }
}
