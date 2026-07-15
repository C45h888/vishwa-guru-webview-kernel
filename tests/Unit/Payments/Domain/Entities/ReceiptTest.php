<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Domain\Entities;

use App\Payments\Domain\Enums\ReceiptDeliveryState;
use App\Payments\Domain\Exceptions\PaymentStateTransitionException;
use App\Payments\Domain\StateMachines\ReceiptStateMachine;
use App\Payments\Domain\StateMachines\StateTransitionEvent;
use App\Payments\Domain\Entities\Receipt;
use App\Persistence\ValueObjects\EntityId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ReceiptTest extends TestCase
{
    private function make(): Receipt
    {
        return Receipt::issue(
            donationId: EntityId::generate('donation'),
            transactionId: EntityId::generate('payment'),
            fileAssetId: EntityId::generate('file_asset'),
            receiptNumber: 'TR-2026-ABCD1234',
            contentHash: str_repeat('a', 64),
            issuedAt: new DateTimeImmutable(),
            deliveryChannel: 'email',
            deliveryAddress: 'donor@example.com',
        );
    }

    public function testIssue(): void
    {
        $r = $this->make();
        $this->assertSame(ReceiptDeliveryState::PENDING->value, $r->deliveryStatus());
        $this->assertSame('TR-2026-ABCD1234', $r->receiptNumber());
        $this->assertSame('email', $r->deliveryChannel());
        $this->assertSame('donor@example.com', $r->deliveryAddress());
        $this->assertTrue($r->isPending());
        $this->assertFalse($r->isDelivered());
    }

    public function testReceiptNumberValidation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Receipt::issue(
            donationId: EntityId::generate('donation'),
            transactionId: EntityId::generate('payment'),
            fileAssetId: EntityId::generate('file_asset'),
            receiptNumber: 'INVALID-FORMAT',
            contentHash: str_repeat('a', 64),
            issuedAt: new DateTimeImmutable(),
        );
    }

    public function testContentHashValidation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Receipt::issue(
            donationId: EntityId::generate('donation'),
            transactionId: EntityId::generate('payment'),
            fileAssetId: EntityId::generate('file_asset'),
            receiptNumber: 'TR-2026-ABCD1234',
            contentHash: '',
            issuedAt: new DateTimeImmutable(),
        );
    }

    public function testTransitionDeliverySucceeds(): void
    {
        $machine = new ReceiptStateMachine();
        $r = $this->make();
        $next = $r->transitionDelivery($machine, StateTransitionEvent::DELIVERY_DISPATCHED);
        $this->assertSame(ReceiptDeliveryState::DELIVERED->value, $next->deliveryStatus());
        $this->assertNotNull($next->deliveredAt());
    }

    public function testWithChangesRejectsContentFields(): void
    {
        $r = $this->make();
        $this->expectException(PaymentStateTransitionException::class);
        $r->withChanges(['receipt_number' => 'TR-2026-OTHER']);
    }

    public function testWithChangesRejectsDeliveryStatus(): void
    {
        $r = $this->make();
        $this->expectException(\LogicException::class);
        $r->withChanges(['delivery_status' => 'delivered']);
    }

    public function testWithChangesAllowsDeliveryMetadata(): void
    {
        $r = $this->make();
        $next = $r->withChanges(['metadata' => ['render_engine' => 'pdf_v1']]);
        $this->assertSame(['render_engine' => 'pdf_v1'], $next->metadata());
    }

    public function testFromRowReconstructs(): void
    {
        $r1 = $this->make();
        $row = $r1->toArray();
        $row['delivery_status'] = 'delivered';
        $row['delivered_at'] = '2026-01-01T00:00:00+00:00';

        $r2 = Receipt::fromRow($row);
        $this->assertTrue($r2->isDelivered());
    }

    public function testEntityType(): void
    {
        $this->assertSame('receipt', $this->make()->entityType());
    }

    public function testHasFailedDelivery(): void
    {
        $machine = new ReceiptStateMachine();
        $r = $this->make()->transitionDelivery($machine, StateTransitionEvent::DELIVERY_FAILED);
        $this->assertTrue($r->hasFailedDelivery());
    }
}
