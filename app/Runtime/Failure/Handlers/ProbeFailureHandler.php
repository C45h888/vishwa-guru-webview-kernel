<?php

declare(strict_types=1);

namespace App\Runtime\Failure\Handlers;

use App\Runtime\Failure\StateMachines\FailureTransitionResult;
use App\Runtime\Failure\ValueObjects\FailureRecord;

/**
 * Executes the side effect for ProbeSubsystemDown failures.
 *
 * Doctrine: receives a fully-formed FailureRecord + FailureTransitionResult
 * from the router. The state machine has already decided the log level
 * (WARNING), the user message, and the coalesce window.
 *
 * This handler does NOT return a HealthCheckResult — that is the probe's
 * job. The probe returns HealthCheckResult::fail to its caller
 * (HealthCheckAggregator → HealthController), and the aggregator
 * separately calls FailureRouter::report(kind: ProbeSubsystemDown)
 * to record the failure through the runtime membrane.
 *
 * The handler's responsibility: ensure the failure is logged at the
 * inferred level with the structured context (probe name, latency,
 * detail) so operators can correlate /health JSON with log entries.
 *
 * No-op if the router already logged it — but in practice the router
 * logs ONCE per lifecycle walk, and this handler is the side effect at
 * the Surfaced transition. Logging happens at the Recorded transition.
 */
final class ProbeFailureHandler
{
    public function handle(FailureRecord $record, FailureTransitionResult $result): void
    {
        // Side effect: surface the failure to whoever is observing.
        // Currently this is a no-op because logging happens at the
        // Recorded transition in the router. Future phases may add:
        //   - emit a metric (Prometheus / StatsD)
        //   - update a circuit-breaker counter
        //   - emit a webhook to ops
        // For now, the Surfaced transition is purely structural.
    }
}