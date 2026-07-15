<?php

declare(strict_types=1);

namespace App\Payments\Domain\Repositories;

use App\Payments\Domain\Enums\PaymentProvider;
use DateTimeImmutable;

/**
 * Persistence boundary for webhook event audit records.
 *
 * Webhook events live in the `webhook_events` table which has a UNIQUE
 * (provider_code, provider_event_id) constraint — duplicate callbacks
 * MUST be detected here, before any processing.
 *
 * The repository is the ONLY path to insert webhook events; the
 * PaymentVerificationPipeline calls record() to log every incoming
 * callback regardless of whether it was accepted or rejected.
 */
interface WebhookEventRepositoryContract
{
    /**
     * Find a webhook event by its provider-side identifier.
     *
     * @return array<string, mixed>|null
     */
    public function findByProviderEventId(PaymentProvider $provider, string $providerEventId): ?array;

    /**
     * Whether an event with the same provider_event_id has already been
     * recorded for this provider. Used for fast duplicate detection.
     */
    public function exists(PaymentProvider $provider, string $providerEventId): bool;

    /**
     * Record a webhook event with its processing outcome.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
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
    ): string;

    /**
     * Update only the processing_status + failure_reason columns after
     * a webhook event has been further processed.
     */
    public function updateProcessingStatus(
        string $eventRowId,
        string $processingStatus,
        ?string $failureReason = null,
    ): void;

    /**
     * Count webhook events received in the given window. Used by the
     * observability dashboard.
     */
    public function countBetween(DateTimeImmutable $from, DateTimeImmutable $to): int;
}