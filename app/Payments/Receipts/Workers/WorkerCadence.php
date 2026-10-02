<?php

declare(strict_types=1);

namespace App\Payments\Receipts\Workers;

use Throwable;

/**
 * WorkerCadence — the execution policy every receipt worker runs under.
 *
 * Each stage in the substrate pipeline (data → types → design) executes
 * through this cadence so the "worker" concept means something concrete:
 *
 *   - timeout: a wall-clock budget per stage. PHP cannot preempt a running
 *     call, so the budget is COOPERATIVE: checked before each attempt and
 *     enforced on completion (an attempt that blows the budget counts as a
 *     failed attempt and no further attempts are made). This guarantees a
 *     stage can never starve the queue worker's own retry ladder — the
 *     job either finishes inside its budget or fails fast into
 *     failed_jobs / receipts:reconcile.
 *   - retries: bounded attempts with a backoff schedule between them.
 *
 * Config contract (config/receipts.php → `workers`):
 *   workers.data|types|design.timeout_ms   — per-stage budget
 *   workers.data|types|design.max_attempts — bounded attempts
 *   workers.backoff_ms                     — backoff schedule (ms), by attempt index
 */
final class WorkerCadence
{
    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly array $config,
    ) {
    }

    /**
     * Execute one worker stage under the cadence for that stage.
     *
     * @template T
     * @param  callable(): T  $stage
     * @return T
     * @throws WorkerExhaustedException
     */
    public function run(string $worker, callable $stage): mixed
    {
        $timeoutMs = (float) (int) ($this->config[$worker]['timeout_ms'] ?? 2000);
        $maxAttempts = max(1, (int) ($this->config[$worker]['max_attempts'] ?? 2));
        /** @var array<int, int> $backoffMs */
        $backoffMs = array_values((array) ($this->config['backoff_ms'] ?? [100, 400]));

        $startedAt = microtime(true);
        $deadline = $startedAt + ($timeoutMs / 1000);
        $lastError = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            if (microtime(true) >= $deadline) {
                break;
            }

            $attemptStartedAt = microtime(true);

            try {
                $result = $stage();
                $elapsed = (microtime(true) - $attemptStartedAt) * 1000;

                // An attempt that completes but blew the budget is a
                // timeout — the cadence must stay predictable.
                if ($elapsed > $timeoutMs) {
                    $lastError = sprintf('attempt exceeded budget (%.0fms > %.0fms)', $elapsed, $timeoutMs);
                    break;
                }

                return $result;
            } catch (Throwable $e) {
                $lastError = $e->getMessage();
            }

            if ($attempt < $maxAttempts) {
                $backoff = (int) ($backoffMs[$attempt - 1] ?? $backoffMs[array_key_last($backoffMs)] ?? 0);
                if ($backoff > 0) {
                    usleep($backoff * 1000);
                }
            }
        }

        throw new WorkerExhaustedException(
            worker: $worker,
            attempts: $maxAttempts,
            elapsedMs: (microtime(true) - $startedAt) * 1000,
            lastError: $lastError,
        );
    }
}
