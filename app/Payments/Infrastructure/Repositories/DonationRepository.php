<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Repositories;

use App\Payments\Domain\Entities\Donation;
use App\Payments\Domain\Enums\DonationState;
use App\Payments\Domain\Repositories\DonationRepositoryContract;
use App\Persistence\Contracts\PersistenceAdapterContract;
use App\Persistence\ValueObjects\EntityId;
use RuntimeException;

final class DonationRepository implements DonationRepositoryContract
{
    public function __construct(
        private readonly PersistenceAdapterContract $adapter,
    ) {}

    public function findById(EntityId $id): ?Donation
    {
        $result = $this->adapter->query(
            'SELECT * FROM donations WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id->value()],
        );
        if ($result->isFailure() || empty($result->value())) {
            return null;
        }

        return Donation::fromRow($result->value()[0]);
    }

    public function findByIdempotencyKey(string $idempotencyKey): ?Donation
    {
        $result = $this->adapter->query(
            'SELECT * FROM donations WHERE idempotency_key = :key AND deleted_at IS NULL LIMIT 1',
            ['key' => $idempotencyKey],
        );
        if ($result->isFailure() || empty($result->value())) {
            return null;
        }

        return Donation::fromRow($result->value()[0]);
    }

    /**
     * @return array<int, Donation>
     */
    public function findByCampaignId(EntityId $campaignId, int $limit = 100, int $offset = 0): array
    {
        $result = $this->adapter->query(
            'SELECT * FROM donations WHERE campaign_id = :cid AND deleted_at IS NULL ORDER BY created_at DESC LIMIT :limit OFFSET :offset',
            ['cid' => $campaignId->value(), 'limit' => $limit, 'offset' => $offset],
        );
        if ($result->isFailure()) {
            return [];
        }

        return array_map(
            static fn (array $row): Donation => Donation::fromRow($row),
            $result->value(),
        );
    }

    /**
     * @return array<int, Donation>
     */
    public function findByDonorId(EntityId $donorId, int $limit = 100, int $offset = 0): array
    {
        $result = $this->adapter->query(
            'SELECT * FROM donations WHERE donor_id = :did AND deleted_at IS NULL ORDER BY created_at DESC LIMIT :limit OFFSET :offset',
            ['did' => $donorId->value(), 'limit' => $limit, 'offset' => $offset],
        );
        if ($result->isFailure()) {
            return [];
        }

        return array_map(
            static fn (array $row): Donation => Donation::fromRow($row),
            $result->value(),
        );
    }

    public function save(Donation $donation): void
    {
        $row = $donation->toArray();

        $sql = 'INSERT INTO donations (
            id, campaign_id, donor_id, donor_name_snapshot, donor_email_snapshot,
            donor_phone_snapshot, donor_pan_snapshot, donor_address_snapshot,
            amount_minor, currency_code, is_anonymous, dedication,
            donor_message, internal_notes, state, idempotency_key,
            metadata, submitted_at, payment_initiated_at, payment_verified_at,
            receipt_generated_at, completed_at, failed_at, cancelled_at,
            created_at, updated_at, deleted_at, created_by, updated_by
        ) VALUES (
            :id, :campaign_id, :donor_id, :donor_name_snapshot, :donor_email_snapshot,
            :donor_phone_snapshot, :donor_pan_snapshot, :donor_address_snapshot,
            :amount_minor, :currency_code, :is_anonymous, :dedication,
            :donor_message, :internal_notes, :state, :idempotency_key,
            :metadata, :submitted_at, :payment_initiated_at, :payment_verified_at,
            :receipt_generated_at, :completed_at, :failed_at, :cancelled_at,
            :created_at, :updated_at, :deleted_at, :created_by, :updated_by
        )';

