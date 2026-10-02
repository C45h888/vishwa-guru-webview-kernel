<?php

declare(strict_types=1);

namespace App\Payments\Receipts\Workers;

use RuntimeException;

/**
 * Thrown when a worker stage exhausts its retry attempts or blows its
 * time budget (see WorkerCadence). The substrate maps this to a
 * Result::failure + FailureState record; the queue-level cadence
 * (GenerateReceiptJob tries/backoff) then decides whether to re-run.
 */
final class WorkerExhaustedException extends RuntimeException
{
    public function __construct(
        public readonly string $worker,
        public readonly int $attempts,
        public readonly float $elapsedMs,
        public readonly ?string $lastError = null,
    ) {
        parent::__construct(sprintf(
            'Receipt worker [%s] exhausted %d attempt(s) in %.0fms%s',
            $worker,
            $attempts,
            $elapsedMs,
            $lastError !== null ? " — last error: {$lastError}" : '',
        ));
    }
}
