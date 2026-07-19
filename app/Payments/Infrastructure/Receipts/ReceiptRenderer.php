<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Receipts;

use App\Payments\Contracts\ReceiptGenerationContract;
use App\Payments\Domain\Entities\Donation;
use App\Payments\Domain\Entities\Payment;
use App\Payments\Domain\Entities\Receipt;
use App\Payments\Domain\Enums\FailureClassification;
use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Domain\Repositories\DonationRepositoryContract;
use App\Payments\Domain\Repositories\PaymentRepositoryContract;
use App\Payments\Domain\Repositories\ReceiptRepositoryContract;
use App\Payments\Domain\ValueObjects\ReceiptDraft;
use App\Payments\Services\FailureStateService;
use App\Persistence\ValueObjects\EntityId;
use App\Shared\Support\Clock;
use App\Shared\Support\Result;
use App\Shared\ValueObjects\Identifier;

/**
 * Production implementation of ReceiptGenerationContract.
 *
 * Orchestrates the full receipt pipeline:
 *   1. Load & validate Payment + Donation
 *   2. Check idempotency (already issued?)
 *   3. Allocate receipt number
 *   4. Validate 80G eligibility
 *   5. Render PDF
 *   6. Persist to file_assets
 *   7. Build ReceiptDraft
 *
 * Failure path: records FailureState with RECOVERABLE_TERMINAL and returns
 * Result::failure — the caller (ReceiptService) decides whether to throw
 * ReceiptGenerationFailedException or propagate the failure.
 *
 * No state is mutated in this class. All persistence goes through repositories.
 */
final class ReceiptRenderer implements ReceiptGenerationContract
{
    public function __construct(
        private readonly PaymentRepositoryContract $payments,
        private readonly DonationRepositoryContract $donations,
        private readonly ReceiptRepositoryContract $receipts,
        private readonly ReceiptNumberAllocator $allocator,
        private readonly ReceiptPdfGenerator $pdfGenerator,
        private readonly ReceiptStorage $storage,
        private readonly Receipt80GValidator $validator80G,
        private readonly FailureStateService $failureStateService,
        private readonly Clock $clock,
    ) {}

    // ─── ReceiptGenerationContract implementation ─────────────────────────

    /**
     * Phase 0.25 surface (unchanged shape).
     *
     * @return Result<array{receipt_number: string, issued_at: string, download_url: string, content_hash: string}>
     */
    public function generate(Identifier $transactionId): Result
    {
        $draftResult = $this->draft($transactionId);

        if ($draftResult->isFailure()) {
            return Result::failure($draftResult->error());
        }

        /** @var ReceiptDraft $draft */
        $draft = $draftResult->value();

        // Adapt to the flat array shape expected by Phase 0.25 consumers
        return Result::success([
            'receipt_number' => $draft->receiptNumber(),
            'issued_at' => $draft->issuedAt()->format(DATE_ATOM),
            'download_url' => $this->buildDownloadUrl($draft),
            'content_hash' => $draft->contentHash(),
        ]);
    }

    /**
     * Pass 1.7 rich surface.
     *
     * @return Result<ReceiptDraft>
     */
    public function draft(Identifier $transactionId): Result
    {
        // Step 1: Load Payment
        $paymentEntityId = EntityId::fromString($transactionId->value());
        $payment = $this->payments->findById($paymentEntityId);

        if ($payment === null) {
            return Result::failure("ReceiptRenderer: payment [{$transactionId->value()}] not found");
        }

        // Step 2: Verify payment is successful
        if (! $payment->status()->isSuccessful()) {
            return Result::failure(sprintf(
                'ReceiptRenderer: cannot issue receipt for payment in status [%s]',
                $payment->status()->value,
            ));
        }

        // Step 3: Idempotency — already issued?
        if ($this->receipts->existsForTransaction($payment->id())) {
            $existing = $this->receipts->findByTransactionId($payment->id());
            if ($existing !== null) {
                // Build Draft from the existing persisted receipt
                $draft = $this->buildDraftFromExistingReceipt($existing);
                if ($draft !== null) {
                    return Result::success($draft);
                }
            }
        }

        // Step 4: Load Donation for snapshot fields
        $donation = $this->donations->findById($payment->donationId());

        // Step 5: Allocate receipt number
        $receiptNumber = $this->allocator->next();

        // Step 6: Validate 80G eligibility
        $eligibilityResult = $this->validator80G->isEligible(
            $donation?->donorPanSnapshot(),
            $payment->amountMinor(),
            $payment->currency(),
            $this->clock->now(),
        );

        $eligibility = $eligibilityResult->isOk() ? $eligibilityResult->value() : [
            'eligible' => false,
            'reason' => '80G validation error',
            'certificate_required' => false,
            'certificate_number' => null,
        ];

        // Step 7: Build a preview Receipt to derive formatted fields
        $previewReceipt = $this->buildPreviewReceipt(
            $transactionId,
            $payment,
            $donation,
            $receiptNumber,
            EntityId::generate('file_asset'),
            $eligibility['certificate_number'] ?? null,
        );

        // Step 8: Render PDF
        $pdfResult = $donation !== null
            ? $this->pdfGenerator->render($previewReceipt, $payment, $donation)
            : Result::failure('Cannot render receipt without donation record');

        if ($pdfResult->isFailure()) {
            $this->recordFailure($payment->id(), 'pdf_render', $pdfResult->error());
            return Result::failure($pdfResult->error());
        }

        /** @var string $pdfBytes */
        $pdfBytes = $pdfResult->value();

        // Step 9: Persist PDF to file_assets
        $persistResult = $this->storage->persist(
            $transactionId->value(),
            EntityId::generate('receipt')->value(),
            $pdfBytes,
            'receipt_pdf',
        );

        if ($persistResult->isFailure()) {
            $this->recordFailure($payment->id(), 'file_persist', $persistResult->error());
            return Result::failure($persistResult->error());
        }

        /** @var \App\Payments\Domain\ValueObjects\FileAssetRecord $fileAsset */
        $fileAsset = $persistResult->value();

        // Step 10: Compute amount in words
        $amountInWords = AmountInWords::convert($payment->amountMinor());

        // Step 11: Build the final ReceiptDraft
        $issuedAt = $this->clock->now();

        $draft = ReceiptDraft::fromRenderer(
            transactionId: $transactionId,
            donationId: $payment->donationId()->identifier(),
            receiptNumber: $receiptNumber,
            fileAssetId: new Identifier($fileAsset->id()),
            issuedAt: $issuedAt,
            contentHash: $fileAsset->fileHashSha256(),
            amountInWords: $amountInWords,
            deliveryChannel: null,
            deliveryAddress: null,
        );

        return Result::success($draft);
    }

