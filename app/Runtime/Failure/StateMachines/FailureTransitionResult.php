<?php

declare(strict_types=1);

namespace App\Runtime\Failure\StateMachines;

use App\Runtime\Failure\Enums\FailureState;

/**
 * Immutable decision output from FailureStateMachine::decide().
 *
 * Carries the inferred next state plus any side-effect descriptors the
 * router must apply at this transition:
 *   - handlerClass:         which handler to dispatch (null = no dispatch)
 *   - logLevel:             which PSR-3 log level to record at (null = no log)
 *   - userMessage:          templated message to surface (null = no surface)
 *   - coalesce:             whether the router should attempt to dedup
 *   - coalesceWindowSeconds: window for dedup (0 = no coalesce)
 *
 * Doctrine: ALL fields are decided by the state machine, not by the
 * router. Handlers are pure executors — they never decide WHAT to do,
 * only execute the decision they receive.
 */
final class FailureTransitionResult
{
    public function __construct(
        public readonly FailureState $nextState,
        public readonly ?string $handlerClass = null,
        public readonly ?string $logLevel = null,
        public readonly ?string $userMessage = null,
        public readonly bool $coalesce = false,
        public readonly int $coalesceWindowSeconds = 0,
    ) {}
}