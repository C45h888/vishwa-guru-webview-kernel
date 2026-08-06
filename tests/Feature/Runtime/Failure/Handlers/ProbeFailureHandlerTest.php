<?php

declare(strict_types=1);

namespace Tests\Feature\Runtime\Failure\Handlers;

use App\Runtime\Failure\Enums\FailureKind;
use App\Runtime\Failure\Enums\FailureState;
use App\Runtime\Failure\Handlers\ProbeFailureHandler;
use App\Runtime\Failure\StateMachines\FailureTransitionResult;
use App\Runtime\Failure\ValueObjects\FailureRecord;
use App\Shared\ValueObjects\Identifier;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for ProbeFailureHandler — currently a structural
 * side-effect executor (logging happens at the Recorded transition
 * in the router; the Surfaced transition is currently a no-op).
 */
final class ProbeFailureHandlerTest extends TestCase
{
    #[Test]
    public function it_resolves_from_the_container(): void
    {
        $handler = $this->app->make(ProbeFailureHandler::class);
        $this->assertInstanceOf(ProbeFailureHandler::class, $handler);
    }

    #[Test]
    public function handle_does_not_throw_on_probe_subsystem_down(): void
    {
        $handler = $this->app->make(ProbeFailureHandler::class);

        $record = new FailureRecord(
            id: Identifier::generate(),
            kind: FailureKind::ProbeSubsystemDown,
            origin: self::class,
            message: 'redis down',
            previousState: FailureState::Surfaced,
            context: ['subsystem' => 'cache'],
        );
        $result = new FailureTransitionResult(
            nextState: FailureState::Surfaced,
            handlerClass: ProbeFailureHandler::class,
            userMessage: 'cache is unhealthy: redis down',
        );

        // No exception expected.
        $handler->handle($record, $result);
        $this->assertTrue(true); // Marker for "no exception thrown".
    }
}