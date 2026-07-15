<?php

declare(strict_types=1);

namespace App\Payments\Domain\Repositories;

use App\Payments\Domain\Entities\Payment;
use App\Payments\Domain\Enums\TransactionStatus;
use App\Persistence\ValueObjects\EntityId;

/**
 * Persistence boundary for the Payment aggregate.
 *
 * Implementations are owned by the Persistence module; the Payments
 * domain depends only on this contract. Payment rows MUST be looked up
 * by either EntityId (internal) or provider_order_id (gateway-side),
 * never by raw ULID strings.
 */
interface PaymentRepositoryContract
{
    /**
     * Find a payment by its internal identifier.
     */
    public function findById(EntityId $id): ?Payment;

    /**
     * Find a payment by the gateway-supplied order identifier.
     * Returns null if no payment matches.
     */
    public function findByGatewayOrderId(string $gatewayOrderId): ?Payment;

    /**
     * Find the payment associated with a donation.
     * The schema enforces payments.donation_id as UNIQUE — at most one
     * payment per donation.
     */
    public function findByDonationId(EntityId $donationId): ?Payment;

    /**
     * Find a payment by idempotency key.
     * Used to detect duplicate payment initialization requests.
     */
    public function findByIdempotencyKey(string $idempotencyKey): ?Payment;

    /**
     * Persist a new payment (INSERT).
     */
    public function save(Payment $payment): void;

    /**
     * Apply entity-level changes and persist.
     * The repository MUST call Payment::withChanges() internally so all
     * transitions are state-machine-validated before write.
     */
    public function update(Payment $payment): void;

    /**
     * Update only the status field, with state machine validation.
     * Returns the updated entity.
     */
    public function updateStatus(EntityId $id, TransactionStatus $newStatus): Payment;

    /**
     * Whether a payment exists for the given gateway order id.
     */
    public function existsForGatewayOrder(string $gatewayOrderId): bool;

    /**
     * Count payments matching the given status. Used for diagnostics.
     */
    public function countByStatus(TransactionStatus $status): int;/**
     * Batch lookup multiple payments by gateway order id.
     * Returns a map keyed by gatewayOrderId; missing ids are simply absent.
     *
     * @param  array<int, string>  $gatewayOrderIds
     * @return array<string, Payment>
     */
    public function findManyByGatewayOrderIds(array $gatewayOrderIds): array;

    /**
     * SELECT ... FOR UPDATE row lock. MUST be called inside a transaction.
     * Used by the PaymentService to serialize concurrent state advances on
     * the same payment (e.g. two webhook callbacks arriving simultaneously).
     */
    public function lockByIdForUpdate(EntityId $id): ?Payment;

    /**
     * SELECT ... FOR UPDATE row lock by gateway order id. MUST be inside a transaction.
     */
    public function lockByGatewayOrderIdForUpdate(string $gatewayOrderId): ?Payment;
}