<?php

declare(strict_types=1);

namespace App\Runtime\Failure\Handlers;

use App\Runtime\Exceptions\EnvironmentValidationException;
use App\Runtime\Failure\Enums\FailureKind;
use App\Runtime\Failure\Enums\FailureState;
use App\Runtime\Failure\StateMachines\FailureTransitionResult;
use App\Runtime\Failure\ValueObjects\FailureRecord;
use App\Shared\Enums\EnvironmentType;
use RuntimeException;
use Throwable;

/**
 * Executes the side effect for BootEnvMissing / PersistenceBindingFailed failures.
 *
 * Doctrine: receives a fully-formed FailureRecord + FailureTransitionResult
 * from the router. The state machine has already decided log level, user
 * message, etc. This handler's job is to throw the right exception — that
 * is the side effect that signals boot failure to Laravel's exception
 * handler.
 *
 * Reads context['environment_type'] and context['missing'] from the record.
 * These are populated by the reporter (BootProbe) before calling report().
 */
final class BootFailureHandler
{
    public function handle(FailureRecord $record, FailureTransitionResult $result): void
    {
        $environmentType = $this->resolveEnvironmentType($record);
        $missingKeys = $record->context['missing'] ?? [];

        if ($record->kind === FailureKind::BootEnvMissing) {
            throw EnvironmentValidationException::fromMissingKeys(
                $environmentType,
                $missingKeys,
            );
        }

        if ($record->kind === FailureKind::PersistenceBindingFailed) {
            throw new RuntimeException(
                sprintf(
                    'Persistence layer binding failed (origin: %s): %s',
                    $record->origin,
                    $record->message,
                ),
                0,
                $record->context['previous'] ?? null,
            );
        }

        // Unknown kind routed here — defensive throw.
        throw new RuntimeException(
            "BootFailureHandler received unexpected FailureKind: {$record->kind->value}",
        );
    }

    private function resolveEnvironmentType(FailureRecord $record): EnvironmentType
    {
        $value = $record->context['environment_type'] ?? null;
        if (is_string($value)) {
            $type = EnvironmentType::tryFrom($value);
            if ($type !== null) {
                return $type;
            }
        }
        return EnvironmentType::Production; // Fail-safe default for boot failures.
    }
}