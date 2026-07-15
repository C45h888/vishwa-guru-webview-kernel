<?php

declare(strict_types=1);

namespace App\Payments\Domain\Repositories;

use App\Payments\Domain\Entities\Donor;
use App\Persistence\ValueObjects\EntityId;

/**
 * Persistence boundary for the Donor aggregate.
 *
 * Donors are deduplicated by (email, phone) pair. Use findByEmail /
 * findByPhone / findByEmailOrPhone when reconciling a new donation
 * against existing donor records.
 */
interface DonorRepositoryContract
{
    /**
     * Find a donor by its internal identifier.
     */
    public function findById(EntityId $id): ?Donor;

    /**
     * Find a donor by email (case-insensitive citext lookup at the DB layer).
     */
    public function findByEmail(string $email): ?Donor;

    /**
     * Find a donor by phone (exact match; normalization is the caller's job).
     */
    public function findByPhone(string $phone): ?Donor;

    /**
     * Find a donor by either email or phone — whichever matches first.
     */
    public function findByEmailOrPhone(?string $email, ?string $phone): ?Donor;

    /**
     * Persist a new donor (INSERT).
     */
    public function save(Donor $donor): void;

    /**
     * Apply entity-level changes and persist.
     */
    public function update(Donor $donor): void;

    /**
     * Mark a donor as anonymized. After this call findByEmail/Phone
     * MUST no longer return the donor.
     */
    public function anonymize(EntityId $id): void;

    /**
     * Whether any donor exists with the given email.
     */
    public function existsWithEmail(string $email): bool;

    /**
     * Whether any donor exists with the given phone.
     */
    public function existsWithPhone(string $phone): bool;
}