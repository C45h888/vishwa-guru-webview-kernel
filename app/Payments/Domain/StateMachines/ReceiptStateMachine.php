<?php

declare(strict_types=1);

namespace App\Payments\Domain\StateMachines;

use App\Payments\Domain\Enums\ReceiptDeliveryState;
use App\Payments\Domain\Exceptions\PaymentStateTransitionException;
use DateTimeImmutable;

/**
 * Receipt delivery state machine.
 *
 * Receipt CONTENT is immutable post-issue; only delivery status
 * transitions through this machine. Receipt::withChanges() delegates
 * delivery-status changes here.
 */
final class ReceiptStateMachine
{
    public function transition(
        ReceiptDeliveryState $from,
        StateTransitionEvent $event,
        array $context = [],
    ): StateTransitionResult {
        if ($from->isTerminal()) {
            throw new \LogicException(
                sprintf('Receipt delivery state [%s] is terminal; no transitions allowed', $from->value)
            );
        }

        $allowed = $this->allowedEvents($from);
        if (! in_array($event, $allowed, true)) {
            throw new \LogicException(
                sprintf(
                    'Receipt delivery transition [%s + %s] is not permitted',
                    $from->value,
                    $event->value,
                )
            );
        }

        $to = $this->targetFor($from, $event);
        $entityChanges = ['delivery_status' => $to->value];
        $timestampChanges = [];

        if ($to === ReceiptDeliveryState::DELIVERED) {
            $timestampChanges['delivered_at'] = new DateTimeImmutable();
        } elseif ($to === ReceiptDeliveryState::PENDING && isset($context['redispatched_at'])) {
            $timestampChanges['delivered_at'] = null;
        }

        return new StateTransitionResult(
            toState: $to,
            entityChanges: $entityChanges,
            timestampChanges: $timestampChanges,
        );
    }

    public function canTransition(
        ReceiptDeliveryState $from,
        StateTransitionEvent $event,
    ): bool {
        if ($from->isTerminal()) {
            return false;
        }

        return in_array($event, $this->allowedEvents($from), true);
    }

    /**
     * @return array<int, ReceiptDeliveryState>
     */
    public function allowedNext(ReceiptDeliveryState $from): array
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
    private function allowedEvents(ReceiptDeliveryState $from): array
    {
        return match ($from) {
            ReceiptDeliveryState::PENDING => [
                StateTransitionEvent::DELIVERY_DISPATCHED,
                StateTransitionEvent::DELIVERY_BOUNCED,
                StateTransitionEvent::DELIVERY_FAILED,
            ],
            ReceiptDeliveryState::FAILED => [
                StateTransitionEvent::DELIVERY_REDISPATCHED,
            ],
            ReceiptDeliveryState::BOUNCED => [
                StateTransitionEvent::DELIVERY_REDISPATCHED,
            ],
            ReceiptDeliveryState::DELIVERED => [],
        };
    }

    private function targetFor(
        ReceiptDeliveryState $from,
        StateTransitionEvent $event,
    ): ReceiptDeliveryState {
        $table = [
            [ReceiptDeliveryState::PENDING, StateTransitionEvent::DELIVERY_DISPATCHED] => ReceiptDeliveryState::DELIVERED,
            [ReceiptDeliveryState::PENDING, StateTransitionEvent::DELIVERY_BOUNCED] => ReceiptDeliveryState::BOUNCED,
            [ReceiptDeliveryState::PENDING, StateTransitionEvent::DELIVERY_FAILED] => ReceiptDeliveryState::FAILED,

            [ReceiptDeliveryState::FAILED, StateTransitionEvent::DELIVERY_REDISPATCHED] => ReceiptDeliveryState::PENDING,
            [ReceiptDeliveryState::BOUNCED, StateTransitionEvent::DELIVERY_REDISPATCHED] => ReceiptDeliveryState::PENDING,
        ];

        $key = [$from, $event];
        if (! isset($table[$key])) {
            throw new \LogicException(
                sprintf(
                    'Receipt delivery transition table miss: [%s + %s]',
                    $from->value,
                    $event->value,
                )
            );
        }

        return $table[$key];
    }

    /**
     * Sentinel — ReceiptDeliveryState::DELIVERED terminal hint
     * from a real LogicException for invalid transitions.
     */
    private static function terminalMessage(ReceiptDeliveryState $state): string
    {
        return sprintf(
            'Receipt delivery state [%s] is terminal',
            $state->value,
        );
    }
}
