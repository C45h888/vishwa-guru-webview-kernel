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
     * Atomically reserve a (provider, provider_event_id) slot.
     *
     * Returns true if we own this slot (caller proceeds with the webhook
     * controller); false if duplicate or backend unreachable. Used by the
     * inbound HTTP webhook dedupe middleware (Layer 3 of 3-layer dedupe)
     * as the DB fallback when Redis SETEX is unavailable.
     *
     * Doctrine:
     *   - MUST NOT throw. Failure is reported via false; the contract
     *     layer catches Throwable internally.
     *   - Atomic at the DB layer (INSERT ... ON CONFLICT DO NOTHING).
     *   - The `webhook_events` table has `UNIQUE (provider_code, provider_event_id)`
     *     per the V1 schema; the ON CONFLICT clause uses those columns.
     *   - Reserves a MINIMAL placeholder row (event_type='pending', payload='{}').
     *     The full webhook controller will call `record()` AFTER signature
     *     verification to populate the row with real data. The reserve-then-record
     *     pattern means record() always succeeds (the UNIQUE was already
     *     checked at reserve time).
     */
    public function reserve(
        PaymentProvider $provider,
        string $providerEventId,
        int $ttlSeconds,
    ): bool;

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
     * Doctrine: idempotent upsert. If a row with the same
     * (provider_code, provider_event_id) already exists (e.g. from a
     * prior `reserve()` call from the webhook dedupe middleware, or a
     * prior `record()` call), this method UPDATES the existing row
     * rather than failing on UNIQUE violation. This is atomic at the
     * DB layer (`INSERT ... ON CONFLICT DO UPDATE`).
     *
     * The reserve-then-record pattern means:
     *   - The middleware's reserve() establishes "we own this slot"
     *     atomically (Layer 3 dedupe gate).
     *   - The service's record() then upserts the full payload into
     *     that same row (placeholder → full data).
     *   - The race window between the two is closed: the UNIQUE
     *     constraint ensures only one row per (provider, event_id).
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