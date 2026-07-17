<?php

declare(strict_types=1);

namespace App\Runtime\Diagnostics;

use App\Persistence\Contracts\PersistenceAdapterContract;
use Throwable;

/**
 * Database subsystem health probe.
 *
 * Runs `SELECT 1` via PersistenceAdapterContract — uses the kernel
 * contract, NOT the DB:: facade, so the probe stays framework-agnostic
 * at the contract boundary and the doctrine "Runtime depends on
 * Persistence contracts only" holds.
 *
 * Doctrine: never throws. Catches Throwable, returns fail().
 */
final class DatabaseHealthProbe implements HealthProbe
{
    public function __construct(
        private readonly PersistenceAdapterContract $persistence,
    ) {}

    public function name(): string
    {
        return 'database';
    }

    public function probe(): HealthCheckResult
    {
        $start = microtime(true);

        try {
            $result = $this->persistence->query('SELECT 1 AS one');
            $latencyMs = (microtime(true) - $start) * 1000.0;

            if ($result->isFailure()) {
                return HealthCheckResult::fail(
                    'database',
                    $latencyMs,
                    $result->error() ?? 'unknown query failure',
                );
            }

            return HealthCheckResult::ok('database', $latencyMs);
        } catch (Throwable $e) {
            $latencyMs = (microtime(true) - $start) * 1000.0;
            return HealthCheckResult::fail('database', $latencyMs, $e->getMessage());
        }
    }
}