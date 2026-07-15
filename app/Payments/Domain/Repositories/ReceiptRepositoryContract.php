<?php

declare(strict_types=1);

namespace App\Payments\Domain\Repositories;

use App\Payments\Domain\Entities\Receipt;
use App\Persistence\ValueObjects\EntityId;

/**
 * Persistence boundary for the Receipt aggregate.
 */
interface ReceiptRepositoryContract
{
    /**
     * Find a receipt by its internal identifier.
     */
    public function findById(EntityId $id): ?Receipt;

    /**
     * Find the receipt for a transaction.
     * Receipts are 1:1 with transactions in the V1 schema.
     */
    public function findByTransactionId(EntityId $transactionId): ?Receipt;

    /**
     * Find the receipt for a donation.
     * Receipts are 1:1 with donations in the V1 schema.
     */
    public function findByDonationId(EntityId $donationId): ?Receipt;

    /**
     * Find a receipt by its human-readable number (TR-YYYY-{shortId}).
     */
    public function findByReceiptNumber(string $receiptNumber): ?Receipt;

    /**
     * Persist a new receipt (INSERT).
     */
    public function save(Receipt $receipt): void;

    /**
     * Apply entity-level changes and persist.
     * Only delivery-tracking fields may be changed; the implementation
     * MUST reject content-field updates (Receipt::withChanges enforces
     * this at the domain layer; the repo MAY add a defensive check).
     */
    public function update(Receipt $receipt): void;

    /**
     * Update only delivery tracking fields.
     */
    public function updateDelivery(
        EntityId $id,
        string $deliveryStatus,
        ?string $deliveredAt = null,
    ): Receipt;

    /**
     * Whether a receipt exists for the given transaction.
     */
    public function existsForTransaction(EntityId $transactionId): bool;
}