<?php

declare(strict_types=1);

namespace Tests\Feature\Runtime\Failure;

use App\Runtime\Failure\Contracts\FailureReportingContract;
use App\Runtime\Failure\Enums\FailureKind;
use App\Runtime\Failure\Enums\FailureState;
use App\Runtime\Failure\FailureRouter;
use App\Runtime\Failure\Handlers\BootFailureHandler;
use App\Runtime\Failure\Handlers\ProbeFailureHandler;
use App\Runtime\Failure\StateMachines\FailureStateMachine;
use App\Runtime\Failure\StateMachines\FailureTransitionResult;
use App\Runtime\Failure\ValueObjects\FailureRecord;
use App\Shared\Support\Clock;
use App\Shared\Support\Identifier;
use App\Shared\Support\SystemClock;
use DateTimeImmutable;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for FailureRouter — the central coordinator that walks
 * a failure through its lifecycle by consulting the state machine at
 * each step.
 */
final class FailureRouterTest extends TestCase
{
    private function makeRecord(FailureKind $kind, FailureState $state = FailureState::Observed, array $context = [], string $message = 'test'): FailureRecord
    {
        return new FailureRecord(
            id: Identifier::generate(),
            kind: $kind,
            origin: self::class,
            message: $message,
            previousState: $state,
            context: $context,
            occurredAt: new DateTimeImmutable(),
        );
    }

    #[Test]
    public function the_router_resolves_from_the_container(): void
    {
        $router = $this->app->make(FailureReportingContract::class);
        $this->assertInstanceOf(FailureRouter::class, $router);
    }

    #[Test]
    public function report_walks_a_failure_through_the_full_lifecycle(): void
    {
        Log::spy();

        $router = $this->app->make(FailureReportingContract::class);
        $result = $router->report($this->makeRecord(FailureKind::HttpUnhandled));

        $this->assertInstanceOf(FailureTransitionResult::class, $result);
        $this->assertSame(FailureState::Resolved, $result->nextState);

        // Should have logged exactly once at the Recorded transition.
        Log::shouldHaveReceived('log')->once()->withArgs(
            fn ($level) => $level === 'ERROR',
            \Mockery::any(),
            \Mockery::any(),
        );
    }

    #[Test]
    public function report_dispatches_to_the_inferred_handler(): void
    {
        $handlerSpy = new class {
            public int $called = 0;
            public function handle(FailureRecord $r, FailureTransitionResult $t): void
            {
                $this->called++;
            }
        };
        $this->app->instance(ProbeFailureHandler::class, $handlerSpy);
        $this->app->forgetInstance(FailureRouter::class);

        $router = $this->app->make(FailureReportingContract::class);
        $router->report($this->makeRecord(FailureKind::ProbeSubsystemDown));

        $this->assertSame(1, $handlerSpy->called);
    }

    #[Test]
    public function report_skips_handler_dispatch_when_no_handler_is_inferred(): void
    {
        // PersistenceQueryFailed has no auto-handler.
        $handlerSpy = new class {
            public int $called = 0;
            public function handle(FailureRecord $r, FailureTransitionResult $t): void
            {
                $this->called++;
            }
        };
        $this->app->instance(\App\Runtime\Failure\Handlers\BootFailureHandler::class, $handlerSpy);
        $this->app->forgetInstance(FailureRouter::class);

        $router = $this->app->make(FailureReportingContract::class);
        $router->report($this->makeRecord(FailureKind::PersistenceQueryFailed));

        $this->assertSame(0, $handlerSpy->called);
    }

    #[Test]
    public function report_coalesces_duplicate_probe_failures_within_window(): void
    {
        Log::spy();
        $router = $this->app->make(FailureReportingContract::class);

        // First report — full lifecycle, log + handler.
        $router->report($this->makeRecord(FailureKind::ProbeSubsystemDown, message: 'redis down'));

        // Second identical report within window — coalesced, no log.
        $result = $router->report($this->makeRecord(FailureKind::ProbeSubsystemDown, message: 'redis down'));

        $this->assertTrue($result->coalesce);
        $this->assertSame(60, $result->coalesceWindowSeconds);
    }

    #[Test]
    public function report_does_not_coalesce_different_messages(): void
    {
        Log::spy();
        $router = $this->app->make(FailureReportingContract::class);

        $router->report($this->makeRecord(FailureKind::ProbeSubsystemDown, message: 'redis down'));
        $router->report($this->makeRecord(FailureKind::ProbeSubsystemDown, message: 'memcached down'));

        // Two distinct messages → two logs.
        Log::shouldHaveReceived('log')->twice();
    }

    #[Test]
    public function report_handles_handler_exceptions_without_propagating(): void
    {
        $crashingHandler = new class {
            public function handle(FailureRecord $r, FailureTransitionResult $t): void
            {
                throw new \RuntimeException('handler crashed');
            }
        };
        $this->app->instance(ProbeFailureHandler::class, $crashingHandler);
        $this->app->forgetInstance(FailureRouter::class);

        Log::spy();

        $router = $this->app->make(FailureReportingContract::class);
        $result = $router->report($this->makeRecord(FailureKind::ProbeSubsystemDown));

        // Router survives — returns Resolved without propagating.
        $this->assertSame(FailureState::Resolved, $result->nextState);
        Log::shouldHaveReceived('error')->atLeast()->once();
    }

    #[Test]
    public function report_for_boot_failure_throws_environment_validation_exception(): void
    {
        $router = $this->app->make(FailureReportingContract::class);

        $this->expectException(\App\Runtime\Exceptions\EnvironmentValidationException::class);

        $router->report($this->makeRecord(
            FailureKind::BootEnvMissing,
            context: ['environment_type' => 'production', 'missing' => ['DATABASE_URL']],
        ));
    }
}