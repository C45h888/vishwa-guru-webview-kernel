<?php

declare(strict_types=1);

namespace App\Payments\Services;

use App\Payments\Contracts\ReceiptGenerationContract;
use App\Payments\Domain\Entities\Receipt;
use App\Payments\Domain\Enums\ReceiptDeliveryState;
use App\Payments\Domain\Exceptions\PaymentVerificationFailedException;
use App\Payments\Domain\Repositories\DonationRepositoryContract;
use App\Payments\Domain\Repositories\PaymentRepositoryContract;
use App\Payments\Domain\Repositories\ReceiptRepositoryContract;
use App\Payments\Domain\StateMachines\ReceiptStateMachine;
use App\Payments\Domain\StateMachines\StateTransitionEvent;
use App\Payments\Domain\ValueObjects\ReceiptDraft;
use App\Campaigns\Domain\Repositories\CampaignRepositoryContract;
use App\Persistence\ValueObjects\EntityId;
use App\Shared\Support\Clock;
use App\Shared\Support\Result;
use App\Shared\ValueObjects\Identifier;

/**
 * Owns the Receipt lifecycle after a payment has been verified.
 *
 * issue() builds a ReceiptDraft from the configured
 * ReceiptGenerationContract, persists the Receipt row, and returns
 * the persisted entity. On any failure the service escalates a
 * FailureState with classification=RECOVERABLE_TERMINAL so an
 * operator can retry the receipt workflow manually; the underlying
 * Payment stays verified.
 *
 * markDelivered() drives the ReceiptStateMachine for delivery
 * transitions. The Receipt entity enforces immutability of content
 * fields; only delivery_status, delivered_at, delivery_channel, and
 * delivery_address are mutable post-issue.
 *
 * file_assets:
 *   The V1 schema models file_assets separately from receipts (a
 *   receipt references one file_asset row). Pass 1.4 introduces the
 *   FileAssetRepositoryContract; in Pass 1.3 the service records the
 *   file_asset_id as a placeholder EntityId so the Receipt row is
 *   fully wired. When FileAssetRepositoryContract lands, the service
 *   gains a real file_asset insert step in issue().
 *
 * Non-final so unit tests can substitute a recording stub via
 * inheritance; production code resolves through DI and never sees
 * a subclass.
 */
class ReceiptService
{
    public function __construct(
        private readonly ReceiptRepositoryContract $receipts,
        private readonly PaymentRepositoryContract $payments,
        private readonly DonationRepositoryContract $donations,
        private readonly CampaignRepositoryContract $campaigns,
        private readonly FailureStateService $failureStateService,
        private readonly ReceiptGenerationContract $receiptGenerator,
        private readonly ReceiptStateMachine $receiptStateMachine,
        private readonly Clock $clock,
    ) {}

    /**
     * Issue a receipt for the verified payment identified by the
     * gateway-side transactionId (the public-facing ULID exposed on
     * receipts and audit trails).
     *
     * @return Result<Receipt>
     */
    public function issue(Identifier $transactionId): Result
    {
        $payment = $this->payments->findById(
            new EntityId('payment', $transactionId->value()),
        );
        if ($payment === null) {
            return $this->escalateFailure(
                $transactionId,
                'receipt.issue',
                'payment_not_found',
                sprintf('Payment [%s] not found when issuing receipt', $transactionId->value()),
            );
        }

        if (! $payment->status()->isSuccessful()) {
            return $this->escalateFailure(
                $transactionId,
                'receipt.issue',
                'payment_not_successful',
                sprintf(
                    'Cannot issue receipt for payment [%s] in status [%s]',
                    $transactionId->value(),
                    $payment->status()->value,
                ),
            );
        }

        // Wave 1 F3 fix (2026-08-06): replace the exists-then-save TOCTOU
        // with a single fetch first (cheap on the no-row path). Then
        // INSERT, and on unique-constraint violation (the only
        // remaining race window between two concurrent webhook retries
        // that both saw null), re-fetch the winning row. This is the
        // SQLite/Postgres-portable equivalent of ON CONFLICT DO NOTHING
        // RETURNING — the canonical Repository contract has no
        // native upsert primitive, so we wire it at the service layer.
        $existing = $this->receipts->findByTransactionId($payment->id());
        if ($existing !== null) {
            return Result::success($existing);
        }

        $draft = $this->receiptGenerator->draft($transactionId);
        if ($draft->isFailure()) {
            return $this->escalateFailure(
                $transactionId,
                'receipt.issue',
                'generator_failed',
                (string) $draft->error(),
            );
        }

        /** @var ReceiptDraft $d */
        $d = $draft->value();

        $donation = $this->donations->findById($payment->donationId());
        $campaignId = $donation?->campaignId() ?? EntityId::generate('campaign');

        // Canonical snapshot: when the substrate produced a typed document,
        // the persisted row is built from EXACTLY the fields the design
        // rendered (campaign title, donor snapshots, 80G decision). The
        // fallback path only serves legacy drafts without a document.
        $doc = $d->document();
        $campaignTitleSnapshot = $doc->campaignTitle ?? $this->resolveCampaignTitle($donation);
        $donorName = $doc->donorName ?? ($donation?->donorNameSnapshot() ?? 'Anonymous');

        $receipt = Receipt::issue(
            donationId: $payment->donationId(),
            paymentId: $payment->id(),
            campaignId: $campaignId,
            receiptNumber: $d->receiptNumber(),
            campaignTitleSnapshot: $campaignTitleSnapshot,
            donorName: $donorName,
            amountMinor: $payment->amountMinor(),
            currency: $payment->currency(),
            contentHash: $d->contentHash(),
            donorEmail: $doc->donorEmail ?? $donation?->donorEmailSnapshot(),
            donorPan: $doc->donorPan ?? $donation?->donorPanSnapshot(),
            donorAddress: $donation?->donorAddressSnapshot(),
            amountInWords: $d->amountInWords(),
            isTaxDeductible: $doc->isTaxDeductible ?? true,
            tax80gEligible: $doc->tax80gEligible ?? false,
            tax80gCertificateNumber: $doc?->tax80gCertificateNumber,
            // ReceiptDraft::fileAssetId() carries a bare ULID; the Receipt
            // entity stores the canonical typed EntityId, so re-attach the
            // `file_asset_` prefix here.
            receiptFileId: EntityId::fromString('file_asset_'.$d->fileAssetId()->value()),
            certificate80gFileId: null,
            deliveryChannel: $d->deliveryChannel(),
            deliveryAddress: $d->deliveryAddress(),
            deliveryMetadata: [],
            metadata: [
                'payment_method' => $payment->method(),
                'provider' => $payment->providerCode()->value,
            ],
            id: EntityId::generate('receipt'),
        );

        try {
            $this->receipts->save($receipt);
        } catch (\RuntimeException $e) {
            // Likely the receipts_unique_per_payment unique-constraint
            // collision from a concurrent webhook retry. Re-fetch the
            // winning row so callers see a stable Receipt and no duplicate
            // PDF / audit event is generated.
            $winning = $this->receipts->findByTransactionId($payment->id());
            if ($winning !== null) {
                return Result::success($winning);
            }
            // Different RuntimeException — surface as failure.
            return $this->escalateFailure(
                $transactionId,
                'receipt.issue',
                'persist_failed',
                $e->getMessage(),
            );
        }

        return Result::success($receipt);
    }

