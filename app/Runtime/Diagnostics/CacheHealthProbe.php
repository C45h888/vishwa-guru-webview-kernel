<?php

declare(strict_types=1);

namespace App\Runtime\Diagnostics;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Throwable;

/**
 * Cache subsystem health probe.
 *
 * Sets + reads a throwaway key with a 1-second TTL. The key is namespaced
 * with `runtime-health-probe-` so it cannot collide with application data.
 *
 * Doctrine: never throws. Catches Throwable, returns fail().
 */
final class CacheHealthProbe implements HealthProbe
{
    public function __construct(
        private readonly CacheRepository $cache,
    ) {}

    public function name(): string
    {
        return 'cache';
    }

    public function probe(): HealthCheckResult
    {
        $start = microtime(true);

        try {
            $key = 'runtime-health-probe-' . bin2hex(random_bytes(6));
            $value = 'probe-' . bin2hex(random_bytes(4));

            $this->cache->put($key, $value, 1);

            $read = $this->cache->get($key);

            // Best-effort cleanup; if forget fails, the 1-second TTL
            // will reap the key anyway.
            $this->cache->forget($key);

            $latencyMs = (microtime(true) - $start) * 1000.0;

            if ($read !== $value) {
                return HealthCheckResult::fail(
                    'cache',
                    $latencyMs,
                    'round-trip mismatch — wrote ' . var_export($value, true)
                        . ' but read ' . var_export($read, true),
                );
            }

            return HealthCheckResult::ok('cache', $latencyMs);
        } catch (Throwable $e) {
            $latencyMs = (microtime(true) - $start) * 1000.0;
            return HealthCheckResult::fail('cache', $latencyMs, $e->getMessage());
        }
    }
}