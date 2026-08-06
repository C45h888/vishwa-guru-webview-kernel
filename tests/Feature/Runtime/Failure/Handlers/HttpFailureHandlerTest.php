<?php

declare(strict_types=1);

namespace Tests\Feature\Runtime\Failure\Handlers;

use App\Runtime\Failure\Enums\FailureKind;
use App\Runtime\Failure\Enums\FailureState;
use App\Runtime\Failure\Handlers\HttpFailureHandler;
use App\Runtime\Failure\StateMachines\FailureTransitionResult;
use App\Runtime\Failure\ValueObjects\FailureRecord;
use App\Shared\ValueObjects\Identifier;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for HttpFailureHandler — structural side-effect
 * executor for HttpUnhandled / FrameworkException failures.
 */
final class HttpFailureHandlerTest extends TestCase
{
    #[Test]
    public function it_resolves_from_the_container(): void
    {
        $handler = $this->app->make(HttpFailureHandler::class);
        $this->assertInstanceOf(HttpFailureHandler::class, $handler);
    }

    #[Test]
    public function handle_does_not_throw_on_http_unhandled(): void
    {
        $handler = $this->app->make(HttpFailureHandler::class);

        $record = new FailureRecord(
            id: Identifier::generate(),
            kind: FailureKind::HttpUnhandled,
            origin: self::class,
            message: 'division by zero',
            previousState: FailureState::Surfaced,
            context: [],
        );
        $result = new FailureTransitionResult(
            nextState: FailureState::Surfaced,
            handlerClass: HttpFailureHandler::class,
            userMessage: 'unhandled exception: division by zero',
        );

        $handler->handle($record, $result);
        $this->assertTrue(true);
    }
}