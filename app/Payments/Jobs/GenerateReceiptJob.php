<?php

declare(strict_types=1);

namespace App\Payments\Jobs;

use App\Jobs\AbstractQueuedJob;
use App\Payments\Services\ReceiptService;
use App\Shared\ValueObjects\Identifier;
use RuntimeException;

/**
 * GenerateReceiptJob — renders and persists the receipt for a validated
 * payment on the dedicated `receipts` queue (owned by a dedicated worker).
 *
 * Financial job: 3 tries with backoff. A missing receipt is a real
 * financial artifact, so transient failures (PDF render, file write, DB)
 * are retried; after the final attempt the job lands in failed_jobs and
 * the scheduled `receipts:reconcile` command re-issues it.
 *
 * Idempotent: ReceiptService::issue() short-circuits when a receipt
 * already exists for the payment, so at-least-once delivery is safe. The
 * SETEX idempotency key adds a fast-path dedupe on top.
 */
final class GenerateReceiptJob extends AbstractQueuedJob
{
    /** Retry with backoff — receipt generation is idempotent and retryable. */
    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public $queue = 'receipts';

    public function __construct(
        public readonly Identifier $paymentId,
    ) {}

    public function idempotencyKey(): ?string
    {
        return 'receipt:'.$this->paymentId->value();
    }

    /** 7 days — long enough to dedupe replays while reconcile catches misses. */
    public function idempotencyTtl(): int
    {
        return 7 * 24 * 3600;
    }

    public function handle(): void
    {
        $result = app(ReceiptService::class)->issue($this->paymentId);

        if ($result->isFailure()) {
            // Surface to the queue so the retry/backoff policy applies.
            // ReceiptService::issue is idempotent, so a retry is safe.
            throw new RuntimeException(
                'GenerateReceiptJob: receipt issuance failed — '.$result->error(),
            );
        }

        // Delivery is a CONSEQUENCE of issuance, never a part of it: the
        // queue carrier for the mail flow is dispatched only after the
        // receipt committed, so a mail failure can never undo or block a
        // verified receipt. The mail seam handles idempotency itself.
        $receipt = $result->value();
        if ($receipt instanceof \App\Payments\Domain\Entities\Receipt) {
            \App\Jobs\ReceiptEmailJob::dispatch(
                new Identifier($receipt->id()->ulid()),
            );
        }
    }
}
