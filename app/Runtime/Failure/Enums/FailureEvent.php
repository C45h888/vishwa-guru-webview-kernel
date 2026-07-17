<?php

declare(strict_types=1);

namespace App\Runtime\Failure\Enums;

/**
 * String-backed events that drive FailureState transitions.
 *
 * Each event corresponds to one transition:
 *   Observe  → Observed   (initial)
 *   Classify → Classified
 *   Record   → Recorded
 *   Surface  → Surfaced
 *   Resolve  → Resolved
 *
 * Doctrine: the state machine owns the (currentState, event) → nextState
 * transition table; the router invokes decide() once per state and
 * the state machine returns the next state plus the side effects to apply.
 */
enum FailureEvent: string
{
    case Observe  = 'observe';
    case Classify = 'classify';
    case Record   = 'record';
    case Surface  = 'surface';
    case Resolve  = 'resolve';
}