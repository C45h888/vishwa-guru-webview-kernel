<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\Coordinator\MailDispatchCoordinator;
use App\Payments\Domain\Repositories\ReceiptRepositoryContract;
use App\Persistence\ValueObjects\EntityId;
use App\Shared\ValueObjects\Identifier;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * ReceiptEmailJob — the QUEUE CARRIER for receipt email delivery.
 *
 * Doctrine: the queue-level cadence (tries/backoff) and the SETEX
 * idempotency gate live here; ALL delivery logic lives behind the seam
 * (App\Mail\Coordinator\MailDispatchCoordinator), which sequences the
 * mail workers and books delivery state. This job is transport of WORK
 * between queues — nothing more.
 *
 *   - Queue = 'receipts' (dedicated worker container).
 *   - Idempotency key = "receipt:{receiptId}:email" (7d TTL) — operator
 *     re-dispatch within the window is a no-op, not a duplicate email.
 *     (Note: the durable delivery guard is the MailDispatchCoordinator's
 *     persisted already_delivered check — see Mail Kernel.md Boundary 3.)
 *   - Retry policy (2026-10-03 fix): tries=3 with backoff [10,60,300].
 *     Delivery IS idempotent (FSM + persisted delivery_status guard), so
 *     retrying a transient Hostinger API/auth blip is safe and preferable
 *     to failing permanently. The receipts:reconcile email backfill is the
 *     final safety net for anything that exhausts its retries.
 *   - Dispatched from GenerateReceiptJob AFTER receipt issuance commits
 *     (delivery never blocks or undoes a verified receipt).
 */
final class ReceiptEmailJob extends AbstractQueuedJob
{
    /** Retryable: delivery is idempotent via persisted delivery state. */
    public int $tries = 3;

    /** @var array<int, int> seconds between attempts */
    public array $backoff = [10, 60, 300];

    /**
     * Wave 1 fix (2026-08-06): Laravel's Queueable trait declares
     * $queue as an untyped public property; PHP 8+ forbids the
     * subclass from narrowing with `?string` annotation. Drop the
     * type to allow the override.
     */
    public $queue = 'receipts';

    /** 7 days — long enough to dedupe manual operator re-dispatches. */
    public function idempotencyTtl(): int
    {
        return 7 * 24 * 3600;
    }

    public function __construct(
        public readonly Identifier $receiptId,
    ) {
    }

    public function idempotencyKey(): ?string
    {
        return 'receipt:'.$this->receiptId->value().':email';
    }

    public function handle(): void
    {
        // Per the AbstractQueuedJob contract, handle() takes no arguments
        // and resolves dependencies from the container.
        $receipts = app(ReceiptRepositoryContract::class);

        $receipt = $receipts->findById(
            new EntityId('receipt', $this->receiptId->value()),
        );

        if ($receipt === null) {
            Log::warning('ReceiptEmailJob: receipt not found', [
                'receipt_id' => $this->receiptId->value(),
            ]);
            return;
        }

        $email = $receipt->donorEmail();
        if ($email === null || $email === '') {
            // Donor made the donation anonymous / no email captured.
            // Nothing to send; the receipt stays valid for download.
            Log::info('ReceiptEmailJob: skipped, no donor email', [
                'receipt_id' => $this->receiptId->value(),
            ]);
            return;
        }

        // The gated receipt URL — the access token is the only door to
        // PAN/address-bearing content.
        $signedUrl = route('receipts.show', [
            'receiptNumber' => $receipt->receiptNumber(),
            't' => $receipt->accessToken(),
        ], absolute: true);

        // ALL delivery logic lives behind the seam.
        $result = app(MailDispatchCoordinator::class)->deliverReceipt($receipt, $signedUrl);

        if ($result->isFailure()) {
            $error = (string) $result->error();
            if (str_contains($error, 'mail.no_recipient')) {
                Log::info('ReceiptEmailJob: skipped, no recipient', [
                    'receipt_id' => $this->receiptId->value(),
                ]);
                return;
            }

            Log::warning('ReceiptEmailJob: delivery failed', [
                'receipt_id' => $this->receiptId->value(),
                'reason' => $error,
            ]);

            // Surface to the queue's failed path so ops sees the gap;
            // the seam already booked the failure as delivery state.
            throw new RuntimeException('ReceiptEmailJob: '.$error);
        }

        Log::info('ReceiptEmailJob: delivered', [
            'receipt_id' => $this->receiptId->value(),
            'status' => $result->value()['status'] ?? 'unknown',
        ]);
    }
}
