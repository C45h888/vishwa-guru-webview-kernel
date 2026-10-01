<?php

declare(strict_types=1);

namespace App\Payments\Contracts;

use App\Payments\Domain\DTOs\GatewayReconciliation;
use App\Shared\Support\Result;

/**
 * Optional capability for gateways that can report the authoritative
 * status of an existing order (including any payments made against it).
 *
 * This is deliberately separate from PaymentGatewayContract so that
 * gateway adapters which cannot reconcile (or test doubles) are not
 * forced to implement it. The orchestrator resolves the adapter for a
 * payment and, when it implements this interface, uses reconciliation
 * to converge local state with the provider's truth.
 *
 * Canonical use: a Razorpay order that is "paid" with a "captured"
 * payment, where the webhook never arrived and the local Payment row is
 * still "initialized".
 */
interface PaymentReconciliationContract
{
    /**
     * Fetch the authoritative status of an order from the gateway.
     *
     * Implementations MUST NOT mutate local state — they only report
     * what the provider says. The caller drives the PaymentStateMachine.
     *
     * @return Result<GatewayReconciliation>
     */
    public function reconcileOrder(string $gatewayOrderId): Result;
}