        $params = [
            'id' => $row['id'],
            'campaign_id' => $row['campaign_id'],
            'donor_id' => $row['donor_id'],
            'donor_name_snapshot' => $row['donor_name_snapshot'],
            'donor_email_snapshot' => $row['donor_email_snapshot'],
            'donor_phone_snapshot' => $row['donor_phone_snapshot'],
            'donor_pan_snapshot' => $row['donor_pan_snapshot'],
            // Doctrine: preserve NULL for JSONB columns. The previous
            // `?? []` fallback encoded null → '[]', which violated the
            // `donations_anonymous_no_pii` CHECK constraint (it requires
            // NULL, not empty array). When a non-null array is provided
            // (e.g. {'city': 'Mumbai'}), it must be JSON-encoded as text
            // so PDO sends a valid JSON literal to the JSONB column.
            'donor_address_snapshot' => $row['donor_address_snapshot'] === null
                ? null
                : (is_string($row['donor_address_snapshot'])
                    ? $row['donor_address_snapshot']
                    : json_encode($row['donor_address_snapshot'], JSON_THROW_ON_ERROR)),
            'amount_minor' => $row['amount_minor'],
            'currency_code' => $row['currency_code'],
            'is_anonymous' => $row['is_anonymous'],
            'dedication' => $row['dedication'],
            'donor_message' => $row['donor_message'],
            'internal_notes' => $row['internal_notes'],
            'state' => $row['state'],
            'idempotency_key' => $row['idempotency_key'],
            'metadata' => $row['metadata'] === null
                ? null
                : (is_string($row['metadata'])
                    ? $row['metadata']
                    : json_encode($row['metadata'], JSON_THROW_ON_ERROR)),
            'submitted_at' => $row['submitted_at'],
            'payment_initiated_at' => $row['payment_initiated_at'],
            'payment_verified_at' => $row['payment_verified_at'],
            'receipt_generated_at' => $row['receipt_generated_at'],
            'completed_at' => $row['completed_at'],
            'failed_at' => $row['failed_at'],
            'cancelled_at' => $row['cancelled_at'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'deleted_at' => $row['deleted_at'],
            'created_by' => $row['created_by'] ?? null,
            'updated_by' => $row['updated_by'] ?? null,
        ];

        $exec = $this->adapter->execute($sql, $params);
        if ($exec->isFailure()) {
            throw new RuntimeException('DonationRepository::save failed: '.$exec->error());
        }
    }

    public function update(Donation $donation): void
    {
        $row = $donation->toArray();

        $sql = 'UPDATE donations SET
            campaign_id = :campaign_id,
            donor_id = :donor_id,
            donor_name_snapshot = :donor_name_snapshot,
            donor_email_snapshot = :donor_email_snapshot,
            donor_phone_snapshot = :donor_phone_snapshot,
            donor_pan_snapshot = :donor_pan_snapshot,
            donor_address_snapshot = :donor_address_snapshot,
            amount_minor = :amount_minor,
            currency_code = :currency_code,
            is_anonymous = :is_anonymous,
            dedication = :dedication,
            donor_message = :donor_message,
            internal_notes = :internal_notes,
            state = :state,
            idempotency_key = :idempotency_key,
            metadata = :metadata,
            submitted_at = :submitted_at,
            payment_initiated_at = :payment_initiated_at,
            payment_verified_at = :payment_verified_at,
            receipt_generated_at = :receipt_generated_at,
            completed_at = :completed_at,
            failed_at = :failed_at,
            cancelled_at = :cancelled_at,
            updated_at = :updated_at,
            updated_by = :updated_by
        WHERE id = :id';

        $params = [
            'id' => $row['id'],
            'campaign_id' => $row['campaign_id'],
            'donor_id' => $row['donor_id'],
            'donor_name_snapshot' => $row['donor_name_snapshot'],
            'donor_email_snapshot' => $row['donor_email_snapshot'],
            'donor_phone_snapshot' => $row['donor_phone_snapshot'],
            'donor_pan_snapshot' => $row['donor_pan_snapshot'],
            // Doctrine: preserve NULL for JSONB columns. The previous
            // `?? []` fallback encoded null → '[]', which violated the
            // `donations_anonymous_no_pii` CHECK constraint (it requires
            // NULL, not empty array). When a non-null array is provided
            // (e.g. {'city': 'Mumbai'}), it must be JSON-encoded as text
            // so PDO sends a valid JSON literal to the JSONB column.
            'donor_address_snapshot' => $row['donor_address_snapshot'] === null
                ? null
                : (is_string($row['donor_address_snapshot'])
                    ? $row['donor_address_snapshot']
                    : json_encode($row['donor_address_snapshot'], JSON_THROW_ON_ERROR)),
            'amount_minor' => $row['amount_minor'],
            'currency_code' => $row['currency_code'],
            'is_anonymous' => $row['is_anonymous'],
            'dedication' => $row['dedication'],
            'donor_message' => $row['donor_message'],
            'internal_notes' => $row['internal_notes'],
            'state' => $row['state'],
            'idempotency_key' => $row['idempotency_key'],
            'metadata' => $row['metadata'] === null
                ? null
                : (is_string($row['metadata'])
                    ? $row['metadata']
                    : json_encode($row['metadata'], JSON_THROW_ON_ERROR)),
            'submitted_at' => $row['submitted_at'],
            'payment_initiated_at' => $row['payment_initiated_at'],
            'payment_verified_at' => $row['payment_verified_at'],
            'receipt_generated_at' => $row['receipt_generated_at'],
            'completed_at' => $row['completed_at'],
            'failed_at' => $row['failed_at'],
            'cancelled_at' => $row['cancelled_at'],
            'updated_at' => $row['updated_at'],
            'updated_by' => $row['updated_by'] ?? null,
        ];

        $exec = $this->adapter->execute($sql, $params);
        if ($exec->isFailure()) {
            throw new RuntimeException('DonationRepository::update failed: '.$exec->error());
        }
    }

