<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Domain\StateMachines;

use App\Payments\Domain\Enums\DonationState;
use App\Payments\Domain\Exceptions\PaymentStateTransitionException;
use App\Payments\Domain\StateMachines\DonationStateMachine;
use App\Payments\Domain\StateMachines\StateTransitionEvent;
use PHPUnit\Framework\TestCase;

class DonationStateMachineTest extends TestCase
{
    private DonationStateMachine $machine;

    protected function setUp(): void
    {
        $this->machine = new DonationStateMachine();
    }

    public function testDraftToPendingPayment(): void
    {
        $result = $this->machine->transition(
            DonationState::DRAFT,
            StateTransitionEvent::SUBMITTED,
        );
        $this->assertSame(DonationState::PENDING_PAYMENT, $result->toState());
        $this->assertSame(['state' => 'pending_payment'], $result->entityChanges());
        $this->assertArrayHasKey('payment_initiated_at', $result->timestampChanges());
    }

    public function testPendingPaymentToVerified(): void
    {
        $result = $this->machine->transition(
            DonationState::PENDING_PAYMENT,
            StateTransitionEvent::GATEWAY_CONFIRMED,
        );
        $this->assertSame(DonationState::PAYMENT_VERIFIED, $result->toState());
        $this->assertArrayHasKey('payment_verified_at', $result->timestampChanges());
    }

    public function testWebhookTimeoutCancelsPendingPayment(): void
    {
        $result = $this->machine->transition(
            DonationState::PENDING_PAYMENT,
            StateTransitionEvent::WEBHOOK_TIMEOUT,
        );
        $this->assertSame(DonationState::CANCELLED, $result->toState());
        $this->assertArrayHasKey('cancelled_at', $result->timestampChanges());
    }

    public function testReceiptFailedTransitionsToFailed(): void
    {
        $result = $this->machine->transition(
            DonationState::PAYMENT_VERIFIED,
            StateTransitionEvent::RECEIPT_FAILED,
        );
        $this->assertSame(DonationState::FAILED, $result->toState());
        $this->assertArrayHasKey('failed_at', $result->timestampChanges());
    }

    public function testCompletedOnlyFromReceiptGenerated(): void
    {
        $result = $this->machine->transition(
            DonationState::RECEIPT_GENERATED,
            StateTransitionEvent::COMPLETED,
        );
        $this->assertSame(DonationState::COMPLETED, $result->toState());
        $this->assertArrayHasKey('completed_at', $result->timestampChanges());

        $this->expectException(PaymentStateTransitionException::class);
        $this->machine->transition(
            DonationState::PAYMENT_VERIFIED,
            StateTransitionEvent::COMPLETED,
        );
    }

    public function testTerminalStatesRefuseAllEvents(): void
    {
        foreach ([
            DonationState::COMPLETED,
            DonationState::FAILED,
            DonationState::CANCELLED,
        ] as $terminal) {
            foreach (StateTransitionEvent::cases() as $event) {
                $this->assertFalse(
                    $this->machine->canTransition($terminal, $event),
                    sprintf('Terminal [%s] should refuse event [%s]', $terminal->value, $event->value),
                );
            }
        }
    }

    public function testCanTransition(): void
    {
        $this->assertTrue($this->machine->canTransition(
            DonationState::DRAFT,
            StateTransitionEvent::SUBMITTED,
        ));
        $this->assertFalse($this->machine->canTransition(
            DonationState::COMPLETED,
            StateTransitionEvent::SUBMITTED,
        ));
    }

    public function testAllowedNext(): void
    {
        $next = $this->machine->allowedNext(DonationState::PENDING_PAYMENT);
        $this->assertContains(DonationState::PAYMENT_VERIFIED, $next);
        $this->assertContains(DonationState::FAILED, $next);
        $this->assertContains(DonationState::CANCELLED, $next);
    }

    public function testReceiptIdFromContext(): void
    {
        $result = $this->machine->transition(
            DonationState::PAYMENT_VERIFIED,
            StateTransitionEvent::RECEIPT_ISSUED,
            ['receipt_id' => 'rcpt_01ABC'],
        );
        $this->assertSame(DonationState::RECEIPT_GENERATED, $result->toState());
        $this->assertSame('rcpt_01ABC', $result->entityChanges()['receipt_id']);
        $this->assertArrayHasKey('receipt_generated_at', $result->timestampChanges());
    }

    public function testMapToTransactionStatus(): void
    {
        $this->assertSame(
            \App\Payments\Domain\Enums\TransactionStatus::INITIALIZED,
            DonationStateMachine::mapToTransactionStatus(DonationState::DRAFT),
        );
        $this->assertSame(
            \App\Payments\Domain\Enums\TransactionStatus::SETTLED,
            DonationStateMachine::mapToTransactionStatus(DonationState::COMPLETED),
        );
    }

    // Auto-added for full table coverage (iter 10)

    public function testTransition_DRAFT_CUSTOMER_CANCELLED(): void
    {
        $this->assertTrue($this->machine->canTransition(
            DonationState::DRAFT,
            StateTransitionEvent::CUSTOMER_CANCELLED,
        ));
    }

    public function testTransition_PAYMENT_VERIFIED_CUSTOMER_CANCELLED(): void
    {
        $this->assertTrue($this->machine->canTransition(
            DonationState::PAYMENT_VERIFIED,
            StateTransitionEvent::CUSTOMER_CANCELLED,
        ));
    }

    public function testTransition_PAYMENT_VERIFIED_RECEIPT_FAILED(): void
    {
        $this->assertTrue($this->machine->canTransition(
            DonationState::PAYMENT_VERIFIED,
            StateTransitionEvent::RECEIPT_FAILED,
        ));
    }

    public function testTransition_PAYMENT_VERIFIED_RECEIPT_ISSUED(): void
    {
        $this->assertTrue($this->machine->canTransition(
            DonationState::PAYMENT_VERIFIED,
            StateTransitionEvent::RECEIPT_ISSUED,
        ));
    }

    public function testTransition_PENDING_PAYMENT_CUSTOMER_CANCELLED(): void
    {
        $this->assertTrue($this->machine->canTransition(
            DonationState::PENDING_PAYMENT,
            StateTransitionEvent::CUSTOMER_CANCELLED,
        ));
    }

    public function testTransition_PENDING_PAYMENT_GATEWAY_CONFIRMED(): void
    {
        $this->assertTrue($this->machine->canTransition(
            DonationState::PENDING_PAYMENT,
            StateTransitionEvent::GATEWAY_CONFIRMED,
        ));
    }

    public function testTransition_PENDING_PAYMENT_GATEWAY_FAILED(): void
    {
        $this->assertTrue($this->machine->canTransition(
            DonationState::PENDING_PAYMENT,
            StateTransitionEvent::GATEWAY_FAILED,
        ));
    }

    public function testTransition_PENDING_PAYMENT_WEBHOOK_TIMEOUT(): void
    {
        $this->assertTrue($this->machine->canTransition(
            DonationState::PENDING_PAYMENT,
            StateTransitionEvent::WEBHOOK_TIMEOUT,
        ));
    }

    public function testTransition_RECEIPT_GENERATED_COMPLETED(): void
    {
        $this->assertTrue($this->machine->canTransition(
            DonationState::RECEIPT_GENERATED,
            StateTransitionEvent::COMPLETED,
        ));
    }

    public function testTransition_RECEIPT_GENERATED_POST_COMMIT_FAIL(): void
    {
        $this->assertTrue($this->machine->canTransition(
            DonationState::RECEIPT_GENERATED,
            StateTransitionEvent::POST_COMMIT_FAIL,
        ));
    }

}
