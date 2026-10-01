<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Repositories;

use App\Payments\Domain\Entities\Payment;
use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Enums\PaymentProvider;
use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Domain\Repositories\PaymentRepositoryContract;
use App\Payments\Domain\StateMachines\PaymentStateMachine;
use App\Persistence\Contracts\PersistenceAdapterContract;
use App\Persistence\ValueObjects\EntityId;
use App\Shared\Support\Result;

/**
 * @implements PaymentRepositoryContract
 */
final class PaymentRepository implements PaymentRepositoryContract
{
    public function __construct(
        private readonly PersistenceAdapterContract $adapter,
        private readonly PaymentStateMachine $stateMachine,
    ) {}

    public function findById(EntityId $id): ?Payment
    {
        $result = $this->adapter->query(
            'SELECT * FROM payments WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id->value()],
        );
        if ($result->isFailure()) {
            return null;
        }
        $rows = $result->value();
        if (empty($rows)) {
            return null;
        }

        return Payment::fromRow($rows[0]);
    }

    public function findByGatewayOrderId(string $gatewayOrderId): ?Payment
    {
        $result = $this->adapter->query(
            'SELECT * FROM payments WHERE provider_order_id = :oid AND deleted_at IS NULL LIMIT 1',
            ['oid' => $gatewayOrderId],
        );
        if ($result->isFailure()) {
            return null;
        }
        $rows = $result->value();
        if (empty($rows)) {
            return null;
        }

        return Payment::fromRow($rows[0]);
    }

    public function findByDonationId(EntityId $donationId): ?Payment
    {
        $result = $this->adapter->query(
            'SELECT * FROM payments WHERE donation_id = :did AND deleted_at IS NULL LIMIT 1',
            ['did' => $donationId->value()],
        );
        if ($result->isFailure()) {
            return null;
        }
        $rows = $result->value();
        if (empty($rows)) {
            return null;
        }

        return Payment::fromRow($rows[0]);
    }

    public function findByIdempotencyKey(string $idempotencyKey): ?Payment
    {
        $result = $this->adapter->query(
            'SELECT * FROM payments WHERE idempotency_key = :key AND deleted_at IS NULL LIMIT 1',
            ['key' => $idempotencyKey],
        );
        if ($result->isFailure()) {
            return null;
        }
        $rows = $result->value();
        if (empty($rows)) {
            return null;
        }

        return Payment::fromRow($rows[0]);
    }

    public function save(Payment $payment): void
    {
        $row = $payment->toArray();

        $sql = 'INSERT INTO payments (
            id, donation_id, provider_code, amount_minor, currency_code,
            status, amount_captured_minor, amount_refunded_minor,
            fee_minor, tax_minor, method, method_detail,
            provider_order_id, provider_payment_id, provider_reference_id,
            signature, signature_verified_at, verified_at,
            verification_metadata, initiated_at, authorized_at,
            captured_at, settled_at, failed_at, refunded_at,
            cancelled_at, expired_at, last_failure_code,
            last_failure_reason, idempotency_key,
            raw_provider_response, created_at, updated_at, deleted_at
        ) VALUES (
            :id, :donation_id, :provider_code, :amount_minor, :currency_code,
            :status, :amount_captured_minor, :amount_refunded_minor,
            :fee_minor, :tax_minor, :method, :method_detail,
            :provider_order_id, :provider_payment_id, :provider_reference_id,
            :signature, :signature_verified_at, :verified_at,
            :verification_metadata, :initiated_at, :authorized_at,
            :captured_at, :settled_at, :failed_at, :refunded_at,
            :cancelled_at, :expired_at, :last_failure_code,
            :last_failure_reason, :idempotency_key,
            :raw_provider_response, :created_at, :updated_at, :deleted_at
        )';

