<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Domain\StateMachines;

use App\Payments\Domain\Enums\ReceiptDeliveryState;
use App\Payments\Domain\StateMachines\ReceiptStateMachine;
use App\Payments\Domain\StateMachines\StateTransitionEvent;
use PHPUnit\Framework\TestCase;

class ReceiptStateMachineTest extends TestCase
{
    private ReceiptStateMachine $machine;

    protected function setUp(): void
    {
        $this->machine = new ReceiptStateMachine();
    }

    public function testPendingToDelivered(): void
    {
        $result = $this->machine->transition(
            ReceiptDeliveryState::PENDING,
            StateTransitionEvent::DELIVERY_DISPATCHED,
        );
        $this->assertSame(ReceiptDeliveryState::DELIVERED, $result->toState());
        $this->assertSame(['delivery_status' => 'delivered'], $result->entityChanges());
        $this->assertArrayHasKey('delivered_at', $result->timestampChanges());
    }

    public function testPendingToBounced(): void
    {
        $result = $this->machine->transition(
            ReceiptDeliveryState::PENDING,
            StateTransitionEvent::DELIVERY_BOUNCED,
        );
        $this->assertSame(ReceiptDeliveryState::BOUNCED, $result->toState());
        $this->assertSame(['delivery_status' => 'bounced'], $result->entityChanges());
    }

    public function testPendingToFailed(): void
    {
        $result = $this->machine->transition(
            ReceiptDeliveryState::PENDING,
            StateTransitionEvent::DELIVERY_FAILED,
        );
        $this->assertSame(ReceiptDeliveryState::FAILED, $result->toState());
    }

    public function testBouncedCanRedispatch(): void
    {
        $result = $this->machine->transition(
            ReceiptDeliveryState::BOUNCED,
            StateTransitionEvent::DELIVERY_REDISPATCHED,
        );
        $this->assertSame(ReceiptDeliveryState::PENDING, $result->toState());
    }

    public function testFailedCanRedispatch(): void
    {
        $result = $this->machine->transition(
            ReceiptDeliveryState::FAILED,
            StateTransitionEvent::DELIVERY_REDISPATCHED,
        );
        $this->assertSame(ReceiptDeliveryState::PENDING, $result->toState());
    }

    public function testDeliveredIsTerminal(): void
    {
        $this->expectException(\LogicException::class);
        $this->machine->transition(
            ReceiptDeliveryState::DELIVERED,
            StateTransitionEvent::DELIVERY_REDISPATCHED,
        );
    }

    public function testCanTransition(): void
    {
        $this->assertTrue($this->machine->canTransition(
            ReceiptDeliveryState::PENDING,
            StateTransitionEvent::DELIVERY_DISPATCHED,
        ));
        $this->assertFalse($this->machine->canTransition(
            ReceiptDeliveryState::DELIVERED,
            StateTransitionEvent::DELIVERY_DISPATCHED,
        ));
    }

    public function testAllowedNext(): void
    {
        $next = $this->machine->allowedNext(ReceiptDeliveryState::PENDING);
        $this->assertContains(ReceiptDeliveryState::DELIVERED, $next);
        $this->assertContains(ReceiptDeliveryState::BOUNCED, $next);
        $this->assertContains(ReceiptDeliveryState::FAILED, $next);
    }

    public function testInvalidEventIsRejected(): void
    {
        $this->expectException(\LogicException::class);
        $this->machine->transition(
            ReceiptDeliveryState::PENDING,
            StateTransitionEvent::GATEWAY_CONFIRMED,
        );
    }

    // Auto-added for full table coverage (iter 10)

    public function testTransition_BOUNCED_DELIVERY_REDISPATCHED(): void
    {
        $this->assertTrue($this->machine->canTransition(
            ReceiptDeliveryState::BOUNCED,
            StateTransitionEvent::DELIVERY_REDISPATCHED,
        ));
    }

    public function testTransition_FAILED_DELIVERY_REDISPATCHED(): void
    {
        $this->assertTrue($this->machine->canTransition(
            ReceiptDeliveryState::FAILED,
            StateTransitionEvent::DELIVERY_REDISPATCHED,
        ));
    }

    public function testTransition_PENDING_DELIVERY_BOUNCED(): void
    {
        $this->assertTrue($this->machine->canTransition(
            ReceiptDeliveryState::PENDING,
            StateTransitionEvent::DELIVERY_BOUNCED,
        ));
    }

    public function testTransition_PENDING_DELIVERY_FAILED(): void
    {
        $this->assertTrue($this->machine->canTransition(
            ReceiptDeliveryState::PENDING,
            StateTransitionEvent::DELIVERY_FAILED,
        ));
    }

}
