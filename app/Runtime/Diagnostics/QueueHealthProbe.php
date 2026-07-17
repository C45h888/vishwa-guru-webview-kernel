<?php

declare(strict_types=1);

namespace App\Runtime\Diagnostics;

use Illuminate\Queue\QueueManager;
use Throwable;

/**
 * Queue subsystem health probe.
 *
 * Read-only check: calls Queue::connection()->size(null) — for the default
 * queue — without pushing any payload. Sync driver returns 0 immediately;
 * Redis/Database drivers report actual queue depth.
 *
 * Doctrine: never throws. Catches Throwable, returns fail().
 */
final class QueueHealthProbe implements HealthProbe
{
    public function __construct(
        private readonly QueueManager $queueManager,
    ) {}

    public function name(): string
    {
        return 'queue';
    }

    public function probe(): HealthCheckResult
    {
        $start = microtime(true);

        try {
            $size = $this->queueManager->connection()->size(null);
            $latencyMs = (microtime(true) - $start) * 1000.0;

            return HealthCheckResult::ok('queue', $latencyMs, "size: {$size}");
        } catch (Throwable $e) {
            $latencyMs = (microtime(true) - $start) * 1000.0;
            return HealthCheckResult::fail('queue', $latencyMs, $e->getMessage());
        }
    }
}