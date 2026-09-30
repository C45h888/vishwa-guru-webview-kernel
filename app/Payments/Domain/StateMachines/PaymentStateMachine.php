<?php

declare(strict_types=1);

namespace App\Payments\Domain\StateMachines;

use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Domain\Exceptions\PaymentStateTransitionException;
use DateTimeImmutable;

/**
 * Payment lifecycle state machine.
 *
 * Single source of truth for valid Payment transitions. Deterministic
 * pure function: same input → same result. No I/O. No side effects.
 *
 * The machine does NOT call repositories, gateways, or any service —
 * all it does is compute (toState, entityChanges, timestampChanges)
 * given (fromState, event, context). The caller (a service in
 * Pass 1.3) is responsible for applying the changes via
 * Payment::transitionTo().
 *
 * Transition table: see SECTION 4.1 of the Pass 1.1+1.2 spec.
 */
final class PaymentStateMachine
{
    /**
     * Compute the next state for a payment given a current state and an event.
     *
     * @param  array<string, mixed>  $context  Side-channel: amount, idempotencyKey, fee, tax, etc.
     * @throws PaymentStateTransitionException
     */
    public function transition(
        TransactionStatus $from,
        StateTransitionEvent $event,
        array $context = [],
    ): StateTransitionResult {
        // Terminal statuses refuse ALL events.
        if ($this->isTerminal($from)) {
            throw PaymentStateTransitionException::terminalCannotTransition($from);
        }

        $allowed = $this->allowedEvents($from);
        if (! in_array($event, $allowed, true)) {
            throw PaymentStateTransitionException::invalidTransition($from, $this->targetFor($from, $event), $event->value);
        }

        $now = new DateTimeImmutable();
        $to = $this->targetFor($from, $event);
        $entityChanges = ['status' => $to->value];
        $timestampChanges = [];
        $metadata = [];

        // Status<->timestamp correlation rules
        $timestampMap = [
            TransactionStatus::AUTHORIZED->value => 'authorized_at',
            TransactionStatus::CAPTURED->value => 'captured_at',
            TransactionStatus::SETTLED->value => 'settled_at',
            TransactionStatus::FAILED->value => 'failed_at',
            TransactionStatus::REFUNDED->value => 'refunded_at',
            TransactionStatus::PARTIALLY_REFUNDED->value => 'refunded_at',
            TransactionStatus::CANCELLED->value => 'cancelled_at',
            TransactionStatus::EXPIRED->value => 'expired_at',
        ];
        if (isset($timestampMap[$to->value])) {
            $timestampChanges[$timestampMap[$to->value]] = $now;
        }

        // Schema CHECK: if entering CAPTURED/SETTLED/REFUNDED/PARTIALLY_REFUNDED
        // without a captured amount, default to the payment's amount_minor.
        $capturedStatuses = [
            TransactionStatus::CAPTURED,
            TransactionStatus::SETTLED,
            TransactionStatus::REFUNDED,
            TransactionStatus::PARTIALLY_REFUNDED,
        ];
        if (in_array($to, $capturedStatuses, true)) {
            $existingCaptured = (int) ($context['amount_captured_minor'] ?? 0);
            if ($existingCaptured <= 0) {
                $entityChanges['amount_captured_minor'] = (int) ($context['amount_minor'] ?? 0);
                $metadata['amount_captured_minor_auto_filled'] = true;
            } else {
                $entityChanges['amount_captured_minor'] = $existingCaptured;
            }
        }

        // Capture fee and tax from context if present
        if (isset($context['fee_minor'])) {
            $entityChanges['fee_minor'] = (int) $context['fee_minor'];
        }
        if (isset($context['tax_minor'])) {
            $entityChanges['tax_minor'] = (int) $context['tax_minor'];
        }
        if (isset($context['method'])) {
            $entityChanges['method'] = (string) $context['method'];
        }
        if (isset($context['gateway_payment_id'])) {
            $entityChanges['provider_payment_id'] = (string) $context['gateway_payment_id'];
        }
        if (isset($context['verified_at'])) {
            $entityChanges['verified_at'] = (string) $context['verified_at'];
        }
        if (isset($context['amount_refunded_minor'])) {
            $entityChanges['amount_refunded_minor'] = (int) $context['amount_refunded_minor'];
        }

        return new StateTransitionResult(
            toState: $to,
            entityChanges: $entityChanges,
            timestampChanges: $timestampChanges,
            metadata: $metadata,
        );
    }

    public function canTransition(
        TransactionStatus $from,
        StateTransitionEvent $event,
    ): bool {
        if ($this->isTerminal($from)) {
            return false;
        }

        return in_array($event, $this->allowedEvents($from), true);
    }

    /**
     * @return array<int, TransactionStatus>
     */
    public function allowedNext(TransactionStatus $from): array
    {
        $events = $this->allowedEvents($from);
        $targets = [];
        foreach ($events as $event) {
            $targets[] = $this->targetFor($from, $event);
        }

        return array_values(array_unique($targets, SORT_REGULAR));
    }

