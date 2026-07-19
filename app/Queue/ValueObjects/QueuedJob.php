<?php

declare(strict_types=1);

namespace App\Queue\ValueObjects;

use App\Shared\ValueObjects\Identifier;

/**
 * QueuedJob — typed payload for a queue dispatch.
 *
 * Doctrine:
 *   - Doctrine value-object pattern: immutable, equality by value.
 *   - Job classes extend App\Jobs\AbstractQueuedJob which already
 *     implements ShouldQueue. This VO is the DATA the job carries,
 *     not the job itself.
 *   - Doctrine: typed payloads prevent silent schema drift. Raw
 *     scalars lose meaning over time; typed VOs preserve intent.
 *
 * The job payload is what the worker will reconstruct when popping.
 * We serialize to JSON for storage on Redis (the Laravel queue
 * substrate).
 */
final class QueuedJob
{
    /**
     * @param  string  $jobClass   Fully-qualified class name of the job
     * @param  array<string, mixed>  $payload  Typed payload values
     * @param  string|null  $queue  Queue name; null = default queue
     * @param  int  $tries  Retry attempts before failing
     * @param  array<int, int>  $backoff  Seconds to wait between retries
     */
    public function __construct(
        public readonly string $jobClass,
        public readonly array $payload,
        public readonly ?string $queue = null,
        public readonly int $tries = 3,
        public readonly array $backoff = [10, 60, 300],
    ) {
    }

    /**
     * Factory for a job with explicit ID + tracking identifier.
     */
    public static function create(
        string $jobClass,
        array $payload,
        ?string $queue = null,
        int $tries = 3,
        array $backoff = [10, 60, 300],
    ): self {
        return new self($jobClass, $payload, $queue, $tries, $backoff);
    }

    /**
     * Convenience constructor for financial-path jobs (tries=1, fail-fast).
     */
    public static function financial(string $jobClass, array $payload, ?string $queue = null): self
    {
        return new self($jobClass, $payload, $queue, tries: 1, backoff: [0]);
    }

    /**
     * Generate a unique tracking id for this dispatch.
     */
    public function trackingId(): Identifier
    {
        return Identifier::generate();
    }
}
