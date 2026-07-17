<?php

declare(strict_types=1);

namespace App\Runtime\Failure\Handlers;

use App\Runtime\Failure\StateMachines\FailureTransitionResult;
use App\Runtime\Failure\ValueObjects\FailureRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Executes the side effect for CommandFailed failures.
 *
 * Doctrine: receives a fully-formed FailureRecord + FailureTransitionResult
 * from the router. The state machine has decided log level, user message,
 * etc.
 *
 * This handler's job: surface the failure to the running command's
 * lifecycle so the command can return Command::FAILURE. The command
 * itself inspects the failure record (returned from report()) and
 * decides its own exit code.
 *
 * No-op at the handler level — the Surfaced transition is purely
 * structural for commands. Logging happens at the Recorded transition.
 */
final class CommandFailureHandler
{
    public function handle(FailureRecord $record, FailureTransitionResult $result): void
    {
        // Side effect placeholder — see class docblock.
    }
}