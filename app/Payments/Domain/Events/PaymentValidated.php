<?php

declare(strict_types=1);

namespace App\Payments\Domain\Events;

use App\Payments\Domain\Enums\TransactionStatus;
use DateTimeImmutable;

/**
 * PaymentValidated — the single domain signal that a payment has been
 * verified as successful by the gateway and persisted locally.
 *
 * Dispatched AFTER the capture transaction commits, from the one place a
 * payment reaches a successful state:
 *   - PaymentOrchestrator::commitGatewayStatus() — synchronous checkout
 *     callback and reconciliation paths.
 *   - PaymentOrchestrator::handleWebhook()       — async webhook path.
 *
 * ReceiptIssuanceCoordinator listens and dispatches GenerateReceiptJob
 * onto the dedicated `receipts` queue. This binds receipt generation to
 * payment validation rather than to whichever HTTP path happened to
 * capture the payment — replacing the previous scattered, inline
 * "best-effort" issuance.
 *
 * Domain events are immutable value carriers: no behaviour, no I/O.
 */
final readonly class PaymentValidated
{
    public function __construct(
        public string $paymentUlid,
        public string $gatewayOrderId,
        public TransactionStatus $status,
        public DateTimeImmutable $occurredAt,
    ) {}
}
