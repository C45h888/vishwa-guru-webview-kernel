<?php

declare(strict_types=1);

namespace App\Runtime\Diagnostics;

/**
 * Immutable result of a single subsystem health probe.
 *
 * Constructed via the named constructors ok() / fail() so the boolean
 * status is never ambiguous. Latency is in milliseconds (float).
 */
final class HealthCheckResult
{
    private function __construct(
        public readonly string $name,
        public readonly bool $healthy,
        public readonly float $latencyMs,
        public readonly ?string $detail,
    ) {}

    /**
     * Construct a successful probe result.
     */
    public static function ok(string $name, float $latencyMs, ?string $detail = null): self
    {
        return new self($name, true, max(0.0, $latencyMs), $detail);
    }

    /**
     * Construct a failed probe result.
     */
    public static function fail(string $name, float $latencyMs, ?string $detail = null): self
    {
        return new self($name, false, max(0.0, $latencyMs), $detail);
    }

    public function isHealthy(): bool
    {
        return $this->healthy;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status'     => $this->healthy ? 'ok' : 'fail',
            'latency_ms' => round($this->latencyMs, 3),
            'detail'     => $this->detail,
        ];
    }
}