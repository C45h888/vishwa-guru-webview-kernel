<?php

declare(strict_types=1);

namespace App\Runtime\Failure\ValueObjects;

use App\Runtime\Failure\Enums\FailureKind;
use App\Runtime\Failure\Enums\FailureState;
use App\Shared\Support\Identifier;
use DateTimeImmutable;

/**
 * Immutable record of a single failure reported into the runtime kernel.
 *
 * Carries everything the FailureStateMachine needs to make an inference
 * decision:
 *   - id:           stable identifier for log correlation
 *   - kind:         closed FailureKind vocabulary
 *   - origin:       FQCN of the reporter (where the failure was caught)
 *   - message:      short human-readable description
 *   - previousState: the state the router has reached so far (defaults
 *                    to Observed when first reported)
 *   - context:      arbitrary structured data (key => mixed)
 *   - occurredAt:   timestamp from the Clock, not from real time
 *
 * Immutable — to advance through states, the router builds a NEW
 * FailureRecord with the new previousState.
 */
final class FailureRecord
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly Identifier $id,
        public readonly FailureKind $kind,
        public readonly string $origin,
        public readonly string $message,
        public readonly FailureState $previousState,
        public readonly array $context = [],
        public readonly DateTimeImmutable $occurredAt = new DateTimeImmutable(),
    ) {}

    /**
     * Build a new FailureRecord advanced to the next state. Pure.
     *
     * @param  array<string, mixed>  $additionalContext
     */
    public function withState(FailureState $next, array $additionalContext = []): self
    {
        return new self(
            id: $this->id,
            kind: $this->kind,
            origin: $this->origin,
            message: $this->message,
            previousState: $next,
            context: array_merge($this->context, $additionalContext),
            occurredAt: $this->occurredAt,
        );
    }

    /**
     * Deterministic message-hash used by the coalesce logic.
     * Same kind + origin + message → same hash.
     */
    public function messageHash(): string
    {
        return hash('xxh64', $this->kind->value . '|' . $this->origin . '|' . $this->message);
    }
}