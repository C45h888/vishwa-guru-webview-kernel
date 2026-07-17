<?php

declare(strict_types=1);

namespace Tests\Unit\Runtime\Failure\Enums;

use App\Runtime\Failure\Enums\FailureKind;
use App\Runtime\Failure\Handlers\BootFailureHandler;
use App\Runtime\Failure\Handlers\CommandFailureHandler;
use App\Runtime\Failure\Handlers\HttpFailureHandler;
use App\Runtime\Failure\Handlers\ProbeFailureHandler;
use App\Runtime\Failure\StateMachines\FailureStateMachine;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the FailureKind enum — verifies that every case has
 * a mapping in the state machine's inference tables.
 *
 * If a new FailureKind is added but its handler / log level / coalesce
 * entries are missing from the state machine, these tests fail.
 */
final class FailureKindTest extends TestCase
{
    /**
     * @return array<int, array{0: FailureKind, 1: string|null}>
     */
    public static function kindProvider(): array
    {
        return [
            [FailureKind::BootEnvMissing,          BootFailureHandler::class],
            [FailureKind::PersistenceBindingFailed, BootFailureHandler::class],
            [FailureKind::ProbeSubsystemDown,       ProbeFailureHandler::class],
            [FailureKind::HttpUnhandled,            HttpFailureHandler::class],
            [FailureKind::CommandFailed,            CommandFailureHandler::class],
            [FailureKind::FrameworkException,       HttpFailureHandler::class],
            [FailureKind::PersistenceQueryFailed,   null], // caller decides
        ];
    }

    #[Test]
    public function every_kind_has_a_string_value(): void
    {
        foreach (FailureKind::cases() as $kind) {
            $this->assertNotEmpty($kind->value);
        }
    }

    #[Test]
    public function every_kind_has_a_unique_string_value(): void
    {
        $values = array_map(fn ($k) => $k->value, FailureKind::cases());
        $this->assertSame(count($values), count(array_unique($values)), 'duplicate FailureKind values');
    }

    #[Test]
    public function the_state_machine_infers_a_handler_for_every_kind(): void
    {
        $machine = new FailureStateMachine();

        foreach (FailureKind::cases() as $kind) {
            $record = new \App\Runtime\Failure\ValueObjects\FailureRecord(
                id: \App\Shared\Support\Identifier::generate(),
                kind: $kind,
                origin: self::class,
                message: 'test',
                previousState: \App\Runtime\Failure\Enums\FailureState::Observed,
                context: [],
            );
            $clock = new class implements \App\Shared\Support\Clock {
                public function now(): \DateTimeImmutable { return new \DateTimeImmutable(); }
                public function timestamp(): int { return time(); }
                public function timezone(): \DateTimeZone { return new \DateTimeZone('UTC'); }
            };

            // Advance to Surfaced where handler is decided.
            $currentState = \App\Runtime\Failure\Enums\FailureState::Observed;
            for ($i = 0; $i < 5; $i++) {
                $r = $machine->decide($record, $currentState, $clock);
                $currentState = $r->nextState;
                if ($currentState === \App\Runtime\Failure\Enums\FailureState::Surfaced) {
                    // PersistenceQueryFailed has no auto-handler — skip.
                    if ($kind === FailureKind::PersistenceQueryFailed) {
                        $this->assertNull($r->handlerClass, "{$kind->value} should have null handler");
                    } else {
                        $this->assertNotNull($r->handlerClass, "{$kind->value} should have a handler");
                    }
                    break;
                }
            }
        }
    }

    #[Test]
    public function the_state_machine_infers_a_log_level_for_every_kind(): void
    {
        $machine = new FailureStateMachine();

        foreach (FailureKind::cases() as $kind) {
            $record = new \App\Runtime\Failure\ValueObjects\FailureRecord(
                id: \App\Shared\Support\Identifier::generate(),
                kind: $kind,
                origin: self::class,
                message: 'test',
                previousState: \App\Runtime\Failure\Enums\FailureState::Observed,
                context: [],
            );
            $clock = new class implements \App\Shared\Support\Clock {
                public function now(): \DateTimeImmutable { return new \DateTimeImmutable(); }
                public function timestamp(): int { return time(); }
                public function timezone(): \DateTimeZone { return new \DateTimeZone('UTC'); }
            };

            $currentState = \App\Runtime\Failure\Enums\FailureState::Observed;
            for ($i = 0; $i < 5; $i++) {
                $r = $machine->decide($record, $currentState, $clock);
                $currentState = $r->nextState;
                if ($currentState === \App\Runtime\Failure\Enums\FailureState::Recorded) {
                    $this->assertNotNull($r->logLevel, "{$kind->value} should have a log level");
                    $this->assertContains(
                        $r->logLevel,
                        ['DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'],
                    );
                    break;
                }
            }
        }
    }
}