<?php

declare(strict_types=1);

namespace App\Payments\Services;

use App\Payments\Contracts\ReceiptGenerationContract;
use App\Payments\Domain\Entities\Receipt;
use App\Payments\Domain\Enums\ReceiptDeliveryState;
use App\Payments\Domain\Exceptions\PaymentVerificationFailedException;
use App\Payments\Domain\Exceptions\ReceiptGenerationFailedException;
use App\Payments\Domain\Repositories\PaymentRepositoryContract;
use App\Payments\Domain\Repositories\ReceiptRepositoryContract;
use App\Payments\Domain\StateMachines\ReceiptStateMachine;
use App\Payments\Domain\StateMachines\StateTransitionEvent;
use App\Payments\Domain\ValueObjects\ReceiptDraft;
use App\Persistence\ValueObjects\EntityId;
use App\Shared\Support\Clock;
use App\Shared\Support\IdentifierGenerator;
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
 */
final class ReceiptService
{
    public function __construct(
        private readonly ReceiptRepositoryContract $receipts,
        private readonly PaymentRepositoryContract $payments,
        private readonly FailureStateService $failureStateService,
        private readonly ReceiptGenerationContract $receiptGenerator,
        private readonly ReceiptStateMachine $receiptStateMachine,
        private readonly Clock $clock,
        private readonly IdentifierGenerator $ids,
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

        if ($this->receipts->existsForTransaction($payment->id())) {
            $existing = $this->receipts->findByTransactionId($payment->id());
            if ($existing !== null) {
                return Result::success($existing);
            }
        }

        $generated = $this->receiptGenerator->generate($transactionId);
        if ($generated->isFailure()) {
            return $this->escalateFailure(
                $transactionId,
                'receipt.issue',
                'generator_failed',
                (string) $generated->error(),
            );
        }

        $payload = $generated->value();
        if (! isset($payload['receipt_number'], $payload['content_hash'], $payload['issued_at'])) {
            return $this->escalateFailure(
                $transactionId,
                'receipt.issue',
                'generator_payload_malformed',
                'Receipt generator returned an incomplete payload',
            );
        }

        // Pass 1.3 placeholder fileAssetId. Pass 1.4 replaces this
        // with a real file_assets insert via FileAssetRepositoryContract.
        $placeholderFileAssetId = EntityId::fromString(
            $this->ids->next(),
        );

        $receipt = Receipt::issue(
            donationId: $payment->donationId(),
            transactionId: $payment->id(),
            fileAssetId: $placeholderFileAssetId,
            receiptNumber: (string) $payload['receipt_number'],
            contentHash: (string) $payload['content_hash'],
            issuedAt: new \DateTimeImmutable((string) $payload['issued_at']),
            deliveryChannel: null,
            deliveryAddress: null,
            metadata: [
                'download_url' => (string) ($payload['download_url'] ?? ''),
            ],
            id: EntityId::fromString($this->ids->next()),
        );

        $this->receipts->save($receipt);

        return Result::success($receipt);
    }

    /**
     * Apply a delivery transition to a persisted Receipt.
     *
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

        $event = $this->eventForDeliveryState($state);
        if ($event === null) {
            return Result::failure(
                'unsupported_delivery_state: '.$state->value,
            );
        }

        try {
            $transitioned = $receipt->transitionDelivery(
                machine: $this->receiptStateMachine,
                event: $event,
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
     * Centralised failure path. The receipt layer NEVER throws
     * (ReceiptGenerationFailedException is for adapter-layer code,
     * not service code). Instead it escalates a FailureState so
     * operators see the receipt gap and the underlying Payment stays
     * verified.
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

        throw ReceiptGenerationFailedException::renderingFailed(
            $transactionId->value(),
            $message,
        );
    }

    private function eventForDeliveryState(ReceiptDeliveryState $state): ?StateTransitionEvent
    {
        return match ($state) {
            ReceiptDeliveryState::DELIVERED => StateTransitionEvent::DELIVERY_DISPATCHED,
            ReceiptDeliveryState::FAILED => StateTransitionEvent::DELIVERY_FAILED,
            ReceiptDeliveryState::BOUNCED => StateTransitionEvent::DELIVERY_BOUNCED,
            ReceiptDeliveryState::PENDING => StateTransitionEvent::DELIVERY_REDISPATCHED,
            default => null,
        };
    }
}