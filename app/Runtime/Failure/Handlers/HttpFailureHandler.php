<?php

declare(strict_types=1);

namespace App\Runtime\Failure\Handlers;

use App\Runtime\Failure\StateMachines\FailureTransitionResult;
use App\Runtime\Failure\ValueObjects\FailureRecord;

/**
 * Executes the side effect for HttpUnhandled / FrameworkException failures.
 *
 * Doctrine: receives a fully-formed FailureRecord + FailureTransitionResult
 * from the router. The state machine has already decided log level
 * (ERROR or CRITICAL), user message, etc.
 *
 * This handler's job: register that an unhandled HTTP exception has been
 * observed. The actual HTTP response rendering is Laravel's job (via
 * the exception handler in bootstrap/app.php — see Phase F's wiring).
 *
 * No-op at the handler level for now — the Surfaced transition is purely
 * structural. Logging happens at the Recorded transition.
 */
final class HttpFailureHandler
{
    public function handle(FailureRecord $record, FailureTransitionResult $result): void
    {
        // Side effect placeholder — see class docblock.
    }
}