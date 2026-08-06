<?php

declare(strict_types=1);

namespace Tests\Unit\Runtime\Failure\StateMachines;

use App\Runtime\Failure\Enums\FailureKind;
use App\Runtime\Failure\Enums\FailureState;
use App\Runtime\Failure\Handlers\BootFailureHandler;
use App\Runtime\Failure\Handlers\CommandFailureHandler;
use App\Runtime\Failure\Handlers\HttpFailureHandler;
use App\Runtime\Failure\Handlers\ProbeFailureHandler;
use App\Runtime\Failure\StateMachines\FailureStateMachine;
use App\Runtime\Failure\ValueObjects\FailureRecord;
use App\Shared\Support\Clock;
use App\Shared\ValueObjects\Identifier;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Crown jewel test for the FailureStateMachine — the runtime kernel's
 * inference model + routing membrane + deterministic decisionary logic.
 *
 * Verifies:
 *   - Linear progression Observed → Classified → Recorded → Surfaced → Resolved
 *   - Each transition infers the correct side-effect descriptors
 *   - Coalesce signaling per kind
 *   - Determinism: same inputs always produce same outputs
 *   - Pure function: no exceptions thrown
 */
final class FailureStateMachineTest extends TestCase
{
    private function makeClock(): Clock
    {
        return new class implements Clock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-07-16T19:00:00+00:00');
            }
            public function timestamp(): int
            {
                return 1771194000;
            }
            public function timezone(): DateTimeZone
            {
                return new DateTimeZone('UTC');
            }
        };
    }

    private function makeRecord(FailureKind $kind, FailureState $state = FailureState::Observed, array $context = []): FailureRecord
    {
        return new FailureRecord(
            id: Identifier::generate(),
            kind: $kind,
            origin: self::class,
            message: 'test failure',
            previousState: $state,
            context: $context,
            occurredAt: new DateTimeImmutable(),
        );
    }

    #[Test]
    public function observed_transitions_to_classified_with_no_side_effects(): void
    {
        $machine = new FailureStateMachine();
        $result = $machine->decide($this->makeRecord(FailureKind::HttpUnhandled), FailureState::Observed, $this->makeClock());

        $this->assertSame(FailureState::Classified, $result->nextState);
        $this->assertNull($result->handlerClass);
        $this->assertNull($result->logLevel);
        $this->assertNull($result->userMessage);
    }

    #[Test]
    public function classified_transitions_to_recorded_with_inferred_log_level(): void
    {
        $machine = new FailureStateMachine();
        $result = $machine->decide($this->makeRecord(FailureKind::HttpUnhandled), FailureState::Classified, $this->makeClock());

        $this->assertSame(FailureState::Recorded, $result->nextState);
        $this->assertNull($result->handlerClass, 'no handler dispatched at Recorded transition');
        $this->assertSame('ERROR', $result->logLevel);
        $this->assertNull($result->userMessage);
    }

    #[Test]
    public function recorded_transitions_to_surfaced_with_inferred_handler_and_message(): void
    {
        $machine = new FailureStateMachine();
        $result = $machine->decide($this->makeRecord(FailureKind::HttpUnhandled), FailureState::Recorded, $this->makeClock());

        $this->assertSame(FailureState::Surfaced, $result->nextState);
        $this->assertSame(HttpFailureHandler::class, $result->handlerClass);
        $this->assertNotNull($result->userMessage);
    }

    #[Test]
    public function surfaced_transitions_to_resolved(): void
    {
        $machine = new FailureStateMachine();
        $result = $machine->decide($this->makeRecord(FailureKind::HttpUnhandled), FailureState::Surfaced, $this->makeClock());

        $this->assertSame(FailureState::Resolved, $result->nextState);
        $this->assertNull($result->handlerClass, 'no handler dispatched at Resolved transition');
    }

    #[Test]
    public function resolved_is_terminal(): void
    {
        $machine = new FailureStateMachine();
        $result = $machine->decide($this->makeRecord(FailureKind::HttpUnhandled), FailureState::Resolved, $this->makeClock());

        $this->assertSame(FailureState::Resolved, $result->nextState);
    }

    #[Test]
    public function boot_env_missing_maps_to_boot_failure_handler_and_critical_log(): void
    {
        $machine = new FailureStateMachine();
        $clock = $this->makeClock();
        $record = $this->makeRecord(FailureKind::BootEnvMissing);

        $r1 = $machine->decide($record, FailureState::Observed, $clock);
        $r2 = $machine->decide($record, $r1->nextState, $clock);
        $r3 = $machine->decide($record, $r2->nextState, $clock);
        $r4 = $machine->decide($record, $r3->nextState, $clock);

        $this->assertSame(FailureState::Resolved, $r4->nextState);
        $this->assertSame(BootFailureHandler::class, $r3->handlerClass);
        $this->assertSame('CRITICAL', $r2->logLevel);
        $this->assertFalse($r4->coalesce);
        $this->assertSame(0, $r4->coalesceWindowSeconds);
    }

    #[Test]
    public function probe_subsystem_down_coalesces_within_60_second_window(): void
    {
        $machine = new FailureStateMachine();
        $record = $this->makeRecord(FailureKind::ProbeSubsystemDown);

        $r3 = $machine->decide($record, FailureState::Recorded, $this->makeClock());

        $this->assertSame(ProbeFailureHandler::class, $r3->handlerClass);
        $this->assertTrue($r3->coalesce);
        $this->assertSame(60, $r3->coalesceWindowSeconds);
    }

    #[Test]
    public function command_failed_maps_to_command_failure_handler(): void
    {
        $machine = new FailureStateMachine();
        $r3 = $machine->decide($this->makeRecord(FailureKind::CommandFailed), FailureState::Recorded, $this->makeClock());

        $this->assertSame(CommandFailureHandler::class, $r3->handlerClass);
    }

    #[Test]
    public function persistence_query_failed_has_no_auto_handler(): void
    {
        $machine = new FailureStateMachine();
        $r3 = $machine->decide($this->makeRecord(FailureKind::PersistenceQueryFailed), FailureState::Recorded, $this->makeClock());

        $this->assertSame(FailureState::Surfaced, $r3->nextState);
        $this->assertNull($r3->handlerClass);
    }

    #[Test]
    public function decide_is_deterministic_for_same_inputs(): void
    {
        $machine = new FailureStateMachine();
        $clock = $this->makeClock();
        $record = $this->makeRecord(FailureKind::HttpUnhandled);

        $r1 = $machine->decide($record, FailureState::Observed, $clock);
        $r2 = $machine->decide($record, FailureState::Observed, $clock);

        $this->assertSame($r1->nextState, $r2->nextState);
        $this->assertSame($r1->logLevel, $r2->logLevel);
        $this->assertSame($r1->handlerClass, $r2->handlerClass);
        $this->assertSame($r1->userMessage, $r2->userMessage);
    }

    #[Test]
    public function user_message_replaces_tokens_from_record_context(): void
    {
        $machine = new FailureStateMachine();
        $record = $this->makeRecord(
            FailureKind::ProbeSubsystemDown,
            context: ['subsystem' => 'cache', 'detail' => 'redis unreachable'],
        );

        $r3 = $machine->decide($record, FailureState::Recorded, $this->makeClock());

        $this->assertStringContainsString('cache', $r3->userMessage);
        $this->assertStringContainsString('redis unreachable', $r3->userMessage);
    }
}