        $params = [
            'id' => $row['id'],
            'donation_id' => $row['donation_id'],
            'provider_code' => $row['provider_code'],
            'amount_minor' => $row['amount_minor'],
            'currency_code' => $row['currency_code'],
            'status' => $row['status'],
            'amount_captured_minor' => $row['amount_captured_minor'],
            'amount_refunded_minor' => $row['amount_refunded_minor'] ?? 0,
            'fee_minor' => $row['fee_minor'],
            'tax_minor' => $row['tax_minor'],
            'method' => $row['method'],
            'method_detail' => $row['method_detail'] ?? '{}',
            'provider_order_id' => $row['provider_order_id'],
            'provider_payment_id' => $row['provider_payment_id'],
            'provider_reference_id' => $row['provider_reference_id'],
            'signature' => $row['signature'],
            'signature_verified_at' => $row['signature_verified_at'],
            'verified_at' => $row['verified_at'],
            'verification_metadata' => is_string($row['verification_metadata'])
                ? $row['verification_metadata']
                : json_encode($row['verification_metadata'], JSON_THROW_ON_ERROR),
            'initiated_at' => $row['initiated_at'],
            'authorized_at' => $row['authorized_at'],
            'captured_at' => $row['captured_at'],
            'settled_at' => $row['settled_at'],
            'failed_at' => $row['failed_at'],
            'refunded_at' => $row['refunded_at'],
            'cancelled_at' => $row['cancelled_at'],
            'expired_at' => $row['expired_at'],
            'last_failure_code' => $row['last_failure_code'],
            'last_failure_reason' => $row['last_failure_reason'],
            'idempotency_key' => $row['idempotency_key'],
            'raw_provider_response' => is_string($row['raw_provider_response'])
                ? $row['raw_provider_response']
                : json_encode($row['raw_provider_response'], JSON_THROW_ON_ERROR),
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'deleted_at' => $row['deleted_at'],
        ];

