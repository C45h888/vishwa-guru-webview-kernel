<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\ReceiptMailable;
use App\Payments\Domain\Entities\Receipt;
use App\Payments\Domain\Repositories\ReceiptRepositoryContract;
use App\Persistence\ValueObjects\EntityId;
use App\Shared\ValueObjects\Identifier;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * ReceiptEmailJob — fires the "your receipt is ready" email once a
 * Receipt has been persisted in ISSUED state.
 *
 * Doctrine:
 *   - Extends AbstractQueuedJob so the SETEX dedupe gate and per-class
 *     retry policy apply uniformly. Receipts are quasi-financial — once
 *     a verified payment exists, an idempotent receipt email with the
 *     correct token URL is mandatory. Tries=1, backoff=[0] (financial
 *     subclass override) so failures surface to ops immediately
 *     instead of silently retrying.
 *   - Queue = 'receipts' (matches docker-compose.yml worker
 *     `--queue=default,receipts,webhooks,notifications`).
 *   - Idempotency key = "receipt:{receiptId}:email". Re-dispatch (e.g.
 *     operator click) won't double-send within the 7d TTL.
 *
 * Wiring:
 *   - Dispatched from PaymentOrchestrator::handleWebhook() AFTER the
 *     verified-payment transaction commits and ReceiptService::issue()
 *     returns success. Receipt issuance is intentionally outside the
 *     payment transaction (so a receipt failure does not roll back a
 *     verified payment); the email dispatch lives one layer further
 *     out so an SMTP outage never blocks the webhook response.
 *   - The signed URL pattern (A3) is used for the receipt link, so
 *     donors never receive a pan/address-bearing PDF in the email body
 *     itself — they click through and the controller gates the download
 *     on `?t=<access_token>`.
 */
final class ReceiptEmailJob extends AbstractQueuedJob
{
    /** Financial — fail-fast on SMTP errors so ops sees the gap. */
    public int $tries = 1;

    /** @var array<int, int> */
    public array $backoff = [0];

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
    ) {}

    public function idempotencyKey(): ?string
    {
        // Per-receipt key — re-dispatching the same receipt (e.g. from
        // operator UI) within the TTL is a no-op rather than a duplicate
        // email.
        return 'receipt:'.$this->receiptId->value().':email';
    }

    public function handle(): void
    {
        // Per the AbstractQueuedJob contract, handle() takes no arguments
        // and resolves dependencies from the container. Wave 1 fix (2026-08-06):
        // resolve the dependency inside the method body rather than via
        // an argument that violated LSP against the parent's no-arg
        // signature and aborted the entire phpunit process with a
        // Declaration of ... must be compatible fatal.
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
            // Nothing to send. Receipt is still valid for the donor's
            // own download via the success page URL.
            Log::info('ReceiptEmailJob: skipped, no donor email', [
                'receipt_id' => $this->receiptId->value(),
            ]);
            return;
        }

        $signedUrl = route('receipts.show', [
            'receiptNumber' => $receipt->receiptNumber(),
            't' => $receipt->accessToken(),
        ], absolute: true);

        // Use a Mailable class so Mail::fake() and Mail::assertSent() can
        // track the dispatch in tests, and so future work can attach a
        // HTML view + PDF attachment by extending build() in one place.
        // Canonical typed document — the email quotes the exact display
        // values the rendered receipt document carries. Formatting lives
        // in the receipt substrate; this job stays a transport. If the
        // document service is unavailable (missing payment/donation rows),
        // fall back to the domain Money formatter so delivery still works.
        try {
            $amountFormatted = app(\App\Payments\Receipts\ReceiptSubstrate::class)
                ->documentFor($receipt)
                ->amountDisplay;
        } catch (\Throwable) {
            $amountFormatted = (new \App\Payments\Domain\ValueObjects\Money(
                $receipt->amountMinor(),
                $receipt->currency(),
            ))->format();
        }

        Mail::to($email)->send(new ReceiptMailable(
            donorName: $receipt->donorName(),
            amountFormatted: $amountFormatted,
            receiptNumber: $receipt->receiptNumber(),
            campaignTitle: $receipt->campaignTitleSnapshot(),
            signedUrl: $signedUrl,
        ));

        Log::info('ReceiptEmailJob: sent', [
            'receipt_id' => $this->receiptId->value(),
            'recipient' => $this->redactEmail($email),
        ]);
    }

    /**
     * Redact the email for log lines so we keep a useful breadcrumb
     * without writing PII to storage/logs. "a***@example.com"
     */
    private function redactEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);
        if ($local === '' || $domain === '') {
            return '***';
        }
        $first = $local[0] ?? '*';

        return $first.'***@'.$domain;
    }
}