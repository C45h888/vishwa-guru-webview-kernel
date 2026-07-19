<?php

declare(strict_types=1);

namespace Tests\Feature\Payments\Infrastructure;

use App\Payments\Domain\Enums\PaymentProvider;
use App\Payments\Infrastructure\Repositories\WebhookEventRepository;
use RuntimeException;

/**
 * @covers WebhookEventRepository
 */
final class WebhookEventRepositoryTest extends InfrastructureTestCase
{
    private WebhookEventRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new WebhookEventRepository($this->adapter);
    }

    public function testRecordFindByProviderEventIdRoundTrip(): void
    {
        $id = $this->repo->record(
            provider: PaymentProvider::RAZORPAY,
            providerEventId: 'evt_razorpay_001',
            eventType: 'payment.captured',
            payload: ['order_id' => 'order_abc', 'amount' => 50000],
            headers: ['X-Razorpay-Signature' => 'sig123'],
            signatureVerified: true,
            processingStatus: 'processed',
            failureReason: null,
            relatedTransactionId: null,
        );

        $this->assertNotEmpty($id);

        $found = $this->repo->findByProviderEventId(PaymentProvider::RAZORPAY, 'evt_razorpay_001');
        $this->assertNotNull($found);
        $this->assertSame('evt_razorpay_001', $found['provider_event_id']);
        $this->assertSame('razorpay', $found['provider_code']);
        $this->assertSame('payment.captured', $found['event_type']);
        $this->assertSame('processed', $found['processing_status']);
        // Payload should be decoded
        $this->assertIsArray($found['payload']);
        $this->assertSame(50000, $found['payload']['amount'] ?? $found['payload'][1] ?? null);
    }

    public function testExistsTrueForRecordedEvent(): void
    {
        $this->repo->record(
            provider: PaymentProvider::RAZORPAY,
            providerEventId: 'evt_exists_001',
            eventType: 'payment.authorized',
            payload: ['order_id' => 'order_xyz'],
            headers: [],
            signatureVerified: true,
            processingStatus: 'processed',
            failureReason: null,
            relatedTransactionId: null,
        );

        $this->assertTrue(
            $this->repo->exists(PaymentProvider::RAZORPAY, 'evt_exists_001'),
        );
    }

    public function testExistsFalseForUnrecordedEvent(): void
    {
        $this->assertFalse(
            $this->repo->exists(PaymentProvider::RAZORPAY, 'evt_nonexistent'),
        );
    }

    public function testExistsWithDifferentProviderIsDistinct(): void
    {
        $this->repo->record(
            provider: PaymentProvider::PAYPAL,
            providerEventId: 'evt_paypal_same_id',
            eventType: 'payment.completed',
            payload: [],
            headers: [],
            signatureVerified: true,
            processingStatus: 'processed',
            failureReason: null,
            relatedTransactionId: null,
        );

        // Razorpay should not find the PayPal event
        $this->assertFalse(
            $this->repo->exists(PaymentProvider::RAZORPAY, 'evt_paypal_same_id'),
        );
        // PayPal should find it
        $this->assertTrue(
            $this->repo->exists(PaymentProvider::PAYPAL, 'evt_paypal_same_id'),
        );
    }

    public function testUpdateProcessingStatus(): void
    {
        $id = $this->repo->record(
            provider: PaymentProvider::RAZORPAY,
            providerEventId: 'evt_update_001',
            eventType: 'payment.captured',
            payload: ['order_id' => 'order_update'],
            headers: [],
            signatureVerified: true,
            processingStatus: 'pending',
            failureReason: null,
            relatedTransactionId: null,
        );

        $this->repo->updateProcessingStatus($id, 'processed', null);

        $found = $this->repo->findByProviderEventId(PaymentProvider::RAZORPAY, 'evt_update_001');
        $this->assertNotNull($found);
        $this->assertSame('processed', $found['processing_status']);
    }

    public function testUpdateProcessingStatusWithFailureReason(): void
    {
        $id = $this->repo->record(
            provider: PaymentProvider::PAYPAL,
            providerEventId: 'evt_fail_001',
            eventType: 'payment.failed',
            payload: [],
            headers: [],
            signatureVerified: false,
            processingStatus: 'pending',
            failureReason: null,
            relatedTransactionId: null,
        );

        $this->repo->updateProcessingStatus($id, 'failed', 'Signature mismatch');

        $found = $this->repo->findByProviderEventId(PaymentProvider::PAYPAL, 'evt_fail_001');
        $this->assertNotNull($found);
        $this->assertSame('failed', $found['processing_status']);
        $this->assertSame('Signature mismatch', $found['processing_error']);
    }

    public function testReserveReturnsTrueForNewEventId(): void
    {
        // Atomic reserve — first call on a new (provider, provider_event_id)
        // returns true. Inserts a minimal placeholder row.
        $reserved = $this->repo->reserve(
            provider: PaymentProvider::Razorpay,
            providerEventId: 'evt_reserve_001',
            ttlSeconds: 604800, // 7d, matches IDEMPOTENCY_TTL_WEBHOOK
        );

        $this->assertTrue(
            $reserved,
            'reserve() must return true on a fresh (provider, provider_event_id)',
        );
    }

    public function testReserveReturnsFalseForDuplicateEventId(): void
    {
        // First reserve succeeds.
        $first = $this->repo->reserve(
            provider: PaymentProvider::Razorpay,
            providerEventId: 'evt_reserve_002',
            ttlSeconds: 604800,
        );
        $this->assertTrue($first);

        // Second reserve of the SAME (provider, provider_event_id) returns false.
        // Doctrine: ON CONFLICT DO NOTHING — atomic, no race window.
        $second = $this->repo->reserve(
            provider: PaymentProvider::Razorpay,
            providerEventId: 'evt_reserve_002',
            ttlSeconds: 604800,
        );

        $this->assertFalse(
            $second,
            'reserve() must return false on duplicate (provider, provider_event_id)',
        );
    }

    public function testReserveIsScopedByProviderSoRazorpayAndPaypalStayDistinct(): void
    {
        // Doctrine: same provider_event_id under different providers
        // is NOT a duplicate. (provider_code, provider_event_id) is
        // the UNIQUE target on webhook_events.
        $razorpay = $this->repo->reserve(
            provider: PaymentProvider::Razorpay,
            providerEventId: 'evt_shared',
            ttlSeconds: 604800,
        );
        $paypal = $this->repo->reserve(
            provider: PaymentProvider::PayPal,
            providerEventId: 'evt_shared',
            ttlSeconds: 604800,
        );

        $this->assertTrue($razorpay, 'Razorpay reserve must succeed');
        $this->assertTrue($paypal, 'PayPal reserve with same event_id must succeed — scoped by provider');
    }
}
