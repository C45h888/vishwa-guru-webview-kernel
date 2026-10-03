<?php

declare(strict_types=1);

namespace App\Payments\Domain\Repositories;

use App\Payments\Domain\Entities\Receipt;
use App\Persistence\ValueObjects\EntityId;
use DateTimeImmutable;

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
     *
     * NOTE: this lookup must NOT be used to authorize public access —
     * the receipt number is sequentially enumerable. Use
     * findByAccessToken() for the public URL credential, or restrict
     * calls of this method to admin/authed surfaces.
     */
    public function findByReceiptNumber(string $receiptNumber): ?Receipt;

    /**
     * Find a receipt by its URL-safe random access token. This is the
     * ONLY public-safe lookup. Used by /receipts/{number}?t=<token>.
     */
    public function findByAccessToken(string $accessToken): ?Receipt;

    /**
     * Find all receipts whose generated_at timestamp falls within the
     * inclusive [from, to] window. Soft-deleted rows are excluded.
     * Caller-side filters (80G eligibility, minimum amount) belong in
     * the calling service — this method stays a pure range scan.
     *
     * Used by Form10BDExporter quarterly filing.
     *
     * @return array<int, Receipt>
     */
    public function findByDateRange(DateTimeImmutable $from, DateTimeImmutable $to): array;

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

    /**
     * Find the highest receipt_number issued in a fiscal year.
     * Used by ReceiptNumberAllocator to issue the next sequential id.
     *
     * @return string|null e.g. "TR-2026-000042" or null if none yet
     */
    public function findMaxReceiptNumberForFY(int $fiscalYear): ?string;

    /**
     * Email delivery backfill candidates for the receipts:reconcile loop.
     *
     * Returns receipts whose email delivery is not durably complete:
     * delivery_status in ('failed','bounced') at any age, plus 'pending'
     * receipts whose updated_at is older than the grace window (to avoid
     * racing an in-flight ReceiptEmailJob). Rows without a donor email
     * are excluded (nothing to send). Returned oldest-first, capped.
     *
     * @param  int  $pendingOlderThanSeconds  grace window for pending rows
     * @param  int  $limit                    result cap (default 200)
     * @return array<int, \App\Payments\Domain\Entities\Receipt>
     */
    public function findUncompletedDeliveries(
        int $pendingOlderThanSeconds,
        int $limit = 200,
    ): array;
}