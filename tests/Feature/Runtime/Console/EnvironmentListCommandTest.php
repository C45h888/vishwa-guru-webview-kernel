<?php

declare(strict_types=1);

namespace Tests\Feature\Runtime\Console;

use App\Runtime\Console\Commands\EnvironmentListCommand;
use App\Runtime\Failure\Contracts\FailureReportingContract;
use App\Runtime\Failure\Enums\FailureState;
use App\Runtime\Failure\StateMachines\FailureTransitionResult;
use App\Runtime\Failure\ValueObjects\FailureRecord;
use App\Shared\Contracts\ConfigurationContract;
use App\Shared\Contracts\EnvironmentContract;
use App\Shared\Enums\EnvironmentType;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Feature tests for `php artisan temple:env`.
 */
final class EnvironmentListCommandTest extends TestCase
{
    private object $fakeRouter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeRouter = new class implements FailureReportingContract {
            /** @var array<int, FailureRecord> */
            public array $reports = [];

            public function report(FailureRecord $record): FailureTransitionResult
            {
                $this->reports[] = $record;
                return new FailureTransitionResult(
                    nextState: FailureState::Resolved,
                    handlerClass: \App\Runtime\Failure\Handlers\CommandFailureHandler::class,
                    logLevel: 'WARNING',
                    userMessage: 'stubbed',
                    coalesce: false,
                    coalesceWindowSeconds: 0,
                );
            }
        };
        $this->app->instance(FailureReportingContract::class, $this->fakeRouter);
    }

    #[Test]
    public function the_command_prints_each_required_key_in_the_testing_environment(): void
    {
        $exitCode = Artisan::call('temple:env');

        $this->assertSame(0, $exitCode);
        $output = Artisan::output();

        // Required keys per Testing matrix should appear with [present] status.
        $this->assertStringContainsString('Testing', $output);
        $this->assertStringContainsString('APP_KEY', $output);
        $this->assertStringContainsString('[present]', $output);
        $this->assertStringContainsString('All required env keys are present', $output);
    }

    #[Test]
    public function the_command_does_not_report_a_failure_in_the_testing_environment(): void
    {
        Artisan::call('temple:env');

        $this->assertCount(0, $this->fakeRouter->reports);
    }

    #[Test]
    public function the_command_exits_non_zero_when_required_keys_missing_and_reports_to_router(): void
    {
        // Simulate a Production-like environment with missing keys.
        $envMock = $this->createMock(EnvironmentContract::class);
        $envMock->method('type')->willReturn(EnvironmentType::Production);
        $envMock->method('get')->willReturn(null);
        $this->app->instance(EnvironmentContract::class, $envMock);

        // Force EnvValidator rebuild against the mocked env.
        $this->app->forgetInstance(\App\Runtime\Validation\EnvValidator::class);
        $this->app->forgetInstance(EnvironmentListCommand::class);

        $exitCode = Artisan::call('temple:env');

        $this->assertSame(1, $exitCode);
        $this->assertCount(1, $this->fakeRouter->reports);
        $this->assertSame(
            \App\Runtime\Failure\Enums\FailureKind::CommandFailed,
            $this->fakeRouter->reports[0]->kind,
        );

        $output = Artisan::output();
        $this->assertStringContainsString('Production', $output);
        $this->assertStringContainsString('[MISSING]', $output);
    }

    #[Test]
    public function the_command_class_resolves_from_the_container(): void
    {
        $command = $this->app->make(EnvironmentListCommand::class);
        $this->assertInstanceOf(EnvironmentListCommand::class, $command);
    }
}