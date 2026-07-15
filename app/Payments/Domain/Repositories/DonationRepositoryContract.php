<?php

declare(strict_types=1);

namespace App\Payments\Domain\Repositories;

use App\Payments\Domain\Entities\Donation;
use App\Payments\Domain\Enums\DonationState;
use App\Persistence\ValueObjects\EntityId;

/**
 * Persistence boundary for the Donation aggregate.
 */
interface DonationRepositoryContract
{
    /**
     * Find a donation by its internal identifier.
     */
    public function findById(EntityId $id): ?Donation;

    /**
     * Find a donation by its idempotency key (when supplied at creation).
     * Used to detect duplicate donation requests.
     */
    public function findByIdempotencyKey(string $idempotencyKey): ?Donation;

    /**
     * Find all donations for a campaign.
     *
     * @return array<int, Donation>
     */
    public function findByCampaignId(EntityId $campaignId, int $limit = 100, int $offset = 0): array;

    /**
     * Find all donations by a donor.
     *
     * @return array<int, Donation>
     */
    public function findByDonorId(EntityId $donorId, int $limit = 100, int $offset = 0): array;

    /**
     * Persist a new donation (INSERT).
     */
    public function save(Donation $donation): void;

    /**
     * Apply entity-level changes and persist.
     */
    public function update(Donation $donation): void;

    /**
     * Update only the state field, with state machine validation.
     */
    public function updateState(EntityId $id, DonationState $newState): Donation;

    /**
     * Whether a donation exists for the given campaign + idempotency key.
     */
    public function existsForIdempotencyKey(string $idempotencyKey): bool;

    /**
     * Count donations matching the given state.
     */
    public function countByState(DonationState $state): int;/**
     * Index lookup: payments.gateway_order_id → donation.
     * Used by the webhook handler to recover the donation from a
     * gateway callback when only the gateway-side identifier is known.
     */
    public function findByGatewayOrderId(string $gatewayOrderId): ?Donation;

    /**
     * SELECT ... FOR UPDATE row lock on a donation. Use during
     * concurrent verification rounds to serialize idempotent processing.
     */
    public function lockByIdForUpdate(EntityId $id): ?Donation;

    /**
     * SELECT ... FOR UPDATE row lock on a donation by idempotency key.
     * Used by the DonationService to detect duplicate creation attempts
     * while a previous transaction is still committing.
     */
    public function lockByIdempotencyKeyForUpdate(string $idempotencyKey): ?Donation;
}