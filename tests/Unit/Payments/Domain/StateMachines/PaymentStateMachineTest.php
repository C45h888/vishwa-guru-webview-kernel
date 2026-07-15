<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Domain\StateMachines;

use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Domain\Exceptions\PaymentStateTransitionException;
use App\Payments\Domain\StateMachines\PaymentStateMachine;
use App\Payments\Domain\StateMachines\StateTransitionEvent;
use PHPUnit\Framework\TestCase;

class PaymentStateMachineTest extends TestCase
{
    private PaymentStateMachine $machine;

    protected function setUp(): void
    {
        $this->machine = new PaymentStateMachine();
    }

    public function testHappyPath(): void
    {
        $result = $this->machine->transition(
            TransactionStatus::INITIALIZED,
            StateTransitionEvent::AUTH_OK,
        );
        $this->assertSame(TransactionStatus::PENDING, $result->toState());
        $this->assertSame(['status' => 'pending'], $result->entityChanges());
        $this->assertArrayNotHasKey('authorized_at', $result->timestampChanges());

        $result = $this->machine->transition(
            TransactionStatus::PENDING,
            StateTransitionEvent::GATEWAY_CONFIRMED,
        );
        $this->assertSame(TransactionStatus::AUTHORIZED, $result->toState());
        $this->assertArrayHasKey('authorized_at', $result->timestampChanges());

        $result = $this->machine->transition(
            TransactionStatus::AUTHORIZED,
            StateTransitionEvent::CAPTURE_RECEIVED,
            ['amount_minor' => 10000],
        );
        $this->assertSame(TransactionStatus::CAPTURED, $result->toState());
        $this->assertSame(10000, $result->entityChanges()['amount_captured_minor']);
        $this->assertArrayHasKey('captured_at', $result->timestampChanges());
    }

    public function testInitializedToFailedOnGatewayFailed(): void
    {
        $result = $this->machine->transition(
            TransactionStatus::INITIALIZED,
            StateTransitionEvent::GATEWAY_FAILED,
        );
        $this->assertSame(TransactionStatus::FAILED, $result->toState());
        $this->assertArrayHasKey('failed_at', $result->timestampChanges());
    }

    public function testCustomerCancelOnAuthorizedIsRejected(): void
    {
        $this->expectException(PaymentStateTransitionException::class);
        $this->machine->transition(
            TransactionStatus::AUTHORIZED,
            StateTransitionEvent::CUSTOMER_CANCELLED,
        );
    }

    public function testSettledCannotAcceptCapture(): void
    {
        $this->expectException(PaymentStateTransitionException::class);
        $this->machine->transition(
            TransactionStatus::SETTLED,
            StateTransitionEvent::CAPTURE_RECEIVED,
        );
    }

    public function testPartialRefundRequiresDifferentEventThanFullRefund(): void
    {
        $partial = $this->machine->transition(
            TransactionStatus::CAPTURED,
            StateTransitionEvent::PARTIAL_REFUND_INITIATED,
            ['amount_minor' => 5000],
        );
        $this->assertSame(TransactionStatus::PARTIALLY_REFUNDED, $partial->toState());
        $this->assertSame(5000, $partial->entityChanges()['amount_captured_minor']);
        $this->assertArrayHasKey('refunded_at', $partial->timestampChanges());

        $this->expectException(PaymentStateTransitionException::class);
        $this->machine->transition(
            TransactionStatus::CAPTURED,
            StateTransitionEvent::REFUND_COMPLETED,
        );
    }

    public function testCanTransition(): void
    {
        $this->assertTrue($this->machine->canTransition(
            TransactionStatus::INITIALIZED,
            StateTransitionEvent::AUTH_OK,
        ));
        $this->assertFalse($this->machine->canTransition(
            TransactionStatus::SETTLED,
            StateTransitionEvent::CAPTURE_RECEIVED,
        ));
    }

    public function testAllowedNext(): void
    {
        $next = $this->machine->allowedNext(TransactionStatus::INITIALIZED);
        $this->assertContains(TransactionStatus::PENDING, $next);
        $this->assertContains(TransactionStatus::FAILED, $next);
        $this->assertContains(TransactionStatus::CANCELLED, $next);
        $this->assertContains(TransactionStatus::EXPIRED, $next);
    }