        $exec = $this->adapter->execute($sql, $params);
        if ($exec->isFailure()) {
            throw new \RuntimeException('PaymentRepository::save failed: '.$exec->error());
        }
    }

    public function update(Payment $payment): void
    {
        $row = $payment->toArray();

        $sql = 'UPDATE payments SET
            donation_id = :donation_id,
            provider_code = :provider_code,
            amount_minor = :amount_minor,
            currency_code = :currency_code,
            status = :status,
            amount_captured_minor = :amount_captured_minor,
            amount_refunded_minor = :amount_refunded_minor,
            fee_minor = :fee_minor,
            tax_minor = :tax_minor,
            method = :method,
            method_detail = :method_detail,
            provider_order_id = :provider_order_id,
            provider_payment_id = :provider_payment_id,
            provider_reference_id = :provider_reference_id,
            signature = :signature,
            signature_verified_at = :signature_verified_at,
            verified_at = :verified_at,
            verification_metadata = :verification_metadata,
            initiated_at = :initiated_at,
            authorized_at = :authorized_at,
            captured_at = :captured_at,
            settled_at = :settled_at,
            failed_at = :failed_at,
            refunded_at = :refunded_at,
            cancelled_at = :cancelled_at,
            expired_at = :expired_at,
            last_failure_code = :last_failure_code,
            last_failure_reason = :last_failure_reason,
            idempotency_key = :idempotency_key,
            raw_provider_response = :raw_provider_response,
            updated_at = :updated_at,
            deleted_at = :deleted_at
        WHERE id = :id';

        $params = [
            'id' => $row['id'],
            'donation_id' => $row['donation_id'],
            'provider_code' => $row['provider_code'],
            'amount_minor' => $row['amount_minor'],
            'currency_code' => $row['currency_code'],
            'status' => $row['status'],
            'amount_captured_minor' => $row['amount_captured_minor'],
            'amount_refunded_minor' => $row['amount_refunded_minor'] ?? 0,
            'fee_minor' => $row['fee_minor'],
            'tax_minor' => $row['tax_minor'],
            'method' => $row['method'],
            'method_detail' => is_string($row['method_detail'])
                ? $row['method_detail']
                : json_encode($row['method_detail'], JSON_THROW_ON_ERROR),
            'provider_order_id' => $row['provider_order_id'],
            'provider_payment_id' => $row['provider_payment_id'],
            'provider_reference_id' => $row['provider_reference_id'],
            'signature' => $row['signature'],
            'signature_verified_at' => $row['signature_verified_at'],
            'verified_at' => $row['verified_at'],
            'verification_metadata' => is_string($row['verification_metadata'])
                ? $row['verification_metadata']
                : json_encode($row['verification_metadata'], JSON_THROW_ON_ERROR),
            'initiated_at' => $row['initiated_at'],
            'authorized_at' => $row['authorized_at'],
            'captured_at' => $row['captured_at'],
            'settled_at' => $row['settled_at'],
            'failed_at' => $row['failed_at'],
            'refunded_at' => $row['refunded_at'],
            'cancelled_at' => $row['cancelled_at'],
            'expired_at' => $row['expired_at'],
            'last_failure_code' => $row['last_failure_code'],
            'last_failure_reason' => $row['last_failure_reason'],
            'idempotency_key' => $row['idempotency_key'],
            'raw_provider_response' => is_string($row['raw_provider_response'])
                ? $row['raw_provider_response']
                : json_encode($row['raw_provider_response'], JSON_THROW_ON_ERROR),
            'updated_at' => $row['updated_at'],
            'deleted_at' => $row['deleted_at'],
        ];

        $exec = $this->adapter->execute($sql, $params);
        if ($exec->isFailure()) {
            throw new \RuntimeException('PaymentRepository::update failed: '.$exec->error());
        }
    }

    public function updateStatus(EntityId $id, TransactionStatus $newStatus): Payment
    {
        $payment = $this->findById($id);
        if ($payment === null) {
            throw new \RuntimeException("PaymentRepository::updateStatus: payment {$id->value()} not found");
        }

        // Enforce SM as sole authority — validate the transition before persisting.
        $transitioned = $payment->transitionTo($this->stateMachine, $newStatus);

        $this->update($transitioned);

        return $transitioned;
    }

    public function existsForGatewayOrder(string $gatewayOrderId): bool
    {
        $result = $this->adapter->query(
            'SELECT 1 FROM payments WHERE provider_order_id = :oid AND deleted_at IS NULL LIMIT 1',
            ['oid' => $gatewayOrderId],
        );

        return ! $result->isFailure() && ! empty($result->value());
    }

    public function countByStatus(TransactionStatus $status): int
    {
        $result = $this->adapter->query(
            'SELECT COUNT(*) as cnt FROM payments WHERE status = :status AND deleted_at IS NULL',
            ['status' => $status->value],
        );
        if ($result->isFailure()) {
            return 0;
        }
        $rows = $result->value();

        return (int) ($rows[0]['cnt'] ?? 0);
    }

    /**
     * @param  array<int, string>  $gatewayOrderIds
     * @return array<string, Payment>
     */
    public function findManyByGatewayOrderIds(array $gatewayOrderIds): array
    {
        if ($gatewayOrderIds === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($gatewayOrderIds as $i => $oid) {
            $placeholders[] = ":oid{$i}";
            $params["oid{$i}"] = $oid;
        }

        $sql = sprintf(
            'SELECT * FROM payments WHERE provider_order_id IN (%s) AND deleted_at IS NULL',
            implode(', ', $placeholders),
        );

        $result = $this->adapter->query($sql, $params);
        if ($result->isFailure()) {
            return [];
        }

        $payments = [];
        foreach ($result->value() as $row) {
            $p = Payment::fromRow($row);
            $payments[$p->providerOrderId() ?? ''] = $p;
        }

        return $payments;
    }

    public function lockByIdForUpdate(EntityId $id): ?Payment
    {
        $result = $this->adapter->query(
            'SELECT * FROM payments WHERE id = :id AND deleted_at IS NULL FOR UPDATE LIMIT 1',
            ['id' => $id->value()],
        );
        if ($result->isFailure() || empty($result->value())) {
            return null;
        }

        return Payment::fromRow($result->value()[0]);
    }

    public function lockByGatewayOrderIdForUpdate(string $gatewayOrderId): ?Payment
    {
        $result = $this->adapter->query(
            'SELECT * FROM payments WHERE provider_order_id = :oid AND deleted_at IS NULL FOR UPDATE LIMIT 1',
            ['oid' => $gatewayOrderId],
        );
        if ($result->isFailure() || empty($result->value())) {
            return null;
        }

        return Payment::fromRow($result->value()[0]);
    }

    /**
     * @return array<int, Payment>
     */
    public function findSuccessfulWithoutReceipt(int $limit = 200): array
    {
        // Successful statuses mirror TransactionStatus::isSuccessful().
        // LIMIT is inlined (sanitized int) because Postgres treats a bound
        // LIMIT parameter as text and rejects it.
        $safeLimit = max(1, $limit);

        $result = $this->adapter->query(
            "SELECT p.* FROM payments p
             LEFT JOIN receipts r ON r.payment_id = p.id
             WHERE p.status IN ('captured', 'settling', 'settled')
               AND p.deleted_at IS NULL
               AND r.id IS NULL
             ORDER BY p.created_at ASC
             LIMIT {$safeLimit}",
        );

        if ($result->isFailure()) {
            return [];
        }

        return array_map(
            static fn (array $row): Payment => Payment::fromRow($row),
            $result->value(),
        );
    }
}