    public function receiptNumber(Identifier $transactionId): string
    {
        return $this->allocator->next();
    }

    public function isEnabled(): bool
    {
        // Doctrine kill-switch: receipts can be disabled per deployment
        // without touching code. ReceiptService::issue() calls this
        // before invoking draft(); when false, no PDF rendering occurs
        // and the draft pipeline returns gracefully with a failure.
        return (bool) config('receipts.enabled', true);
    }

    // ─── Private helpers ────────────────────────────────────────────────

    private function buildPreviewReceipt(
        Identifier $transactionId,
        Payment $payment,
        ?Donation $donation,
        string $receiptNumber,
        EntityId $fileAssetId,
        ?string $certificateNumber,
    ): Receipt {
        return Receipt::issue(
            donationId: $payment->donationId(),
            paymentId: $payment->id(),
            campaignId: $donation?->campaignId() ?? EntityId::generate('campaign'),
            receiptNumber: $receiptNumber,
            campaignTitleSnapshot: 'Temple donation',
            donorName: $donation?->donorNameSnapshot() ?? 'Anonymous',
            amountMinor: $payment->amountMinor(),
            currency: $payment->currency(),
            contentHash: hash('sha256', (string) time()),
            donorEmail: $donation?->donorEmailSnapshot(),
            donorPan: $donation?->donorPanSnapshot(),
            donorAddress: $donation?->donorAddressSnapshot(),
            amountInWords: AmountInWords::convert($payment->amountMinor()),
            isTaxDeductible: true,
            tax80gEligible: $certificateNumber !== null,
            tax80gCertificateNumber: $certificateNumber,
            receiptFileId: $fileAssetId,
            certificate80gFileId: null,
            deliveryChannel: null,
            deliveryAddress: null,
            deliveryMetadata: [],
            metadata: [],
            id: EntityId::generate('receipt'),
        );
    }

    private function buildDraftFromExistingReceipt(Receipt $existing): ?ReceiptDraft
    {
        $receiptFileId = $existing->receiptFileId();

        if ($receiptFileId === null) {
            return null;
        }

        return ReceiptDraft::fromRenderer(
            transactionId: $existing->paymentId()->identifier(),
            donationId: $existing->donationId()->identifier(),
            receiptNumber: $existing->receiptNumber(),
            fileAssetId: $receiptFileId->identifier(),
            issuedAt: $existing->generatedAt(),
            contentHash: $existing->contentHash(),
            amountInWords: $existing->amountInWords(),
            deliveryChannel: $existing->deliveryChannel(),
            deliveryAddress: $existing->deliveryAddress(),
        );
    }

    private function buildDownloadUrl(ReceiptDraft $draft): string
    {
        return sprintf(
            '/receipts/%s/download',
            $draft->receiptNumber(),
        );
    }

    private function recordFailure(
        EntityId $paymentId,
        string $stage,
        ?string $reason,
    ): void {
        try {
            $this->failureStateService->record(
                paymentId: $paymentId,
                providerCode: 'receipt_pipeline',
                gatewayOrderId: $paymentId->value(),
                observedStatus: TransactionStatus::CAPTURED,
                failureCode: "receipt.{$stage}.failed",
                failureReason: $reason,
                classification: FailureClassification::RECOVERABLE_TERMINAL,
                metadata: ['stage' => $stage],
                context: [
                    'correlation_id' => 'receipt-' . bin2hex(random_bytes(6)),
                ],
            );
        } catch (\Throwable) {
            // FailureStateService must not throw — swallow and continue
        }
    }
}
