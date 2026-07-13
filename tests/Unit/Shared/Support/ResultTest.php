<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Support;

use App\Shared\Support\Result;
use PHPUnit\Framework\TestCase;

class ResultTest extends TestCase
{
    public function testSuccess(): void
    {
        $result = Result::success(['donation_id' => '123']);

        $this->assertTrue($result->isOk());
        $this->assertFalse($result->isFailure());
        $this->assertSame(['donation_id' => '123'], $result->value());
        $this->assertNull($result->error());
    }

    public function testFailure(): void
    {
        $result = Result::failure('Payment gateway timeout');

        $this->assertFalse($result->isOk());
        $this->assertTrue($result->isFailure());
        $this->assertNull($result->value());
        $this->assertSame('Payment gateway timeout', $result->error());
    }

    public function testValueOrWithSuccess(): void
    {
        $result = Result::success('actual');
        $this->assertSame('actual', $result->valueOr('default'));
    }

    public function testValueOrWithFailure(): void
    {
        $result = Result::failure('error');
        $this->assertSame('default', $result->valueOr('default'));
    }

    public function testMap(): void
    {
        $result = Result::success(100);
        $mapped = $result->map(fn($v) => $v * 2);

        $this->assertTrue($mapped->isOk());
        $this->assertSame(200, $mapped->value());
    }

    public function testMapPreservesFailure(): void
    {
        $result = Result::failure('error');
        $mapped = $result->map(fn($v) => $v * 2);

        $this->assertTrue($mapped->isFailure());
        $this->assertSame('error', $mapped->error());
    }
}
