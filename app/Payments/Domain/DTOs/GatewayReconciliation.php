<?php

declare(strict_types=1);

namespace App\Payments\Domain\DTOs;

use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Enums\TransactionStatus;

/**
 * Authoritative status of a gateway order as reported by the provider
 * during reconciliation.
 *
 * Reconciliation is the backend safety net that closes the gap between
 * "the donor paid" and "our local Payment row knows about it" when the
 * async webhook is delayed, misconfigured, or never delivered.
 *
 * Unlike GatewayResponseDTO (which models the order-creation response),
 * this DTO models the provider's *settled truth* about an order that may
 * already have a captured payment: its effective status, the provider
 * payment id, the captured amount, currency, and method.
 */
final readonly class GatewayReconciliation
{
    public function __construct(
        public TransactionStatus $status,
        public ?string $gatewayPaymentId,
        public int $amountMinor,
        public Currency $currency,
        public ?string $method = null,
    ) {}
}
