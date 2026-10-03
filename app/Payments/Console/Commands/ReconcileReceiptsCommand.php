<?php

declare(strict_types=1);

namespace App\Payments\Console\Commands;

use App\Jobs\ReceiptEmailJob;
use App\Payments\Domain\Repositories\PaymentRepositoryContract;
use App\Payments\Domain\Repositories\ReceiptRepositoryContract;
use App\Payments\Jobs\GenerateReceiptJob;
use App\Shared\ValueObjects\Identifier;
use Illuminate\Console\Command;

/**
 * receipts:reconcile — two-pass backfill safety net.
 *
 * Pass 1 — MISSING RECEIPTS: dispatch receipt generation for successfully
 * validated payments that have none. Covers a job that exhausted its
 * retries, a signal lost during a deploy, or a receipt row that never
 * persisted. Idempotent: GenerateReceiptJob / ReceiptService::issue() are
 * idempotent per payment.
 *
 * Pass 2 — UNDELIVERED EMAILS (2026-10-03): re-dispatch ReceiptEmailJob
 * for receipts whose email delivery never durably completed —
 * delivery_status 'failed' rows, plus 'pending' rows older than the grace
 * window (an in-flight email job is not racing). The MailDispatchCoordinator's
 * persisted already_delivered guard makes re-dispatch a no-op for anything
 * that actually delivered. Idempotent for the same reason.
 */
final class ReconcileReceiptsCommand extends Command
{
    /**
     * Grace window before a pending receipt's email is considered lost.
     * ReceiptEmailJob normally runs within seconds; an hour is far beyond
     * any legitimate queue dwell, so a pending row this old has no job
     * carrying it anywhere.
     */
    private const PENDING_GRACE_SECONDS = 3600;

    /**
     * @var string
     */
    protected $signature = 'receipts:reconcile {--limit=200 : Max payments/receipts to scan per pass}';

    /**
     * @var string
     */
    protected $description = 'Backfill receipt generation and email delivery for captured payments.';

    public function handle(
        PaymentRepositoryContract $payments,
        ReceiptRepositoryContract $receipts,
    ): int {
        $limit = (int) $this->option('limit');

        $dispatchedReceipts = $this->reconcileMissingReceipts($payments, $limit);
        $dispatchedEmails = $this->reconcileUndeliveredEmails($receipts, $limit);

        if ($dispatchedReceipts === 0 && $dispatchedEmails === 0) {
            $this->info('receipts:reconcile — nothing to backfill.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'receipts:reconcile — dispatched %d receipt generation job(s), %d email job(s).',
            $dispatchedReceipts,
            $dispatchedEmails,
        ));

        return self::SUCCESS;
    }

    private function reconcileMissingReceipts(
        PaymentRepositoryContract $payments,
        int $limit,
    ): int {
        $missing = $payments->findSuccessfulWithoutReceipt($limit);

        foreach ($missing as $payment) {
            GenerateReceiptJob::dispatch(new Identifier($payment->id()->ulid()));
        }

        return count($missing);
    }

    private function reconcileUndeliveredEmails(
        ReceiptRepositoryContract $receipts,
        int $limit,
    ): int {
        $stale = $receipts->findUncompletedDeliveries(
            pendingOlderThanSeconds: self::PENDING_GRACE_SECONDS,
            limit: $limit,
        );

        foreach ($stale as $receipt) {
            ReceiptEmailJob::dispatch(new Identifier($receipt->id()->ulid()));
        }

        return count($stale);
    }
}