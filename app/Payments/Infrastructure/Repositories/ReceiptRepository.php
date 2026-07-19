<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Repositories;

use App\Payments\Domain\Entities\Receipt;
use App\Payments\Domain\Enums\ReceiptDeliveryState;
use App\Payments\Domain\Repositories\ReceiptRepositoryContract;
use App\Payments\Domain\StateMachines\ReceiptStateMachine;
use App\Payments\Domain\StateMachines\StateTransitionEvent;
use App\Persistence\Contracts\PersistenceAdapterContract;
use App\Persistence\ValueObjects\EntityId;
use DateTimeImmutable;
use RuntimeException;

final class ReceiptRepository implements ReceiptRepositoryContract
{
    private static ?ReceiptStateMachine $stateMachine = null;

    public function __construct(
        private readonly PersistenceAdapterContract $adapter,
    ) {}

    public function findById(EntityId $id): ?Receipt
    {
        $result = $this->adapter->query(
            'SELECT * FROM receipts WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id->value()],
        );
        if ($result->isFailure() || empty($result->value())) {
            return null;
        }

        return Receipt::fromRow($result->value()[0]);
    }

    public function findByTransactionId(EntityId $transactionId): ?Receipt
    {
        $result = $this->adapter->query(
            'SELECT * FROM receipts WHERE payment_id = :pid AND deleted_at IS NULL LIMIT 1',
            ['pid' => $transactionId->value()],
        );
        if ($result->isFailure() || empty($result->value())) {
            return null;
        }

        return Receipt::fromRow($result->value()[0]);
    }

    public function findByDonationId(EntityId $donationId): ?Receipt
    {
        $result = $this->adapter->query(
            'SELECT * FROM receipts WHERE donation_id = :did AND deleted_at IS NULL LIMIT 1',
            ['did' => $donationId->value()],
        );
        if ($result->isFailure() || empty($result->value())) {
            return null;
        }

        return Receipt::fromRow($result->value()[0]);
    }

    public function findByReceiptNumber(string $receiptNumber): ?Receipt
    {
        $result = $this->adapter->query(
            'SELECT * FROM receipts WHERE receipt_number = :num AND deleted_at IS NULL LIMIT 1',
            ['num' => $receiptNumber],
        );
        if ($result->isFailure() || empty($result->value())) {
            return null;
        }

        return Receipt::fromRow($result->value()[0]);
    }

    public function save(Receipt $receipt): void
    {
        $row = $receipt->toArray();

        $sql = 'INSERT INTO receipts (
            id, receipt_number, donation_id, payment_id, campaign_id,
            campaign_title_snapshot, donor_name, donor_email, donor_pan,
            donor_address, amount_minor, currency_code, amount_in_words,
            is_tax_deductible, tax_80g_eligible, tax_80g_certificate_number,
            receipt_file_id, certificate_80g_file_id,
            content_hash, state, generated_at,
            delivered_at, delivery_status, delivery_channel,
            delivery_metadata, metadata, created_at, updated_at, deleted_at
        ) VALUES (
            :id, :receipt_number, :donation_id, :payment_id, :campaign_id,
            :campaign_title_snapshot, :donor_name, :donor_email, :donor_pan,
            :donor_address, :amount_minor, :currency_code, :amount_in_words,
            :is_tax_deductible, :tax_80g_eligible, :tax_80g_certificate_number,
            :receipt_file_id, :certificate_80g_file_id,
            :content_hash, :state, :generated_at,
            :delivered_at, :delivery_status, :delivery_channel,
            :delivery_metadata, :metadata, :created_at, :updated_at, :deleted_at
        )';

        $params = [
            'id' => $row['id'],
            'receipt_number' => $row['receipt_number'],
            'donation_id' => $row['donation_id'],
            'payment_id' => $row['payment_id'],
            'campaign_id' => $row['campaign_id'],
            'campaign_title_snapshot' => $row['campaign_title_snapshot'],
            'donor_name' => $row['donor_name'],
            'donor_email' => $row['donor_email'],
            'donor_pan' => $row['donor_pan'],
            'donor_address' => is_string($row['donor_address'])
                ? $row['donor_address']
                : json_encode($row['donor_address'] ?? [], JSON_THROW_ON_ERROR),
            'amount_minor' => $row['amount_minor'],
            'currency_code' => $row['currency_code'],
            'amount_in_words' => $row['amount_in_words'],
            'is_tax_deductible' => $row['is_tax_deductible'],
            'tax_80g_eligible' => $row['tax_80g_eligible'],
            'tax_80g_certificate_number' => $row['tax_80g_certificate_number'],
            'receipt_file_id' => $row['receipt_file_id'],
            'certificate_80g_file_id' => $row['certificate_80g_file_id'],
            'content_hash' => $row['content_hash'],
            'state' => $row['state'],
            'generated_at' => $row['generated_at'],
            'delivered_at' => $row['delivered_at'],
            'delivery_status' => $row['delivery_status'],
            'delivery_channel' => $row['delivery_channel'],
            'delivery_metadata' => is_string($row['delivery_metadata'])
                ? $row['delivery_metadata']
                : json_encode($row['delivery_metadata'] ?? [], JSON_THROW_ON_ERROR),
            'metadata' => is_string($row['metadata'])
                ? $row['metadata']
                : json_encode($row['metadata'] ?? [], JSON_THROW_ON_ERROR),
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'deleted_at' => $row['deleted_at'],
        ];