    public function updateState(EntityId $id, DonationState $newState): Donation
    {
        $updatedAt = (new \DateTimeImmutable())->format(DATE_ATOM);

        $exec = $this->adapter->execute(
            'UPDATE donations SET state = :state, updated_at = :updated_at WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id->value(), 'state' => $newState->value, 'updated_at' => $updatedAt],
        );
        if ($exec->isFailure()) {
            throw new RuntimeException('DonationRepository::updateState failed: '.$exec->error());
        }

        $found = $this->findById($id);
        if ($found === null) {
            throw new RuntimeException("DonationRepository::updateState: donation {$id->value()} not found after update");
        }

        return $found;
    }

    public function existsForIdempotencyKey(string $idempotencyKey): bool
    {
        $result = $this->adapter->query(
            'SELECT 1 FROM donations WHERE idempotency_key = :key AND deleted_at IS NULL LIMIT 1',
            ['key' => $idempotencyKey],
        );

        return ! $result->isFailure() && ! empty($result->value());
    }

    public function countByState(DonationState $state): int
    {
        $result = $this->adapter->query(
            'SELECT COUNT(*) as cnt FROM donations WHERE state = :state AND deleted_at IS NULL',
            ['state' => $state->value],
        );
        if ($result->isFailure()) {
            return 0;
        }

        return (int) ($result->value()[0]['cnt'] ?? 0);
    }

    public function findByGatewayOrderId(string $gatewayOrderId): ?Donation
    {
        // This requires joining through payments; for now do a best-effort
        // by querying only donations that have an associated payment.
        // The actual join will be implemented when PaymentService wires this.
        return null;
    }

    public function lockByIdForUpdate(EntityId $id): ?Donation
    {
        $result = $this->adapter->query(
            'SELECT * FROM donations WHERE id = :id AND deleted_at IS NULL FOR UPDATE LIMIT 1',
            ['id' => $id->value()],
        );
        if ($result->isFailure() || empty($result->value())) {
            return null;
        }

        return Donation::fromRow($result->value()[0]);
    }

    public function lockByIdempotencyKeyForUpdate(string $idempotencyKey): ?Donation
    {
        $result = $this->adapter->query(
            'SELECT * FROM donations WHERE idempotency_key = :key AND deleted_at IS NULL FOR UPDATE LIMIT 1',
            ['key' => $idempotencyKey],
        );
        if ($result->isFailure() || empty($result->value())) {
            return null;
        }

        return Donation::fromRow($result->value()[0]);
    }
}
