<?php

declare(strict_types=1);

namespace App\Runtime\Diagnostics;

use Illuminate\Contracts\Container\Container;

/**
 * Resolves all tagged HealthProbe implementations and aggregates results.
 *
 * Wired in RuntimeServiceProvider:
 *   - Tagged 'runtime.health_probe' → DatabaseHealthProbe, CacheHealthProbe, QueueHealthProbe
 *   - Aggregator resolves via $container->tagged('runtime.health_probe')
 *
 * Doctrine: probes are stateless; aggregator iterates them on every call.
 * Cheap (3 probes × ~few ms each). Safe to call from /health on every
 * load-balancer check.
 */
final class HealthCheckAggregator
{
    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * @return array<string, HealthCheckResult>
     */
    public function probe(): array
    {
        $results = [];

        foreach ($this->container->tagged('runtime.health_probe') as $probe) {
            /** @var HealthProbe $probe */
            $results[$probe->name()] = $probe->probe();
        }

        return $results;
    }

    /**
     * @param  array<string, HealthCheckResult>  $results
     */
    public function allHealthy(array $results): bool
    {
        foreach ($results as $result) {
            if (! $result->isHealthy()) {
                return false;
            }
        }
        return true;
    }
}