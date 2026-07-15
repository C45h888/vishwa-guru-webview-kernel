<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Domain\Entities;

use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Enums\DonationState;
use App\Payments\Domain\Exceptions\PaymentStateTransitionException;
use App\Payments\Domain\StateMachines\DonationStateMachine;
use App\Payments\Domain\StateMachines\StateTransitionEvent;
use App\Payments\Domain\Entities\Donation;
use App\Payments\Domain\ValueObjects\DonorIdentity;
use App\Persistence\ValueObjects\EntityId;
use PHPUnit\Framework\TestCase;

class DonationTest extends TestCase
{
    private function makeIdentified(): Donation
    {
        return Donation::draft(
            campaignId: EntityId::generate('campaign'),
            donor: DonorIdentity::identified('Jane Doe', 'jane@example.com', '+91-9876543210'),
            amountMinor: 5000,
            currency: Currency::INR,
            dedication: 'For prosperity',
            donorMessage: 'With love',
            idempotencyKey: 'idem_d1',
        );
    }

    private function makeAnonymous(): Donation
    {
        return Donation::draft(
            campaignId: EntityId::generate('campaign'),
            donor: DonorIdentity::anonymous(),
            amountMinor: 1000,
            currency: Currency::INR,
            idempotencyKey: 'idem_d2',
        );
    }

    public function testIdentifiedDraft(): void
    {
        $d = $this->makeIdentified();
        $this->assertSame(DonationState::DRAFT, $d->state());
        $this->assertSame(5000, $d->amountMinor());
        $this->assertSame('Jane Doe', $d->donorNameSnapshot());
        $this->assertSame('jane@example.com', $d->donorEmailSnapshot());
        $this->assertFalse($d->isAnonymous());
    }

    public function testAnonymousDraft(): void
    {
        $d = $this->makeAnonymous();
        $this->assertTrue($d->isAnonymous());
        $this->assertNull($d->donorNameSnapshot());
        $this->assertNull($d->donorEmailSnapshot());
    }

    public function testAnonymousRejectsDedication(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Donation::draft(
            campaignId: EntityId::generate('campaign'),
            donor: DonorIdentity::anonymous(),
            amountMinor: 1000,
            currency: Currency::INR,
            dedication: 'Anonymous tribute',
        );
    }

    public function testIdentifiedRequiresContactChannel(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        DonorIdentity::identified('Jane Doe');
    }

    public function testTransitionToPendingPayment(): void
    {
        $machine = new DonationStateMachine();
        $d = $this->makeIdentified();
        $next = $d->transitionTo($machine, DonationState::PENDING_PAYMENT);
        $this->assertSame(DonationState::PENDING_PAYMENT, $next->state());
        $this->assertNotNull($next->paymentInitiatedAt());
    }

    public function testTerminalCannotTransition(): void
    {
        $machine = new DonationStateMachine();
        $d = $this->makeIdentified()
            ->transitionTo($machine, DonationState::PENDING_PAYMENT)
            ->transitionTo($machine, DonationState::PAYMENT_VERIFIED)
            ->transitionTo($machine, DonationState::RECEIPT_GENERATED)
            ->transitionTo($machine, DonationState::COMPLETED);

        $this->assertTrue($d->state()->isTerminal());
        $this->expectException(PaymentStateTransitionException::class);
        $d->transitionTo($machine, DonationState::FAILED);
    }

    public function testWithChangesRejectsStateDirectly(): void
    {
        $d = $this->makeIdentified();
        $this->expectException(\LogicException::class);
        $d->withChanges(['state' => DonationState::COMPLETED->value]);
    }

    public function testWithChangesAllowsNonStateUpdates(): void
    {
        $d = $this->makeIdentified();
        $next = $d->withChanges(['internal_notes' => 'Reviewed by admin']);
        $this->assertSame('Reviewed by admin', $next->internalNotes());
    }

    public function testFromRowReconstructs(): void
    {
        $d1 = $this->makeIdentified();
        $row = $d1->toArray();
        $row['state'] = DonationState::PENDING_PAYMENT->value;
        $row['payment_initiated_at'] = '2026-01-01T00:00:00+00:00';

        $d2 = Donation::fromRow($row);
        $this->assertSame(DonationState::PENDING_PAYMENT, $d2->state());
        $this->assertNotNull($d2->paymentInitiatedAt());
    }

    public function testEntityType(): void
    {
        $d = $this->makeIdentified();
        $this->assertSame('donation', $d->entityType());
    }

    public function testIsAnonymousFlag(): void
    {
        $this->assertFalse($this->makeIdentified()->isAnonymousFlag());
        $this->assertTrue($this->makeAnonymous()->isAnonymousFlag());
    }
}
