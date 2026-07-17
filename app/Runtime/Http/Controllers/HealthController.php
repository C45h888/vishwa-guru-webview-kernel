<?php

declare(strict_types=1);

namespace App\Runtime\Http\Controllers;

use App\Runtime\Diagnostics\HealthCheckAggregator;
use App\Runtime\Diagnostics\HealthCheckResult;
use App\Runtime\Diagnostics\KernelSnapshotFactory;
use App\Shared\Contracts\EnvironmentContract;
use Illuminate\Http\JsonResponse;

/**
 * GET /health — readiness probe.
 *
 * Returns:
 *   - 200 + status:ok when all subsystems healthy
 *   - 503 + status:degraded + which subsystems failed
 *
 * Doctrine: this controller NEVER throws. It is the public surface
 * load balancers and uptime monitors hit — it must respond even when
 * downstream subsystems are down.
 *
 * Registered as a top-level route (no /api/v1 prefix) because load
 * balancers expect /health at the root. See routes/web.php registration
 * via RouteServiceProvider.
 */
final class HealthController
{
    public function __construct(
        private readonly HealthCheckAggregator $aggregator,
        private readonly KernelSnapshotFactory $snapshotFactory,
        private readonly EnvironmentContract $env,
    ) {}

    public function __invoke(): JsonResponse
    {
        $results = $this->aggregator->probe();
        $snapshot = $this->snapshotFactory->capture();
        $allHealthy = $this->aggregator->allHealthy($results);

        $subsystems = [];
        foreach ($results as $name => $result) {
            $subsystems[$name] = $result->toArray();
        }

        $body = [
            'status'     => $allHealthy ? 'ok' : 'degraded',
            'checked_at' => $snapshot->capturedAt->format(\DateTimeImmutable::ATOM),
            'subsystems' => $subsystems,
            'runtime'    => [
                'php'     => $snapshot->phpVersion,
                'laravel' => $snapshot->laravelVersion,
                'env'     => $snapshot->environment->value,
            ],
        ];

        $status = $allHealthy ? 200 : 503;

        return new JsonResponse($body, $status);
    }
}