<?php

declare(strict_types=1);

namespace App\Runtime\Failure\StateMachines;

use App\Runtime\Failure\Enums\FailureKind;
use App\Runtime\Failure\Enums\FailureState;
use App\Runtime\Failure\Handlers\BootFailureHandler;
use App\Runtime\Failure\Handlers\CommandFailureHandler;
use App\Runtime\Failure\Handlers\HttpFailureHandler;
use App\Runtime\Failure\Handlers\ProbeFailureHandler;
use App\Shared\Support\Clock;
use App\Shared\ValueObjects\Identifier;

/**
 * The runtime kernel's inference model + routing membrane + deterministic
 * decisionary logic.
 *
 * Pure function. No I/O. No side effects. No randomness. No internal time
 * reads. Given the same (FailureRecord, FailureState, Clock) triple, it
 * always returns the same FailureTransitionResult.
 *
 * Doctrine:
 *   - Single decision vector for every failure in the runtime kernel.
 *   - Holds the kind→handler-class, kind→log-level, kind→message-template
 *     tables that drive dispatch.
 *   - Decides WHAT to do; the router decides WHEN.
 *   - Handlers are pure executors — they receive a fully-formed
 *     FailureTransitionResult and apply it; no decision logic in handlers.
 *
 * Singleton-bound in RuntimeServiceProvider. Lives inside the runtime
 * kernel at app/Runtime/Failure/StateMachines/.
 */
final class FailureStateMachine
{
    /**
     * Allowed transitions: currentState → nextState.
     * Linear progression. Resolved is terminal.
     *
     * @var array<string, string>
     */
    private const TRANSITIONS = [
        'observed'   => 'classified',
        'classified' => 'recorded',
        'recorded'   => 'surfaced',
        'surfaced'   => 'resolved',
        'resolved'   => 'resolved', // terminal self-loop
    ];

    /**
     * Kind → handler class. null means "no auto-dispatch; caller decides".
     *
     * @var array<string, string|null>
     */
    private const KIND_TO_HANDLER = [
        'boot.env_missing'           => BootFailureHandler::class,
        'persistence.binding_failed' => BootFailureHandler::class,
        'persistence.query_failed'   => null, // Caller decides; no auto-handler
        'probe.subsystem_down'       => ProbeFailureHandler::class,
        'http.unhandled'             => HttpFailureHandler::class,
        'command.failed'             => CommandFailureHandler::class,
        'framework.exception'        => HttpFailureHandler::class,
    ];

    /**
     * Kind → PSR-3 log level (applied at the Recorded transition).
     *
     * @var array<string, string>
     */
    private const KIND_TO_LOG_LEVEL = [
        'boot.env_missing'           => 'CRITICAL',
        'persistence.binding_failed' => 'CRITICAL',
        'persistence.query_failed'   => 'ERROR',
        'probe.subsystem_down'       => 'WARNING',
        'http.unhandled'             => 'ERROR',
        'command.failed'             => 'WARNING',
        'framework.exception'        => 'CRITICAL',
    ];

    /**
     * Kinds that are candidates for coalescing.
     * Repeated occurrences (e.g. Redis down for an hour) should not
     * flood the log. The router owns the actual dedup window state;
     * the state machine just signals intent.
     *
     * @var array<string, bool>
     */
    private const KIND_COALESCES = [
        'boot.env_missing'           => false,
        'persistence.binding_failed' => false,
        'persistence.query_failed'   => false,
        'probe.subsystem_down'       => true,
        'http.unhandled'             => false,
        'command.failed'             => false,
        'framework.exception'        => false,
    ];

    /**
     * Default coalesce window in seconds (60s = 1 minute).
     * Tuned for transient subsystem failures; longer windows risk
     * missing genuine state changes.
     */
    private const COALESCE_WINDOW_SECONDS = 60;

    /**
     * Make a single deterministic decision.
     *
     * Caller pattern (the router):
     *   $result = $machine->decide($record, $currentState, $clock);
     *   // apply side effects encoded in $result (log, dispatch, surface)
     *   // ...loop until $result->nextState === Resolved
     *
     * Doctrine: NO I/O, NO side effects, NO randomness, NO `time()` calls.
     * The Clock parameter is passed in by the caller — never read internally.
     */
    public function decide(
        \App\Runtime\Failure\ValueObjects\FailureRecord $record,
        FailureState $currentState,
        Clock $clock,
    ): FailureTransitionResult {
        $nextStateValue = self::TRANSITIONS[$currentState->value] ?? 'resolved';
        $nextState = FailureState::from($nextStateValue);

        $kindValue = $record->kind->value;

        // Inference: handler class is decided at the Surfaced transition.
        // Other transitions return null (no dispatch at this step).
        $handlerClass = null;
        if ($nextState === FailureState::Surfaced) {
            $handlerClass = self::KIND_TO_HANDLER[$kindValue] ?? null;
        }

        // Inference: log level is decided at the Recorded transition.
        $logLevel = null;
        if ($nextState === FailureState::Recorded) {
            $logLevel = self::KIND_TO_LOG_LEVEL[$kindValue] ?? 'ERROR';
        }

        // Inference: user-facing message is decided at the Surfaced transition.
        $userMessage = null;
        if ($nextState === FailureState::Surfaced) {
            $userMessage = $this->renderUserMessage($record);
        }

        // Coalesce decision: signaled per-kind, router does the actual dedup.
        $coalesce = self::KIND_COALESCES[$kindValue] ?? false;
        $coalesceWindow = $coalesce ? self::COALESCE_WINDOW_SECONDS : 0;

        return new FailureTransitionResult(
            nextState: $nextState,
            handlerClass: $handlerClass,
            logLevel: $logLevel,
            userMessage: $userMessage,
            coalesce: $coalesce,
            coalesceWindowSeconds: $coalesceWindow,
        );
    }

    /**
     * Render a templated user-facing message for the Surfaced transition.
     * Pure — no I/O, no translation services.
     */
    private function renderUserMessage(\App\Runtime\Failure\ValueObjects\FailureRecord $record): string
    {
        $template = match ($record->kind) {
            FailureKind::BootEnvMissing          => 'Application boot failed: required environment key is missing.',
            FailureKind::PersistenceBindingFailed => 'Application boot failed: persistence layer could not be initialized.',
            FailureKind::ProbeSubsystemDown       => 'Subsystem [{$subsystem}] is unhealthy: {$detail}',
            FailureKind::HttpUnhandled            => 'Unhandled HTTP exception: {$message}',
            FailureKind::CommandFailed            => 'Artisan command [{$command}] exited with failure: {$message}',
            FailureKind::FrameworkException       => 'Framework exception: {$message}',
            FailureKind::PersistenceQueryFailed   => 'Persistence query failed: {$message}',
        };

        // Token replacement — pure. Unknown tokens remain as-is.
        $context = array_merge(['message' => $record->message], $record->context);
        $tokens = [];
        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $tokens['{$' . $key . '}'] = (string) $value;
            }
        }
        return strtr($template, $tokens);
    }
}