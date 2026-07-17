<?php

declare(strict_types=1);

namespace App\Runtime\Diagnostics;

/**
 * Single-subsystem health probe interface.
 *
 * Implementations:
 *   - DatabaseHealthProbe (PersistenceAdapterContract::query("SELECT 1"))
 *   - CacheHealthProbe    (Cache::put + get round-trip)
 *   - QueueHealthProbe    (Queue::connection()->size(null) — read-only)
 *
 * Doctrine: implementations NEVER throw. They observe a subsystem and
 * return a HealthCheckResult. The aggregator + HealthController layer
 * translates the result into HTTP semantics.
 */
interface HealthProbe
{
    /**
     * Short subsystem identifier (e.g. "database", "cache", "queue").
     * Used as the map key in HealthCheckAggregator and in JSON output.
     */
    public function name(): string;

    /**
     * Run the probe and return the result.
     * MUST NOT throw — wrap any exception in HealthCheckResult::fail().
     */
    public function probe(): HealthCheckResult;
}