    /**
     * @return array<int, StateTransitionEvent>
     */
    private function allowedEvents(TransactionStatus $from): array
    {
        return match ($from) {
            TransactionStatus::INITIALIZED => [
                StateTransitionEvent::AUTH_OK,
                StateTransitionEvent::GATEWAY_CONFIRMED,
                // A provider can report a captured payment before a
                // separate authorization notification reaches us.
                StateTransitionEvent::CAPTURE_RECEIVED,
                StateTransitionEvent::GATEWAY_FAILED,
                StateTransitionEvent::GATEWAY_TIMEOUT,
                StateTransitionEvent::CUSTOMER_CANCELLED,
            ],
            TransactionStatus::PENDING => [
                StateTransitionEvent::GATEWAY_CONFIRMED,
                StateTransitionEvent::CAPTURE_RECEIVED,
                StateTransitionEvent::GATEWAY_FAILED,
                StateTransitionEvent::GATEWAY_TIMEOUT,
                StateTransitionEvent::CUSTOMER_CANCELLED,
            ],
            TransactionStatus::AUTHORIZED => [
                StateTransitionEvent::CAPTURE_RECEIVED,
                StateTransitionEvent::GATEWAY_FAILED,
                StateTransitionEvent::CUSTOMER_CANCELLED,
            ],
            TransactionStatus::CAPTURED => [
                StateTransitionEvent::SETTLEMENT_NOTICE,
                StateTransitionEvent::SETTLEMENT_CONFIRMED,
                StateTransitionEvent::REFUND_INITIATED,
                StateTransitionEvent::PARTIAL_REFUND_INITIATED,
                StateTransitionEvent::DISPUTE_OPENED,
            ],
            TransactionStatus::SETTLING => [
                StateTransitionEvent::SETTLEMENT_CONFIRMED,
                StateTransitionEvent::SETTLEMENT_FAILED,
                StateTransitionEvent::DISPUTE_OPENED,
            ],
            TransactionStatus::SETTLED => [
                StateTransitionEvent::REFUND_INITIATED,
                StateTransitionEvent::PARTIAL_REFUND_INITIATED,
                StateTransitionEvent::DISPUTE_OPENED,
            ],
            TransactionStatus::PARTIALLY_REFUNDED => [
                StateTransitionEvent::REFUND_COMPLETED,
                StateTransitionEvent::DISPUTE_OPENED,
            ],
            TransactionStatus::DISPUTED => [
                StateTransitionEvent::DISPUTE_RESOLVED_LOST,
            ],
            default => [],
        };
    }

    private function targetFor(
        TransactionStatus $from,
        StateTransitionEvent $event,
    ): TransactionStatus {
        $key = "{$from->value}|{$event->value}";
        $table = [
            // INITIALIZED +
            'initialized|auth_ok' => TransactionStatus::PENDING,
            'initialized|gateway_confirmed' => TransactionStatus::PENDING,
            'initialized|capture_received' => TransactionStatus::CAPTURED,
            'initialized|gateway_failed' => TransactionStatus::FAILED,
            'initialized|gateway_timeout' => TransactionStatus::EXPIRED,
            'initialized|customer_cancelled' => TransactionStatus::CANCELLED,

            // PENDING +
            'pending|gateway_confirmed' => TransactionStatus::AUTHORIZED,
            'pending|capture_received' => TransactionStatus::CAPTURED,
            'pending|gateway_failed' => TransactionStatus::FAILED,
            'pending|gateway_timeout' => TransactionStatus::EXPIRED,
            'pending|customer_cancelled' => TransactionStatus::CANCELLED,

            // AUTHORIZED +
            'authorized|capture_received' => TransactionStatus::CAPTURED,
            'authorized|gateway_failed' => TransactionStatus::FAILED,
            'authorized|customer_cancelled' => TransactionStatus::CANCELLED,

            // CAPTURED +
            'captured|settlement_notice' => TransactionStatus::SETTLING,
            'captured|settlement_confirmed' => TransactionStatus::SETTLED,
            'captured|refund_initiated' => TransactionStatus::REFUNDED,
            'captured|partial_refund_initiated' => TransactionStatus::PARTIALLY_REFUNDED,
            'captured|dispute_opened' => TransactionStatus::DISPUTED,

            // SETTLING +
            'settling|settlement_confirmed' => TransactionStatus::SETTLED,
            'settling|settlement_failed' => TransactionStatus::FAILED,
            'settling|dispute_opened' => TransactionStatus::DISPUTED,

            // SETTLED +
            'settled|refund_initiated' => TransactionStatus::REFUNDED,
            'settled|partial_refund_initiated' => TransactionStatus::PARTIALLY_REFUNDED,
            'settled|dispute_opened' => TransactionStatus::DISPUTED,

            // DISPUTED +
            'disputed|dispute_resolved_lost' => TransactionStatus::FAILED,

            // PARTIALLY_REFUNDED +
            'partially_refunded|refund_completed' => TransactionStatus::REFUNDED,
            'partially_refunded|dispute_opened' => TransactionStatus::DISPUTED,
        ];

        if (! isset($table[$key])) {
            throw PaymentStateTransitionException::invalidTransition($from, $from, $event->value);
        }

        return $table[$key];
    }

    private function isTerminal(TransactionStatus $status): bool
    {
        return $status->isTerminal();
    }
}