        $exec = $this->adapter->execute($sql, $params);
        if ($exec->isFailure()) {
            throw new RuntimeException('ReceiptRepository::save failed: '.$exec->error());
        }
    }

    public function update(Receipt $receipt): void
    {
        // Only delivery-tracking fields may be updated.
        // Financial fields are immutable post-issue.
        $row = $receipt->toArray();

        $sql = 'UPDATE receipts SET
            delivered_at = :delivered_at,
            delivery_status = :delivery_status,
            delivery_channel = :delivery_channel,
            delivery_metadata = :delivery_metadata,
            updated_at = :updated_at
        WHERE id = :id';

        $params = [
            'id' => $row['id'],
            'delivered_at' => $row['delivered_at'],
            'delivery_status' => $row['delivery_status'],
            'delivery_channel' => $row['delivery_channel'],
            'delivery_metadata' => is_string($row['delivery_metadata'])
                ? $row['delivery_metadata']
                : json_encode($row['delivery_metadata'] ?? [], JSON_THROW_ON_ERROR),
            'updated_at' => $row['updated_at'],
        ];

        $exec = $this->adapter->execute($sql, $params);
        if ($exec->isFailure()) {
            throw new RuntimeException('ReceiptRepository::update failed: '.$exec->error());
        }
    }

    public function updateDelivery(
        EntityId $id,
        string $deliveryStatus,
        ?string $deliveredAt = null,
    ): Receipt {
        $current = $this->findById($id);
        if ($current === null) {
            throw new RuntimeException("ReceiptRepository::updateDelivery: receipt {$id->value()} not found");
        }

        $machine = $this->getStateMachine();

        // Map deliveryStatus string to StateTransitionEvent
        $event = match ($deliveryStatus) {
            'delivered' => StateTransitionEvent::DELIVERY_DISPATCHED,
            'failed' => StateTransitionEvent::DELIVERY_FAILED,
            'bounced' => StateTransitionEvent::DELIVERY_BOUNCED,
        };

        $updated = $current->transitionDelivery($machine, $event, [
            'delivered_at' => $deliveredAt,
        ]);

        $this->update($updated);

        return $updated;
    }

    public function existsForTransaction(EntityId $transactionId): bool
    {
        $result = $this->adapter->query(
            'SELECT 1 FROM receipts WHERE payment_id = :pid AND deleted_at IS NULL LIMIT 1',
            ['pid' => $transactionId->value()],
        );

        return ! $result->isFailure() && ! empty($result->value());
    }

    public function findMaxReceiptNumberForFY(int $fiscalYear): ?string
    {
        $pattern = sprintf('TR-%d-%%', $fiscalYear);

        $result = $this->adapter->query(
            'SELECT receipt_number FROM receipts WHERE receipt_number LIKE :pattern AND deleted_at IS NULL ORDER BY receipt_number DESC LIMIT 1',
            ['pattern' => $pattern],
        );

        if ($result->isFailure() || empty($result->value())) {
            return null;
        }

        return (string) $result->value()[0]['receipt_number'];
    }

    public function findByDateRange(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $result = $this->adapter->query(
            'SELECT * FROM receipts
             WHERE generated_at >= :from
               AND generated_at <= :to
               AND deleted_at IS NULL
             ORDER BY generated_at ASC',
            [
                'from' => $from->format(DATE_ATOM),
                'to' => $to->format(DATE_ATOM),
            ],
        );

        if ($result->isFailure() || empty($result->value())) {
            return [];
        }

        $receipts = [];
        foreach ($result->value() as $row) {
            $receipts[] = Receipt::fromRow($row);
        }
        return $receipts;
    }

    private function getStateMachine(): ReceiptStateMachine
    {
        return self::$stateMachine ??= new ReceiptStateMachine();
    }
}
