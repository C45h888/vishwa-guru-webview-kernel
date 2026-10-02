<?php

declare(strict_types=1);

namespace App\Payments\Mail;

use App\Mail\Contracts\DeliveryBookkeepingContract;
use App\Payments\Domain\Entities\Receipt;
use App\Payments\Domain\Enums\ReceiptDeliveryState;
use App\Payments\Domain\Repositories\AuditEventRepositoryContract;
use App\Payments\Services\ReceiptService;
use App\Shared\Support\Clock;

/**
 * DeliveryBookkeepingAdapter — the Payments-side implementation of the
 * mail package's DeliveryBookkeepingContract.
 *
 * Drives delivery state through ReceiptService::markDelivered() (the
 * ReceiptStateMachine is the only mutator of delivery state) and writes
 * one audit-trail entry per outcome. Bookkeeping must never throw across
 * the seam — a failing FSM transition is swallowed and surfaced through
 * the audit trail.
 */
final class DeliveryBookkeepingAdapter implements DeliveryBookkeepingContract
{
    public function __construct(
        private readonly ReceiptService $receiptService,
        private readonly AuditEventRepositoryContract $auditLog,
        private readonly Clock $clock,
    ) {
    }

    public function markDelivered(Receipt $receipt, string $recipient): void
    {
        $this->record($receipt, ReceiptDeliveryState::DELIVERED, $recipient, null);
    }

    public function markFailed(Receipt $receipt, string $recipient, string $reason): void
    {
        $this->record($receipt, ReceiptDeliveryState::FAILED, $recipient, $reason);
    }

    private function record(
        Receipt $receipt,
        ReceiptDeliveryState $state,
        string $recipient,
        ?string $reason,
    ): void {
        try {
            $this->receiptService->markDelivered(
                receiptId: $receipt->identifier(),
                state: $state,
                channel: 'email',
                address: $recipient,
            );
        } catch (\Throwable) {
            // Bookkeeping failure is not a send failure — swallow.
        }

        try {
            $this->auditLog->append(
                eventType: $state === ReceiptDeliveryState::DELIVERED
                    ? 'mail.dispatched'
                    : 'mail.dispatch_failed',
                entityType: 'receipt',
                entityId: $receipt->id()->value(),
                correlationId: $receipt->receiptNumber(),
                previousState: $receipt->deliveryStatus(),
                newState: $state->value,
                context: [
                    'transport' => 'hostinger_mail',
                    'recipient_domain' => substr((string) strrchr($recipient, '@'), 1),
                    'reason' => $reason,
                ],
                occurredAt: $this->clock->now(),
            );
        } catch (\Throwable) {
            // Audit must not throw — swallow and continue.
        }
    }
}
