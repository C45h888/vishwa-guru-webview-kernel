<?php

declare(strict_types=1);

namespace App\Payments\Console\Commands;

use App\Payments\Domain\Repositories\PaymentRepositoryContract;
use App\Payments\Jobs\GenerateReceiptJob;
use App\Shared\ValueObjects\Identifier;
use Illuminate\Console\Command;

/**
 * receipts:reconcile — backfill receipts for successfully validated
 * payments that have none.
 *
 * Safety net for the event → job path: a job that exhausted its retries, a
 * signal lost during a deploy, or a receipt row that never persisted. Runs
 * on a schedule; idempotent because GenerateReceiptJob / ReceiptService::
 * issue() are idempotent per payment.
 */
final class ReconcileReceiptsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'receipts:reconcile {--limit=200 : Max payments to scan}';

    /**
     * @var string
     */
    protected $description = 'Dispatch receipt generation for captured payments that have no receipt.';

    public function handle(PaymentRepositoryContract $payments): int
    {
        $limit = (int) $this->option('limit');

        $missing = $payments->findSuccessfulWithoutReceipt($limit);

        if ($missing === []) {
            $this->info('receipts:reconcile — no missing receipts.');

            return self::SUCCESS;
        }

        foreach ($missing as $payment) {
            GenerateReceiptJob::dispatch(new Identifier($payment->id()->ulid()));
        }

        $this->info(sprintf(
            'receipts:reconcile — dispatched %d receipt generation job(s).',
            count($missing),
        ));

        return self::SUCCESS;
    }
}
