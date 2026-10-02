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
            metadata, terms_version, terms_accepted_at,
            privacy_notice_version, privacy_notice_acknowledged_at,
            marketing_email_consent_version, marketing_email_consented_at,
            submitted_at, payment_initiated_at, payment_verified_at,
            receipt_generated_at, completed_at, failed_at, cancelled_at,
            created_at, updated_at, deleted_at
        ) VALUES (
            :id, :campaign_id, :donor_id, :donor_name_snapshot, :donor_email_snapshot,
            :donor_phone_snapshot, :donor_pan_snapshot, :donor_address_snapshot,
            :amount_minor, :currency_code, :is_anonymous, :dedication,
            :donor_message, :internal_notes, :state, :idempotency_key,
            :metadata, :terms_version, :terms_accepted_at,
            :privacy_notice_version, :privacy_notice_acknowledged_at,
            :marketing_email_consent_version, :marketing_email_consented_at,
            :submitted_at, :payment_initiated_at, :payment_verified_at,
            :receipt_generated_at, :completed_at, :failed_at, :cancelled_at,
            :created_at, :updated_at, :deleted_at
        )';

        $params = [
            'id' => $row['id'],
            'campaign_id' => $row['campaign_id'],
            'donor_id' => $row['donor_id'],
            'donor_name_snapshot' => $row['donor_name_snapshot'],
            'donor_email_snapshot' => $row['donor_email_snapshot'],
            'donor_phone_snapshot' => $row['donor_phone_snapshot'],
            'donor_pan_snapshot' => $row['donor_pan_snapshot'],
            // Anonymous donations must persist NULL to satisfy the
            // donations_anonymous_no_pii constraint. Identified donations
            // without an address use an empty JSON object because the SQLite
            // mirror keeps this snapshot column NOT NULL. A real address is
            // JSON-encoded so PDO sends a valid JSON literal to JSONB.
            'donor_address_snapshot' => $row['donor_address_snapshot'] === null
                ? ((bool) $row['is_anonymous'] ? null : '{}')
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
            'terms_version' => $row['terms_version'],
            'terms_accepted_at' => $row['terms_accepted_at'],
            'privacy_notice_version' => $row['privacy_notice_version'],
            'privacy_notice_acknowledged_at' => $row['privacy_notice_acknowledged_at'],
            'marketing_email_consent_version' => $row['marketing_email_consent_version'],
            'marketing_email_consented_at' => $row['marketing_email_consented_at'],
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
            terms_version = :terms_version,
            terms_accepted_at = :terms_accepted_at,
            privacy_notice_version = :privacy_notice_version,
            privacy_notice_acknowledged_at = :privacy_notice_acknowledged_at,
            marketing_email_consent_version = :marketing_email_consent_version,
            marketing_email_consented_at = :marketing_email_consented_at,
            submitted_at = :submitted_at,
            payment_initiated_at = :payment_initiated_at,
            payment_verified_at = :payment_verified_at,
            receipt_generated_at = :receipt_generated_at,
            completed_at = :completed_at,
            failed_at = :failed_at,
            cancelled_at = :cancelled_at,
            updated_at = :updated_at
        WHERE id = :id';

        $params = [
            'id' => $row['id'],
            'campaign_id' => $row['campaign_id'],
            'donor_id' => $row['donor_id'],
            'donor_name_snapshot' => $row['donor_name_snapshot'],
            'donor_email_snapshot' => $row['donor_email_snapshot'],
            'donor_phone_snapshot' => $row['donor_phone_snapshot'],
            'donor_pan_snapshot' => $row['donor_pan_snapshot'],
            // Anonymous donations must persist NULL to satisfy the
            // donations_anonymous_no_pii constraint. Identified donations
            // without an address use an empty JSON object because the SQLite
            // mirror keeps this snapshot column NOT NULL. A real address is
            // JSON-encoded so PDO sends a valid JSON literal to JSONB.
            'donor_address_snapshot' => $row['donor_address_snapshot'] === null
                ? ((bool) $row['is_anonymous'] ? null : '{}')
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
            'terms_version' => $row['terms_version'],
            'terms_accepted_at' => $row['terms_accepted_at'],
            'privacy_notice_version' => $row['privacy_notice_version'],
            'privacy_notice_acknowledged_at' => $row['privacy_notice_acknowledged_at'],
            'marketing_email_consent_version' => $row['marketing_email_consent_version'],
            'marketing_email_consented_at' => $row['marketing_email_consented_at'],
            'submitted_at' => $row['submitted_at'],
            'payment_initiated_at' => $row['payment_initiated_at'],
            'payment_verified_at' => $row['payment_verified_at'],
            'receipt_generated_at' => $row['receipt_generated_at'],
            'completed_at' => $row['completed_at'],
            'failed_at' => $row['failed_at'],
            'cancelled_at' => $row['cancelled_at'],
            'updated_at' => $row['updated_at'],
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
        // Wave 1 donation-state-drift fix: the previous stub returned null
        // unconditionally, masking any actual lookup failure and breaking
        // cross-kernel joins when callers queried Donation by gateway order id.
        // Implements the join through `payments` correctly so the
        // DonationRepository now behaves like the contract advertises.
        $result = $this->adapter->query(
            'SELECT d.* FROM donations d
             INNER JOIN payments p ON p.donation_id = d.id
             WHERE p.provider_order_id = :oid
               AND d.deleted_at IS NULL
               AND p.deleted_at IS NULL
             LIMIT 1',
            ['oid' => $gatewayOrderId],
        );
        if ($result->isFailure() || empty($result->value())) {
            return null;
        }

        return Donation::fromRow($result->value()[0]);
    }

    public function lockByIdForUpdate(EntityId $id): ?Donation
    {
        $lockClause = $this->adapter->driver() === 'pgsql' ? ' FOR UPDATE' : '';
        $result = $this->adapter->query(
            'SELECT * FROM donations WHERE id = :id AND deleted_at IS NULL LIMIT 1'.$lockClause,
            ['id' => $id->value()],
        );
        if ($result->isFailure() || empty($result->value())) {
            return null;
        }

        return Donation::fromRow($result->value()[0]);
    }

    public function lockByIdempotencyKeyForUpdate(string $idempotencyKey): ?Donation
    {
        $lockClause = $this->adapter->driver() === 'pgsql' ? ' FOR UPDATE' : '';
        $result = $this->adapter->query(
            'SELECT * FROM donations WHERE idempotency_key = :key AND deleted_at IS NULL LIMIT 1'.$lockClause,
            ['key' => $idempotencyKey],
        );
        if ($result->isFailure() || empty($result->value())) {
            return null;
        }

        return Donation::fromRow($result->value()[0]);
    }
}
