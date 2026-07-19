<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * AbstractQueuedJob — base class for every queue job in the system.
 *
 * Establishes the doctrine-aligned pattern that every domain job follows:
 *
 *   1. Typed payload via constructor (Identifier, Money, etc.)
 *   2. retry policy declared as properties ($tries, $backoff)
 *   3. queue name declared as property ($queue)
 *   4. handle() is the single public side-effect method
 *   5. implements ShouldQueue so Laravel routes via worker
 *
 * Doctrine: typed payloads prevent silent schema drift. Raw scalars
 * lose meaning over time; typed VOs preserve intent. Doctrine: per-class
 * retry policy — no global magic, no env-driven retry that hides bugs.
 *
 * Retry contract:
 *   - General-purpose jobs (notifications, audit archival): tries=3,
 *     backoff=[10, 60, 300]. Set by the default properties below.
 *   - Financial jobs (receipts tied to verified payments, donation
 *     state transitions): override $tries = 1, $backoff = [0] in
 *     the subclass. Doctrine: financial correctness > convenience.
 *     A payment has been verified by the gateway; if the receipt
 *     job fails, retrying silently is worse than surfacing to ops.
 *
 * Subclassing pattern:
 *
 *   final class ReceiptPdfGenerationJob extends AbstractQueuedJob
 *   {
 *       public int $tries = 1;             // financial — fail-fast
 *       public array $backoff = [0];
 *       public ?string $queue = 'receipts';
 *
 *       public function __construct(
 *           public readonly ReceiptId $receiptId,
 *       ) {}
 *
 *       public function handle(ReceiptService $receipts): void
 *       {
 *           $receipts->generatePdf($this->receiptId);
 *       }
 *   }
 */
abstract class AbstractQueuedJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Total retry attempts before the job is moved to failed_jobs.
     *
     * Default 3 (general-purpose). Override to 1 in financial jobs.
     */
    public int $tries = 3;

    /**
     * Seconds to wait between retries. Index = attempt number.
     *
     * Default [10, 60, 300] = 10s after first failure, 60s after
     * second, 5min after third. Override [0] for financial jobs
     * (no auto-retry — fail immediately).
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 300];

    /**
     * Per-job hard timeout in seconds. The worker kills a job that
     * runs longer than this. Doctrine: never let a runaway job hold
     * a worker slot forever.
     */
    public int $timeout;

    /**
     * Subclasses MUST override handle() with their domain logic.
     */
    abstract public function handle(): void;

    /**
     * Idempotency key for the SETEX fast-path dedupe gate.
     *
     * Returns the key used by AbstractQueuedJob::execute() to call
     * QueueConnectorContract::reserveIdempotencyKey(). Default: Laravel's
     * auto-assigned job UUID (unique per dispatch, so default behavior is
     * "no dedupe — run every time"). Subclasses override to provide a
     * domain-meaningful key (e.g. "payment:{paymentId}"), which enables
     * cross-dispatch dedupe (same payload → only one execution).
     *
     * Return null to skip SETEX entirely (job runs every time). Useful
     * for fire-and-forget notifications.
     *
     * @return string|null  Key suffix; full key = "idem:queue:{jobClass}:{suffix}"
     */
    public function idempotencyKey(): ?string
    {
        return $this->job?->getKey();
    }

    /**
     * SETEX TTL in seconds for the idempotency reservation.
     *
     * Returns:
     *   - 0 (or negative) → SETEX is skipped. Job runs every time.
     *   - positive      → key is reserved with this TTL.
     *
     * Default: 86400 (24h). Override in subclasses:
     *   - Webhook-driven jobs → 604800 (7d) for long-tail retries
     *   - Financial jobs → 0 (no dedupe; correctness > convenience)
     */
    public function idempotencyTtl(): int
    {
        return 86400;
    }

    /**
     * Template method — Laravel's worker invokes this. Subclasses
     * MUST NOT override (final). The flow:
     *
     *   1. Resolve idempotency key + TTL.
     *   2. If both present, reserve via QueueConnectorContract.
     *      - Reservation fails (duplicate or backend error) → ack silently, return.
     *   3. Otherwise call subclass handle() with the domain logic.
     *
     * Doctrine: extension is by overriding handle(), idempotencyKey(),
     * idempotencyTtl(). The template is non-overridable so subclasses
     * cannot bypass the dedupe gate.
     */
    final public function execute(): void
    {
        $key = $this->idempotencyKey();
        $ttl = $this->idempotencyTtl();

        if ($key !== null && $ttl > 0) {
            /** @var \App\Queue\Contracts\QueueConnectorContract $connector */
            $connector = app(\App\Queue\Contracts\QueueConnectorContract::class);

            $jobClass = $this->job ? get_class($this) : 'unknown';
            $fullKey = "idem:queue:{$jobClass}:{$key}";

            if (! $connector->reserveIdempotencyKey($fullKey, $ttl)) {
                // Duplicate detected OR backend unreachable. Both report
                // as false per the doctrine; in either case we ack silently
                // and do not run handle(). The job's redoRunIfFailed()
                // (Laravel native) handles true failures separately.
                return;
            }
        }

        $this->handle();
    }
}
