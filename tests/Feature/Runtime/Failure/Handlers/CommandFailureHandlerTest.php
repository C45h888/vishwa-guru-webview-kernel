<?php

declare(strict_types=1);

namespace Tests\Feature\Runtime\Failure\Handlers;

use App\Runtime\Failure\Enums\FailureKind;
use App\Runtime\Failure\Enums\FailureState;
use App\Runtime\Failure\Handlers\CommandFailureHandler;
use App\Runtime\Failure\StateMachines\FailureTransitionResult;
use App\Runtime\Failure\ValueObjects\FailureRecord;
use App\Shared\ValueObjects\Identifier;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for CommandFailureHandler — structural side-effect
 * executor for CommandFailed failures.
 */
final class CommandFailureHandlerTest extends TestCase
{
    #[Test]
    public function it_resolves_from_the_container(): void
    {
        $handler = $this->app->make(CommandFailureHandler::class);
        $this->assertInstanceOf(CommandFailureHandler::class, $handler);
    }

    #[Test]
    public function handle_does_not_throw_on_command_failed(): void
    {
        $handler = $this->app->make(CommandFailureHandler::class);

        $record = new FailureRecord(
            id: Identifier::generate(),
            kind: FailureKind::CommandFailed,
            origin: self::class,
            message: 'temple:env failed',
            previousState: FailureState::Surfaced,
            context: ['command' => 'temple:env', 'missing' => ['DATABASE_URL']],
        );
        $result = new FailureTransitionResult(
            nextState: FailureState::Surfaced,
            handlerClass: CommandFailureHandler::class,
            userMessage: 'artisan command temple:env failed',
        );

        $handler->handle($record, $result);
        $this->assertTrue(true);
    }
}