<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Repositories;

use App\Payments\Domain\Enums\PaymentProvider;
use App\Payments\Domain\Repositories\WebhookEventRepositoryContract;
use App\Persistence\Contracts\PersistenceAdapterContract;
use DateTimeImmutable;
use RuntimeException;

final class WebhookEventRepository implements WebhookEventRepositoryContract
{
    public function __construct(
        private readonly PersistenceAdapterContract $adapter,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function findByProviderEventId(PaymentProvider $provider, string $providerEventId): ?array
    {
        $result = $this->adapter->query(
            'SELECT * FROM webhook_events
             WHERE provider_code = :code AND provider_event_id = :eid
             LIMIT 1',
            ['code' => $provider->value, 'eid' => $providerEventId],
        );
        if ($result->isFailure() || empty($result->value())) {
            return null;
        }

        return $this->decodeRow($result->value()[0]);
    }

    public function exists(PaymentProvider $provider, string $providerEventId): bool
    {
        $result = $this->adapter->query(
            'SELECT 1 FROM webhook_events
             WHERE provider_code = :code AND provider_event_id = :eid
             LIMIT 1',
            ['code' => $provider->value, 'eid' => $providerEventId],
        );

        return ! $result->isFailure() && ! empty($result->value());
    }

    public function record(
        PaymentProvider $provider,
        string $providerEventId,
        string $eventType,
        array $payload,
        array $headers,
        bool $signatureVerified,
        string $processingStatus,
        ?string $failureReason = null,
        ?string $relatedTransactionId = null,
    ): string {
        $now = (new DateTimeImmutable())->format(DATE_ATOM);

        $sql = 'INSERT INTO webhook_events (
            id, provider_code, provider_event_id, event_type,
            payload, headers, signature, signature_verified,
            related_payment_id, received_at,
            processed_at, processing_error, retry_count,
            created_at, updated_at
        ) VALUES (
            :id, :provider_code, :provider_event_id, :event_type,
            :payload, :headers, NULL, :signature_verified,
            :related_payment_id, :received_at,
            NULL, :processing_error, 0,
            :created_at, :updated_at
        )';

        $id = 'wev_'.bin2hex(random_bytes(12));

        $params = [
            'id' => $id,
            'provider_code' => $provider->value,
            'provider_event_id' => $providerEventId,
            'event_type' => $eventType,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'headers' => json_encode($headers, JSON_THROW_ON_ERROR),
            'signature_verified' => $signatureVerified ? '1' : '0',
            'related_payment_id' => $relatedTransactionId,
            'received_at' => $now,
            'processing_error' => $failureReason,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $exec = $this->adapter->execute($sql, $params);
        if ($exec->isFailure()) {
            throw new RuntimeException('WebhookEventRepository::record failed: '.$exec->error());
        }

        return $id;
    }

    public function updateProcessingStatus(
        string $eventRowId,
        string $processingStatus,
        ?string $failureReason = null,
    ): void {
        $now = (new DateTimeImmutable())->format(DATE_ATOM);

        $sql = 'UPDATE webhook_events SET
            processing_status = :status,
            processing_error = :error,
            processed_at = :processed_at,
            updated_at = :updated_at
        WHERE id = :id';

        $exec = $this->adapter->execute($sql, [
            'id' => $eventRowId,
            'status' => $processingStatus,
            'error' => $failureReason,
            'processed_at' => $now,
            'updated_at' => $now,
        ]);
        if ($exec->isFailure()) {
            throw new RuntimeException('WebhookEventRepository::updateProcessingStatus failed: '.$exec->error());
        }
    }

    public function countBetween(DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        $result = $this->adapter->query(
            'SELECT COUNT(*) as cnt FROM webhook_events
             WHERE received_at >= :from AND received_at <= :to',
            ['from' => $from->format(DATE_ATOM), 'to' => $to->format(DATE_ATOM)],
        );
        if ($result->isFailure()) {
            return 0;
        }

        return (int) ($result->value()[0]['cnt'] ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeRow(array $row): array
    {
        if (isset($row['payload']) && is_string($row['payload'])) {
            $row['payload'] = json_decode($row['payload'], true) ?? [];
        }
        if (isset($row['headers']) && is_string($row['headers'])) {
            $row['headers'] = json_decode($row['headers'], true) ?? [];
        }

        return $row;
    }
}
