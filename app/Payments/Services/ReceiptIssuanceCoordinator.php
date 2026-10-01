<?php

declare(strict_types=1);

namespace App\Payments\Services;

use App\Payments\Domain\Events\PaymentValidated;
use App\Payments\Domain\Repositories\AuditEventRepositoryContract;
use App\Payments\Domain\Repositories\PaymentRepositoryContract;
use App\Payments\Domain\Repositories\ReceiptRepositoryContract;
use App\Payments\Jobs\GenerateReceiptJob;
use App\Persistence\ValueObjects\EntityId;
use App\Shared\Support\Clock;
use App\Shared\ValueObjects\Identifier;

/**
 * ReceiptIssuanceCoordinator — the single "signal → receipt" handler.
 *
 * Listens to PaymentValidated (dispatched after a payment commits in a
 * successful state) and dispatches GenerateReceiptJob onto the dedicated
 * `receipts` queue. This is the architectural binding point: receipt
 * generation is a consequence of payment validation, not a side-effect
 * of whichever HTTP path captured the payment.
 *
 * Responsibilities:
 *   1. Defense-in-depth — confirm the payment is actually successful.
 *   2. Idempotency — skip when a receipt already exists.
 *   3. Dispatch the queued generation job.
 *   4. Audit the signal.
 *
 * The coordinator never renders or persists a receipt itself; that is the
 * job's (and ReceiptService's) responsibility. Keeping the listener thin
 * means a slow/failing PDF render can never block the payment response.
 */
final class ReceiptIssuanceCoordinator
{
    public function __construct(
        private readonly PaymentRepositoryContract $payments,
        private readonly ReceiptRepositoryContract $receipts,
        private readonly AuditEventRepositoryContract $auditLog,
        private readonly Clock $clock,
    ) {}

    public function handle(PaymentValidated $event): void
    {
        $payment = $this->payments->findById(
            new EntityId('payment', $event->paymentUlid),
        );

        // Defense-in-depth: only a successfully validated payment earns a
        // receipt. The event is only dispatched on success, but re-check so
        // the coordinator is safe against any future misuse.
        if ($payment === null || ! $payment->status()->isSuccessful()) {
            return;
        }

        // Idempotency: a receipt already exists for this payment.
        if ($this->receipts->existsForTransaction($payment->id())) {
            return;
        }

        GenerateReceiptJob::dispatch(new Identifier($event->paymentUlid));

        $this->auditLog->append(
            eventType: 'payment.receipt.signalled',
            entityType: 'payment',
            entityId: $event->paymentUlid,
            correlationId: $event->gatewayOrderId,
            previousState: $event->status->value,
            newState: $event->status->value,
            context: [
                'gateway_order_id' => $event->gatewayOrderId,
                'job' => GenerateReceiptJob::class,
                'queue' => 'receipts',
            ],
            occurredAt: $this->clock->now(),
        );
    }
}
