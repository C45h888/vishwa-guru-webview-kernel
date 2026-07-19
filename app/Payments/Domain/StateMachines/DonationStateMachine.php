<?php

declare(strict_types=1);

namespace App\Payments\Domain\StateMachines;

use App\Payments\Domain\Enums\DonationState;
use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Domain\Exceptions\PaymentStateTransitionException;
use DateTimeImmutable;

/**
 * Donation lifecycle state machine.
 *
 * Owns every valid Donation state transition. Mirrors the schema's
 * donation_state enum and the donations_state_*_ts CHECK constraints.
 *
 * The DonationState → TransactionStatus mapping lives here (not in the
 * entity) so PaymentStateTransitionException can carry the right context
 * for cross-domain exceptions.
 */
final class DonationStateMachine
{
    public function transition(
        DonationState $from,
        StateTransitionEvent $event,
        array $context = [],
    ): StateTransitionResult {
        if ($this->isTerminal($from)) {
            throw PaymentStateTransitionException::terminalCannotTransition(
                self::mapToTransactionStatus($from),
            );
        }

        $allowed = $this->allowedEvents($from);
        if (! in_array($event, $allowed, true)) {
            throw PaymentStateTransitionException::invalidTransition(
                self::mapToTransactionStatus($from),
                self::mapToTransactionStatus($from),
                $event->value,
            );
        }

        $now = new DateTimeImmutable();
        $to = $this->targetFor($from, $event);
        $entityChanges = ['state' => $to->value];
        $timestampChanges = [];

        $timestampMap = [
            DonationState::PENDING_PAYMENT->value => 'payment_initiated_at',
            DonationState::PAYMENT_VERIFIED->value => 'payment_verified_at',
            DonationState::RECEIPT_GENERATED->value => 'receipt_generated_at',
            DonationState::COMPLETED->value => 'completed_at',
            DonationState::FAILED->value => 'failed_at',
            DonationState::CANCELLED->value => 'cancelled_at',
        ];
        if (isset($timestampMap[$to->value])) {
            $timestampChanges[$timestampMap[$to->value]] = $now;
        }

        if (isset($context['receipt_id']) && $to === DonationState::RECEIPT_GENERATED) {
            $entityChanges['receipt_id'] = (string) $context['receipt_id'];
        }

        return new StateTransitionResult(
            toState: $to,
            entityChanges: $entityChanges,
            timestampChanges: $timestampChanges,
        );
    }

    public function canTransition(
        DonationState $from,
        StateTransitionEvent $event,
    ): bool {
        if ($this->isTerminal($from)) {
            return false;
        }

        return in_array($event, $this->allowedEvents($from), true);
    }

    public function allowedNext(DonationState $from): array
    {
        $events = $this->allowedEvents($from);
        $targets = [];
        foreach ($events as $event) {
            $targets[] = $this->targetFor($from, $event);
        }

        return array_values(array_unique($targets, SORT_REGULAR));
    }

    private function allowedEvents(DonationState $from): array
    {
        return match ($from) {
            DonationState::DRAFT => [
                StateTransitionEvent::SUBMITTED,
                StateTransitionEvent::CUSTOMER_CANCELLED,
            ],
            DonationState::PENDING_PAYMENT => [
                StateTransitionEvent::GATEWAY_CONFIRMED,
                StateTransitionEvent::GATEWAY_FAILED,
                StateTransitionEvent::WEBHOOK_TIMEOUT,
                StateTransitionEvent::CUSTOMER_CANCELLED,
            ],
            DonationState::PAYMENT_VERIFIED => [
                StateTransitionEvent::RECEIPT_ISSUED,
                StateTransitionEvent::RECEIPT_FAILED,
                StateTransitionEvent::CUSTOMER_CANCELLED,
            ],
            DonationState::RECEIPT_GENERATED => [
                StateTransitionEvent::COMPLETED,
                StateTransitionEvent::POST_COMMIT_FAIL,
            ],
            default => [],
        };
    }

    private function targetFor(
        DonationState $from,
        StateTransitionEvent $event,
    ): DonationState {
        $key = "{$from->value}|{$event->value}";
        $table = [
            'draft|submitted' => DonationState::PENDING_PAYMENT,
            'draft|customer_cancelled' => DonationState::CANCELLED,

            'pending_payment|gateway_confirmed' => DonationState::PAYMENT_VERIFIED,
            'pending_payment|gateway_failed' => DonationState::FAILED,
            'pending_payment|webhook_timeout' => DonationState::CANCELLED,
            'pending_payment|customer_cancelled' => DonationState::CANCELLED,

            'payment_verified|receipt_issued' => DonationState::RECEIPT_GENERATED,
            'payment_verified|receipt_failed' => DonationState::FAILED,
            'payment_verified|customer_cancelled' => DonationState::CANCELLED,

            'receipt_generated|completed' => DonationState::COMPLETED,
            'receipt_generated|post_commit_fail' => DonationState::FAILED,
        ];

        if (! isset($table[$key])) {
            throw PaymentStateTransitionException::invalidTransition(
                self::mapToTransactionStatus($from),
                self::mapToTransactionStatus($from),
                $event->value,
            );
        }

        return $table[$key];
    }

    private function isTerminal(DonationState $state): bool
    {
        return $state->isTerminal();
    }

    public static function mapToTransactionStatus(DonationState $state): TransactionStatus
    {
        return match ($state) {
            DonationState::DRAFT => TransactionStatus::INITIALIZED,
            DonationState::PENDING_PAYMENT => TransactionStatus::PENDING,
            DonationState::PAYMENT_VERIFIED => TransactionStatus::CAPTURED,
            DonationState::RECEIPT_GENERATED => TransactionStatus::SETTLING,
            DonationState::COMPLETED => TransactionStatus::SETTLED,
            DonationState::FAILED => TransactionStatus::FAILED,
            DonationState::CANCELLED => TransactionStatus::CANCELLED,
        };
    }
}
