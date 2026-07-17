<?php

declare(strict_types=1);

namespace Tests\Unit\Runtime\Diagnostics;

use App\Runtime\Diagnostics\HealthCheckResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit tests for HealthCheckResult value object.
 * No container, no framework — constructor + accessors only.
 */
final class HealthCheckResultTest extends TestCase
{
    #[Test]
    public function ok_constructs_a_healthy_result(): void
    {
        $result = HealthCheckResult::ok('database', 2.5);

        $this->assertTrue($result->isHealthy());
        $this->assertSame('database', $result->name);
        $this->assertSame(2.5, $result->latencyMs);
        $this->assertNull($result->detail);
    }

    #[Test]
    public function ok_accepts_an_optional_detail_message(): void
    {
        $result = HealthCheckResult::ok('cache', 0.4, 'warm');

        $this->assertSame('warm', $result->detail);
    }

    #[Test]
    public function fail_constructs_an_unhealthy_result(): void
    {
        $result = HealthCheckResult::fail('queue', 12.3, 'connection refused');

        $this->assertFalse($result->isHealthy());
        $this->assertSame('queue', $result->name);
        $this->assertSame(12.3, $result->latencyMs);
        $this->assertSame('connection refused', $result->detail);
    }

    #[Test]
    public function latency_is_clamped_to_zero_minimum(): void
    {
        $negative = HealthCheckResult::ok('test', -1.0);
        $this->assertSame(0.0, $negative->latencyMs);
    }

    #[Test]
    public function to_array_includes_status_latency_and_detail(): void
    {
        $result = HealthCheckResult::ok('database', 2.3456789);

        $array = $result->toArray();

        $this->assertSame('ok', $array['status']);
        $this->assertSame(2.346, $array['latency_ms']);
        $this->assertNull($array['detail']);
    }

    #[Test]
    public function to_array_reports_fail_status(): void
    {
        $result = HealthCheckResult::fail('cache', 5.0, 'redis down');

        $array = $result->toArray();

        $this->assertSame('fail', $array['status']);
        $this->assertSame('redis down', $array['detail']);
    }
}