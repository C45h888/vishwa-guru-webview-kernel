<?php

declare(strict_types=1);

namespace App\Payments\Domain\StateMachines;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * The output bundle of a state machine transition.
 *
 * Each state machine produces the same shape:
 *   • toState            — the new state (typed to the machine's target enum)
 *   • entityChanges      — array<string, mixed> for the entity's withChanges()
 *   • timestampChanges   — column => DateTimeImmutable for direct field assign
 *   • metadata           — side-channel for FSM-internal info (audit hints)
 *
 * The toState type is intentionally a mixed property because the same
 * class backs PaymentStateMachine (TransactionStatus target),
 * DonationStateMachine (DonationState target), and ReceiptStateMachine
 * (ReceiptDeliveryState target). Downstream code pattern-matches.
 */
final readonly class StateTransitionResult
{
    /**
     * @param  TransactionStatus|DonationState|ReceiptDeliveryState|string  $toState
     * @param  array<string, mixed>  $entityChanges
     * @param  array<string, DateTimeImmutable>  $timestampChanges
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        private mixed $toState,
        private array $entityChanges,
        private array $timestampChanges = [],
        private array $metadata = [],
    ) {
        if (empty($entityChanges) && empty($timestampChanges)) {
            throw new InvalidArgumentException(
                'StateTransitionResult must carry at least one change or stamp'
            );
        }
    }

    /**
     * @return TransactionStatus|DonationState|ReceiptDeliveryState|string
     */
    public function toState(): mixed
    {
        return $this->toState;
    }

    /**
     * @return array<string, mixed>
     */
    public function entityChanges(): array
    {
        return $this->entityChanges;
    }

    /**
     * @return array<string, DateTimeImmutable>
     */
    public function timestampChanges(): array
    {
        return $this->timestampChanges;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * Convenience: the sole entity change keyed by `field => value`.
     *
     * @return array<string, mixed>
     */
    public function primaryEntityChange(): array
    {
        return $this->entityChanges;
    }

    /**
     * Whether this result carries a timestamp update for the given column.
     */
    public function updatesTimestamp(string $column): bool
    {
        return array_key_exists($column, $this->timestampChanges);
    }

    /**
     * The timestamp value for a given column, if present.
     */
    public function timestampFor(string $column): ?DateTimeImmutable
    {
        return $this->timestampChanges[$column] ?? null;
    }
}