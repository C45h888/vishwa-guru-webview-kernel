<?php

declare(strict_types=1);

namespace App\Runtime\Failure\Enums;

/**
 * The lifecycle a failure moves through inside the Runtime kernel.
 *
 * Linear progression Observed → Classified → Recorded → Surfaced → Resolved.
 * The state machine enforces this order; no state can be skipped.
 *
 * Resolved is terminal — the state machine returns it again on any
 * further decide() calls so the router's loop terminates.
 */
enum FailureState: string
{
    case Observed   = 'observed';    // captured at point of origin
    case Classified = 'classified';  // kind assigned, severity known
    case Recorded   = 'recorded';    // logged at the inferred level
    case Surfaced   = 'surfaced';    // translated to user-visible response
    case Resolved   = 'resolved';    // terminal — no further action
}