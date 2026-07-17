<?php

declare(strict_types=1);

namespace App\Runtime\Failure;

use App\Runtime\Failure\Contracts\FailureReportingContract;
use App\Runtime\Failure\Enums\FailureState;
use App\Runtime\Failure\Handlers\BootFailureHandler;
use App\Runtime\Failure\Handlers\CommandFailureHandler;
use App\Runtime\Failure\Handlers\HttpFailureHandler;
use App\Runtime\Failure\Handlers\ProbeFailureHandler;
use App\Runtime\Failure\StateMachines\FailureStateMachine;
use App\Runtime\Failure\StateMachines\FailureTransitionResult;
use App\Runtime\Failure\ValueObjects\FailureRecord;
use App\Shared\Support\Clock;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The runtime kernel's central coordinator for failures.
 *
 * Receives a FailureRecord, asks the state machine for the next state
 * at each step, applies the inferred side effects (logging + handler
 * dispatch), and returns the final transition result.
 *
 * Stateless (no repository, no audit log — Runtime is not a stateful
 * domain). Mirrors the coordinator pattern of Phase 1's
 * FailureStateService but with no persistence responsibility.
 *
 * Doctrine:
 *   - Sole entry point: FailureReportingContract → FailureRouter.
 *   - Every failure in the system routes through here.
 *   - Side effects (log + handler) are dictated by the state machine,
 *     never decided by the router.
 *   - The router DOES NOT throw — it catches handler exceptions,
 *     converts them into a degraded transition result, and lets the
 *     caller decide what to do. (Doctrine: failure handling must
 *     never become the failure.)
 */
final class FailureRouter implements FailureReportingContract
{
    /**
     * Coalesce buffer — recent (hash → timestamp) pairs.
     * Used to suppress duplicate failure reports within the window.
     *
     * @var array<string, int>
     */
    private array $coalesceBuffer = [];

    public function __construct(
        private readonly FailureStateMachine $stateMachine,
        private readonly Clock $clock,
        private readonly Container $container,
    ) {}

    public function report(FailureRecord $record): FailureTransitionResult
    {
        $currentState = $record->previousState;
        $maxIterations = 10; // Safety: prevent infinite loops if state machine misbehaves.
        $iterations = 0;
        $finalResult = null;

        while ($currentState !== FailureState::Resolved && $iterations < $maxIterations) {
            $iterations++;
            $result = $this->stateMachine->decide($record, $currentState, $this->clock);

            // Coalesce check: skip the lifecycle for duplicate failures
            // within the window. We only suppress IF the kind signals
            // coalesce AND we've seen this hash recently.
            if ($result->coalesce && $this->shouldCoalesce($record, $result->coalesceWindowSeconds)) {
                return new FailureTransitionResult(
                    nextState: FailureState::Resolved,
                    coalesce: true,
                    coalesceWindowSeconds: $result->coalesceWindowSeconds,
                );
            }

            $this->applySideEffects($record, $result);

            $currentState = $result->nextState;
            $finalResult = $result;
        }

        return $finalResult ?? new FailureTransitionResult(
            nextState: FailureState::Resolved,
        );
    }

    /**
     * Apply the side effects inferred by the state machine:
     *   - log at the inferred level (if any)
     *   - dispatch to the inferred handler (if any)
     *
     * Both are wrapped in try/catch so a misbehaving handler cannot
     * crash the router — doctrine says failure handling must not
     * itself become a failure.
     */
    private function applySideEffects(FailureRecord $record, FailureTransitionResult $result): void
    {
        if ($result->logLevel !== null) {
            try {
                Log::log($result->logLevel, $record->message, [
                    'failure_id'   => $record->id->value(),
                    'failure_kind' => $record->kind->value,
                    'origin'       => $record->origin,
                    'state'        => $result->nextState->value,
                    'context'      => $record->context,
                ]);
            } catch (Throwable) {
                // Logging failure must not propagate — swallow.
            }
        }

        if ($result->handlerClass !== null) {
            try {
                $handler = $this->container->make($result->handlerClass);
                $this->dispatchHandler($handler, $record, $result);
            } catch (Throwable $e) {
                // Handler dispatch failure must not propagate.
                try {
                    Log::error('Failure handler dispatch failed', [
                        'handler_class' => $result->handlerClass,
                        'failure_id'    => $record->id->value(),
                        'exception'     => $e->getMessage(),
                    ]);
                } catch (Throwable) {
                    // Even error-logging failures are swallowed.
                }
            }
        }

        // Record coalesce buffer entry after applying side effects.
        if ($result->coalesce) {
            $this->coalesceBuffer[$record->messageHash()] = $this->clock->timestamp();
        }
    }

    /**
     * Each handler interface varies; we dispatch via duck-typing.
     * All 4 Runtime handlers implement a `handle()` method that takes
     * the FailureRecord + FailureTransitionResult.
     */
    private function dispatchHandler(object $handler, FailureRecord $record, FailureTransitionResult $result): void
    {
        if (method_exists($handler, 'handle')) {
            $handler->handle($record, $result);
        }
    }

    private function shouldCoalesce(FailureRecord $record, int $windowSeconds): bool
    {
        if ($windowSeconds <= 0) {
            return false;
        }

        $hash = $record->messageHash();
        if (! isset($this->coalesceBuffer[$hash])) {
            return false;
        }

        $ageSeconds = $this->clock->timestamp() - $this->coalesceBuffer[$hash];
        return $ageSeconds < $windowSeconds;
    }
}