    public function testTerminalStatusesRefuseAllEvents(): void
    {
        foreach ([
            TransactionStatus::SETTLED,
            TransactionStatus::FAILED,
            TransactionStatus::REFUNDED,
            TransactionStatus::CANCELLED,
            TransactionStatus::EXPIRED,
        ] as $terminal) {
            foreach (StateTransitionEvent::cases() as $event) {
                $this->assertFalse(
                    $this->machine->canTransition($terminal, $event),
                    sprintf('Terminal [%s] should refuse event [%s]', $terminal->value, $event->value),
                );
            }
        }
    }

    public function testAmountCapturedMinorAutoFilled(): void
    {
        $result = $this->machine->transition(
            TransactionStatus::CAPTURED,
            StateTransitionEvent::SETTLEMENT_CONFIRMED,
            ['amount_minor' => 12345],
        );
        $this->assertSame(12345, $result->entityChanges()['amount_captured_minor']);
        $this->assertSame(
            true,
            $result->metadata()['amount_captured_minor_auto_filled'],
        );
    }

    public function testDisputeResolutionLost(): void
    {
        $result = $this->machine->transition(
            TransactionStatus::DISPUTED,
            StateTransitionEvent::DISPUTE_RESOLVED_LOST,
        );
        $this->assertSame(TransactionStatus::FAILED, $result->toState());
        $this->assertArrayHasKey('failed_at', $result->timestampChanges());
    }

    public function testDeterministic(): void
    {
        $r1 = $this->machine->transition(
            TransactionStatus::INITIALIZED,
            StateTransitionEvent::AUTH_OK,
        );
        $r2 = $this->machine->transition(
            TransactionStatus::INITIALIZED,
            StateTransitionEvent::AUTH_OK,
        );
        $this->assertSame($r1->toState(), $r2->toState());
        $this->assertSame($r1->entityChanges(), $r2->entityChanges());
    }

    public function testFeeAndTaxFromContext(): void
    {
        $result = $this->machine->transition(
            TransactionStatus::CAPTURED,
            StateTransitionEvent::SETTLEMENT_CONFIRMED,
            [
                'amount_minor' => 10000,
                'fee_minor' => 200,
                'tax_minor' => 360,
                'method' => 'card',
                'gateway_payment_id' => 'pay_ABC',
            ],
        );
        $this->assertSame(200, $result->entityChanges()['fee_minor']);
        $this->assertSame(360, $result->entityChanges()['tax_minor']);
        $this->assertSame('card', $result->entityChanges()['method']);
        $this->assertSame('pay_ABC', $result->entityChanges()['provider_payment_id']);
    }

    // Auto-added for full table coverage (iter 10)

    public function testTransition_AUTHORIZED_CAPTURE_RECEIVED(): void
    {
        $this->assertTrue($this->machine->canTransition(
            TransactionStatus::AUTHORIZED,
            StateTransitionEvent::CAPTURE_RECEIVED,
        ));
    }

    public function testTransition_AUTHORIZED_CUSTOMER_CANCELLED(): void
    {
        $this->assertTrue($this->machine->canTransition(
            TransactionStatus::AUTHORIZED,
            StateTransitionEvent::CUSTOMER_CANCELLED,
        ));
    }

    public function testTransition_AUTHORIZED_GATEWAY_FAILED(): void
    {
        $this->assertTrue($this->machine->canTransition(
            TransactionStatus::AUTHORIZED,
            StateTransitionEvent::GATEWAY_FAILED,
        ));
    }

    public function testTransition_CAPTURED_DISPUTE_OPENED(): void
    {
        $this->assertTrue($this->machine->canTransition(
            TransactionStatus::CAPTURED,
            StateTransitionEvent::DISPUTE_OPENED,
        ));
    }

    public function testTransition_CAPTURED_PARTIAL_REFUND_INITIATED(): void
    {
        $this->assertTrue($this->machine->canTransition(
            TransactionStatus::CAPTURED,
            StateTransitionEvent::PARTIAL_REFUND_INITIATED,
        ));
    }

    public function testTransition_CAPTURED_REFUND_INITIATED(): void
    {
        $this->assertTrue($this->machine->canTransition(
            TransactionStatus::CAPTURED,
            StateTransitionEvent::REFUND_INITIATED,
        ));
    }

    public function testTransition_CAPTURED_SETTLEMENT_NOTICE(): void
    {
        $this->assertTrue($this->machine->canTransition(
            TransactionStatus::CAPTURED,
            StateTransitionEvent::SETTLEMENT_NOTICE,
        ));
    }

