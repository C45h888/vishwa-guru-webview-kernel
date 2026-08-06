<?php

declare(strict_types=1);

namespace Tests\Feature\Payments\Infrastructure;

use App\Payments\Infrastructure\Repositories\AuditEventRepository;
use DateTimeImmutable;
use RuntimeException;

/**
 * @covers AuditEventRepository
 */
final class AuditEventRepositoryTest extends InfrastructureTestCase
{
    private AuditEventRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new AuditEventRepository($this->adapter);
    }

    public function testAppendReturnsIdAndFindByEntityRoundTrip(): void
    {
        $eventId = $this->repo->append(
            eventType: 'donation.initiated',
            entityType: 'donation',
            entityId: 'donation_01ARZ3NDEKTSV4RRFFQ69G5FAV',
            actor: 'user_01ABC',
            correlationId: 'req_abc123',
            previousState: null,
            newState: 'initialized',
            context: ['amount_minor' => 50000],
            occurredAt: null,
        );

        $this->assertNotEmpty($eventId);
        $this->assertStringStartsWith('aud_', $eventId);

        $events = $this->repo->findByEntity('donation', 'donation_01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $this->assertCount(1, $events);

        $event = $events[0];
        $this->assertSame($eventId, $event['id']);
        $this->assertSame('donation.initiated', $event['action']);
        $this->assertSame('donation', $event['entity_type']);
        $this->assertSame('donation_01ARZ3NDEKTSV4RRFFQ69G5FAV', $event['entity_id']);
        $this->assertSame('user_01ABC', $event['actor_type']);
        $this->assertSame('req_abc123', $event['request_id']);
        // metadata should be decoded
        $this->assertIsArray($event['metadata']);
        $this->assertSame('initialized', $event['metadata']['new_state']);
    }

    public function testFindByCorrelationId(): void
    {
        $this->repo->append(
            eventType: 'payment.authorized',
            entityType: 'payment',
            entityId: 'payment_01ARZ3NDEKTSV4RRFFQ69G5FAV',
            correlationId: 'req_pay_001',
        );
        $this->repo->append(
            eventType: 'payment.captured',
            entityType: 'payment',
            entityId: 'payment_01ARZ3NDEKTSV4RRFFQ69G5FAV',
            correlationId: 'req_pay_001',
        );

        $events = $this->repo->findByCorrelationId('req_pay_001');
        $this->assertCount(2, $events);
        // Ordered DESC by occurred_at
        $this->assertSame('payment.captured', $events[0]['action']);
        $this->assertSame('payment.authorized', $events[1]['action']);
    }

    public function testFindByCorrelationIdReturnsEmptyWhenNotFound(): void
    {
        $events = $this->repo->findByCorrelationId('nonexistent_request');
        $this->assertCount(0, $events);
    }

    public function testCountByEventType(): void
    {
        $from = (new DateTimeImmutable())->modify('-1 hour');
        $to = (new DateTimeImmutable())->modify('+1 hour');

        // Create several events
        foreach (['evt_a', 'evt_b', 'evt_a', 'evt_a'] as $i => $eventType) {
            $this->repo->append(
                eventType: $eventType,
                entityType: 'donation',
                entityId: "donation_{$i}",
                occurredAt: new DateTimeImmutable(),
            );
        }

        $countA = $this->repo->countByEventType('evt_a', $from, $to);
        $countB = $this->repo->countByEventType('evt_b', $from, $to);
        $countC = $this->repo->countByEventType('evt_c', $from, $to);

        $this->assertSame(3, $countA);
        $this->assertSame(1, $countB);
        $this->assertSame(0, $countC);
    }

    public function testFindByEntityRespectsLimit(): void
    {
        $entityId = 'donation_limit_test';
        foreach (range(1, 5) as $i) {
            $this->repo->append(
                eventType: "donation.event_{$i}",
                entityType: 'donation',
                entityId: $entityId,
                occurredAt: (new DateTimeImmutable())->modify("+{$i} seconds"),
            );
        }

        $all = $this->repo->findByEntity('donation', $entityId, 100);
        $limited = $this->repo->findByEntity('donation', $entityId, 3);

        $this->assertCount(5, $all);
        $this->assertCount(3, $limited);
    }

    public function testAppendWithNullActorUsesSystemDefault(): void
    {
        $eventId = $this->repo->append(
            eventType: 'payment.failed',
            entityType: 'payment',
            entityId: 'payment_01ARZ3NDEKTSV4RRFFQ69G5FAW',
            actor: null,
        );

        $events = $this->repo->findByEntity('payment', 'payment_01ARZ3NDEKTSV4RRFFQ69G5FAW');
        $this->assertSame('system', $events[0]['actor_type']);
    }
}
