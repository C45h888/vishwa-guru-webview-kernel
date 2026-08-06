<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Domain\Entities;

use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Enums\PaymentProvider;
use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Domain\Exceptions\PaymentStateTransitionException;
use App\Payments\Domain\StateMachines\PaymentStateMachine;
use App\Payments\Domain\StateMachines\StateTransitionEvent;
use App\Payments\Domain\Entities\Payment;
use App\Persistence\ValueObjects\EntityId;
use PHPUnit\Framework\TestCase;

class PaymentTest extends TestCase
{
    private function make(): Payment
    {
        return Payment::initialize(
            donationId: EntityId::generate('donation'),
            providerCode: PaymentProvider::RAZORPAY,
            amountMinor: 10000,
            currency: Currency::INR,
            idempotencyKey: 'idem_test_1',
        );
    }

    public function testInitialize(): void
    {
        $p = $this->make();
        $this->assertSame(TransactionStatus::INITIALIZED, $p->status());
        $this->assertSame('idem_test_1', $p->idempotencyKey());
        $this->assertSame(10000, $p->amountMinor());
        $this->assertSame(PaymentProvider::RAZORPAY, $p->providerCode());
        $this->assertNotNull($p->initiatedAt());
        $this->assertNull($p->providerOrderId());
        $this->assertFalse($p->isDeleted());
    }

    public function testEntityTypeAndIdentifier(): void
    {
        $p = $this->make();
        $this->assertSame('payment', $p->entityType());
        $this->assertInstanceOf(EntityId::class, $p->id());
        $this->assertSame('payment_'.$p->id()->ulid(), $p->id()->value());
    }

    public function testTransitionToSucceeds(): void
    {
        $machine = new PaymentStateMachine();
        $p = $this->make();
        $advanced = $p->transitionTo($machine, TransactionStatus::PENDING);

        $this->assertNotSame($p, $advanced);
        $this->assertSame(TransactionStatus::PENDING, $advanced->status());
    }

    public function testTransitionToRejectedRaises(): void
    {
        $machine = new PaymentStateMachine();
        $p = $this->make()->transitionTo($machine, TransactionStatus::PENDING);

        $this->expectException(PaymentStateTransitionException::class);
        $p->transitionTo($machine, TransactionStatus::REFUNDED);
    }

    public function testWithChangesRejectsStatusDirectly(): void
    {
        $p = $this->make();
        $this->expectException(\LogicException::class);
        $p->withChanges(['status' => TransactionStatus::CAPTURED->value]);
    }

    public function testWithChangesAllowsNonStatusUpdates(): void
    {
        $p = $this->make();
        $next = $p->withChanges(['method' => 'card', 'fee_minor' => 200]);
        $this->assertSame('card', $next->method());
        $this->assertSame(200, $next->feeMinor());
    }

    public function testFromRowReconstructsState(): void
    {
        $p1 = $this->make();
        $row = $p1->toArray();
        $row['status'] = TransactionStatus::CAPTURED->value;
        $row['amount_captured_minor'] = 10000;
        $row['authorized_at'] = '2026-01-01T00:00:00+00:00';
        $row['captured_at'] = '2026-01-01T00:01:00+00:00';

        $p2 = Payment::fromRow($row);
        $this->assertSame(TransactionStatus::CAPTURED, $p2->status());
        $this->assertSame(10000, $p2->amountCapturedMinor());
    }

    public function testFromRowRequiresStatusKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Payment::fromRow(['id' => 'payment_01ARZ3NDEKTSV4RRFFQ69G5FAV']);
    }

    public function testTerminalStatusCannotBeChanged(): void
    {
        $machine = new PaymentStateMachine();
        $p = $this->make();
        $failed = $p->transitionTo($machine, TransactionStatus::FAILED);

        $this->assertTrue($failed->status()->isTerminal());
        $this->expectException(PaymentStateTransitionException::class);
        $failed->transitionTo($machine, TransactionStatus::SETTLED);
    }

    public function testToArrayHasAllSchemaColumns(): void
    {
        $p = $this->make();
        $row = $p->toArray();
        $this->assertArrayHasKey('id', $row);
        $this->assertArrayHasKey('provider_code', $row);
        $this->assertArrayHasKey('verification_metadata', $row);
        $this->assertArrayHasKey('raw_provider_response', $row);
    }

    public function testProviderOrderIdUpdates(): void
    {
        $p = $this->make();
        $next = $p->withChanges(['provider_order_id' => 'order_ABC']);
        $this->assertSame('order_ABC', $next->providerOrderId());
    }

    public function testTransitionToDisputed(): void
    {
        $machine = new PaymentStateMachine();
        $p = $this->make()
            ->transitionTo($machine, TransactionStatus::PENDING)
            ->transitionTo($machine, TransactionStatus::AUTHORIZED)
            ->transitionTo($machine, TransactionStatus::CAPTURED);

        $disputed = $p->transitionTo($machine, TransactionStatus::DISPUTED);
        $this->assertSame(TransactionStatus::DISPUTED, $disputed->status());
    }

    public function testTransitionDisputedToFailed(): void
    {
        $machine = new PaymentStateMachine();
        $p = $this->make()
            ->transitionTo($machine, TransactionStatus::PENDING)
            ->transitionTo($machine, TransactionStatus::AUTHORIZED)
            ->transitionTo($machine, TransactionStatus::CAPTURED)
            ->transitionTo($machine, TransactionStatus::DISPUTED);

        $failed = $p->transitionTo($machine, TransactionStatus::FAILED);
        $this->assertSame(TransactionStatus::FAILED, $failed->status());
    }

    public function testTransitionToExpired(): void
    {
        $machine = new PaymentStateMachine();
        $p = $this->make();

        $expired = $p->transitionTo($machine, TransactionStatus::EXPIRED);
        $this->assertSame(TransactionStatus::EXPIRED, $expired->status());
    }

    public function testTransitionPendingToExpired(): void
    {
        $machine = new PaymentStateMachine();
        $p = $this->make()->transitionTo($machine, TransactionStatus::PENDING);

        $expired = $p->transitionTo($machine, TransactionStatus::EXPIRED);
        $this->assertSame(TransactionStatus::EXPIRED, $expired->status());
    }

}
