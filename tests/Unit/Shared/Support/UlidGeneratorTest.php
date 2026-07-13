<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Support;

use App\Shared\Support\UlidGenerator;
use PHPUnit\Framework\TestCase;

class UlidGeneratorTest extends TestCase
{
    public function testGenerateReturns26CharString(): void
    {
        $ulid = UlidGenerator::generate();
        $this->assertSame(26, strlen($ulid));
        $this->assertMatchesRegularExpression('/^[0-9A-Z]{26}$/', $ulid);
    }

    public function testGenerateIsUnique(): void
    {
        $ulids = [];
        for ($i = 0; $i < 100; $i++) {
            $ulids[] = UlidGenerator::generate();
        }
        $unique = array_unique($ulids);
        $this->assertCount(100, $unique);
    }

    public function testIsValidWithValidUlid(): void
    {
        $ulid = UlidGenerator::generate();
        $this->assertTrue(UlidGenerator::isValid($ulid));
    }

    public function testIsValidWithInvalidLength(): void
    {
        $this->assertFalse(UlidGenerator::isValid('TOOSHORT'));
        $this->assertFalse(UlidGenerator::isValid('TOOLONGTOOLONGTOOLONGTOOLONG'));
    }

    public function testIsValidWithInvalidCharacters(): void
    {
        $this->assertFalse(UlidGenerator::isValid('IIIIIIIIIIIIIIIIIIIIIIIIII')); // I, L, O, U not in Crockford Base32
    }

    public function testTimestampExtraction(): void
    {
        $before = time();
        $ulid = UlidGenerator::generate();
        $after = time();

        $ts = UlidGenerator::timestamp($ulid);
        $this->assertGreaterThanOrEqual($before, $ts);
        $this->assertLessThanOrEqual($after, $ts);
    }

    public function testGenerateFromTimestamp(): void
    {
        $ts = 1700000000;
        $ulid = UlidGenerator::generateFromTimestamp($ts);
        $this->assertSame(26, strlen($ulid));
        $this->assertSame($ts, UlidGenerator::timestamp($ulid));
    }

    public function testFromString(): void
    {
        $original = UlidGenerator::generate();
        $this->assertTrue(UlidGenerator::isValid($original));
    }
}