    public function testTransition_DISPUTED_DISPUTE_RESOLVED_LOST(): void
    {
        $this->assertTrue($this->machine->canTransition(
            TransactionStatus::DISPUTED,
            StateTransitionEvent::DISPUTE_RESOLVED_LOST,
        ));
    }

    public function testTransition_INITIALIZED_CUSTOMER_CANCELLED(): void
    {
        $this->assertTrue($this->machine->canTransition(
            TransactionStatus::INITIALIZED,
            StateTransitionEvent::CUSTOMER_CANCELLED,
        ));
    }

    public function testTransition_INITIALIZED_GATEWAY_CONFIRMED(): void
    {
        $this->assertTrue($this->machine->canTransition(
            TransactionStatus::INITIALIZED,
            StateTransitionEvent::GATEWAY_CONFIRMED,
        ));
    }

    public function testTransition_INITIALIZED_GATEWAY_FAILED(): void
    {
        $this->assertTrue($this->machine->canTransition(
            TransactionStatus::INITIALIZED,
            StateTransitionEvent::GATEWAY_FAILED,
        ));
    }

    public function testTransition_INITIALIZED_GATEWAY_TIMEOUT(): void
    {
        $this->assertTrue($this->machine->canTransition(
            TransactionStatus::INITIALIZED,
            StateTransitionEvent::GATEWAY_TIMEOUT,
        ));
    }

    public function testTransition_PARTIALLY_REFUNDED_DISPUTE_OPENED(): void
    {
        $this->assertTrue($this->machine->canTransition(
            TransactionStatus::PARTIALLY_REFUNDED,
            StateTransitionEvent::DISPUTE_OPENED,
        ));
    }

    public function testTransition_PARTIALLY_REFUNDED_REFUND_COMPLETED(): void
    {
        $this->assertTrue($this->machine->canTransition(
            TransactionStatus::PARTIALLY_REFUNDED,
            StateTransitionEvent::REFUND_COMPLETED,
        ));
    }

    public function testTransition_PENDING_CUSTOMER_CANCELLED(): void
    {
        $this->assertTrue($this->machine->canTransition(
            TransactionStatus::PENDING,
            StateTransitionEvent::CUSTOMER_CANCELLED,
        ));
    }

    public function testTransition_PENDING_GATEWAY_CONFIRMED(): void
    {
        $this->assertTrue($this->machine->canTransition(
            TransactionStatus::PENDING,
            StateTransitionEvent::GATEWAY_CONFIRMED,
        ));
    }

    public function testTransition_PENDING_GATEWAY_FAILED(): void
    {
        $this->assertTrue($this->machine->canTransition(
            TransactionStatus::PENDING,
            StateTransitionEvent::GATEWAY_FAILED,
        ));
    }

    public function testTransition_PENDING_GATEWAY_TIMEOUT(): void
    {
        $this->assertTrue($this->machine->canTransition(
            TransactionStatus::PENDING,
            StateTransitionEvent::GATEWAY_TIMEOUT,
        ));
    }

    public function testTransition_SETTLED_DISPUTE_OPENED(): void
    {
        $this->assertTrue($this->machine->canTransition(
            TransactionStatus::SETTLED,
            StateTransitionEvent::DISPUTE_OPENED,
        ));
    }

    public function testTransition_SETTLED_PARTIAL_REFUND_INITIATED(): void
    {
        $this->assertTrue($this->machine->canTransition(
            TransactionStatus::SETTLED,
            StateTransitionEvent::PARTIAL_REFUND_INITIATED,
        ));
    }

    public function testTransition_SETTLED_REFUND_INITIATED(): void
    {
        $this->assertTrue($this->machine->canTransition(
            TransactionStatus::SETTLED,
            StateTransitionEvent::REFUND_INITIATED,
        ));
    }

    public function testTransition_SETTLING_DISPUTE_OPENED(): void
    {
        $this->assertTrue($this->machine->canTransition(
            TransactionStatus::SETTLING,
            StateTransitionEvent::DISPUTE_OPENED,
        ));
    }

    public function testTransition_SETTLING_SETTLEMENT_CONFIRMED(): void
    {
        $this->assertTrue($this->machine->canTransition(
            TransactionStatus::SETTLING,
            StateTransitionEvent::SETTLEMENT_CONFIRMED,
        ));
    }

    public function testTransition_SETTLING_SETTLEMENT_FAILED(): void
    {
        $this->assertTrue($this->machine->canTransition(
            TransactionStatus::SETTLING,
            StateTransitionEvent::SETTLEMENT_FAILED,
        ));
    }

}