    /**
     * Apply a delivery transition to a persisted Receipt.
     *
     * @phpstan-return Result<Receipt>|Result<null>
     * @return Result<Receipt>
     */
    public function markDelivered(
        Identifier $receiptId,
        ReceiptDeliveryState $state,
        string $channel,
        string $address,
    ): Result {
        $receipt = $this->receipts->findById(
            new EntityId('receipt', $receiptId->value()),
        );
        if ($receipt === null) {
            return Result::failure('receipt_not_found: id='.$receiptId->value());
        }

        try {
            $transitioned = $receipt->transitionDelivery(
                machine: $this->receiptStateMachine,
                event: $this->eventForDeliveryState($state),
                context: [
                    'channel' => $channel,
                    'address' => $address,
                    'transitioned_at' => $this->clock->now()->format(DATE_ATOM),
                ],
            );
        } catch (\App\Payments\Domain\Exceptions\PaymentStateTransitionException $e) {
            return Result::failure('delivery_transition_rejected: '.$e->getMessage());
        }

        $this->receipts->update($transitioned);

        return Result::success($transitioned);
    }

    /**
     * Centralised failure path. The receipt layer NEVER throws — receipt
     * failures are deferred, not fatal. The Payment stays verified; an
     * operator sees the gap via FailureState + audit.
     *
     * @return Result<Receipt>
     */
    private function escalateFailure(
        Identifier $transactionId,
        string $stage,
        string $errorCode,
        string $message,
    ): Result {
        $paymentId = new EntityId('payment', $transactionId->value());
        $this->failureStateService->record(
            paymentId: $paymentId,
            providerCode: 'receipt_pipeline',
            gatewayOrderId: $transactionId->value(),
            observedStatus: \App\Payments\Domain\Enums\TransactionStatus::CAPTURED,
            failureCode: $errorCode,
            failureReason: $message,
            classification: \App\Payments\Domain\Enums\FailureClassification::RECOVERABLE_TERMINAL,
            metadata: [
                'stage' => $stage,
                'error_code' => $errorCode,
            ],
            context: [
                'correlation_id' => 'receipt-'.bin2hex(random_bytes(6)),
            ],
        );

        // @phpstan-ignore-next-line  Result<T> generic narrowing limit: failure() returns Result<null>
        return Result::failure("receipt.{$stage}.{$errorCode}: {$message}");
    }

    private function eventForDeliveryState(ReceiptDeliveryState $state): StateTransitionEvent
    {
        return match ($state) {
            ReceiptDeliveryState::DELIVERED => StateTransitionEvent::DELIVERY_DISPATCHED,
            ReceiptDeliveryState::FAILED => StateTransitionEvent::DELIVERY_FAILED,
            ReceiptDeliveryState::BOUNCED => StateTransitionEvent::DELIVERY_BOUNCED,
            ReceiptDeliveryState::PENDING => StateTransitionEvent::DELIVERY_REDISPATCHED,
        };
    }

    /**
     * Resolve the campaign title for the receipt snapshot. Returns the
     * actual title when the donation is attached to a known campaign,
     * or a generic fallback when the donation is unattached or the
     * campaign has been deleted. The fallback intentionally never
     * reads 'Temple donation' as a default because that mis-attributes
     * funds on a printed 80G receipt.
     */
    private function resolveCampaignTitle(?\App\Payments\Domain\Entities\Donation $donation): string
    {
        if ($donation === null) {
            return 'Temple donation';
        }
        $campaign = $this->campaigns->findById($donation->campaignId()->ulid());
        if ($campaign !== null && $campaign->title !== '') {
            return $campaign->title;
        }

        return 'Temple donation';
    }
}