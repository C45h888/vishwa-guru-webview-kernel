<?php

declare(strict_types=1);

namespace App\Jobs\Templates;

use App\Jobs\AbstractQueuedJob;
use App\Queue\Contracts\QueueConnectorContract;
use App\Shared\ValueObjects\Identifier;
use Illuminate\Support\Facades\Log;

/**
 * HealthCheckQueuedJob — example/template job every domain job follows.
 *
 * Doctrine rationale:
 *   - Provides a concrete, runnable example of the AbstractQueuedJob
 *     pattern. Future domain jobs (ReceiptPdfGenerationJob,
 *     DonationNotificationJob, WebhookRetryJob) clone this shape.
 *   - Used by temple:queue:stats as a probe target — dispatches one
 *     of these, then asserts the depth drops back to baseline.
 *   - Demonstrates the doctrine: typed payload, dependency injection
 *     via constructor (QueueConnectorContract, not Queue:: facade),
 *     idempotent handle(), explicit exception handling.
 *
 * This is NOT a production job — it's a template + test fixture.
 */
final class HealthCheckQueuedJob extends AbstractQueuedJob
{
    public function __construct(
        public readonly Identifier $correlationId,
        public readonly string $probeName = 'temple-runtime-probe',
    ) {}

    public function handle(): void
    {
        // Pure side effect: log the run with its correlation id so
        // ops can correlate the dispatch site to the worker run.
        // No business state is mutated — this is a probe, not a
        // business job.
        Log::info('HealthCheckQueuedJob handled', [
            'correlation_id' => $this->correlationId->value(),
            'probe'          => $this->probeName,
            'attempt'        => $this->attempts(),
        ]);
    }
}
