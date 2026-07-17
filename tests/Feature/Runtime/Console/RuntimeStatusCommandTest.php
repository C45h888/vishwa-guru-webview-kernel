<?php

declare(strict_types=1);

namespace Tests\Feature\Runtime\Console;

use App\Runtime\Console\Commands\RuntimeStatusCommand;
use App\Runtime\Diagnostics\CacheHealthProbe;
use App\Runtime\Diagnostics\DatabaseHealthProbe;
use App\Runtime\Diagnostics\HealthCheckAggregator;
use App\Runtime\Diagnostics\HealthCheckResult;
use App\Runtime\Diagnostics\KernelSnapshotFactory;
use App\Runtime\Diagnostics\QueueHealthProbe;
use App\Runtime\Failure\Contracts\FailureReportingContract;
use App\Runtime\Failure\StateMachines\FailureTransitionResult;
use App\Runtime\Failure\ValueObjects\FailureRecord;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Feature tests for `php artisan temple:runtime`.
 */
final class RuntimeStatusCommandTest extends TestCase
{
    private object $fakeRouter;

    protected function setUp(): void
    {
        parent::setUp();

        // Fake FailureReportingContract — Phase E's contract, stubbed here so
        // the command can resolve. Records every report() invocation.
        $this->fakeRouter = new class implements FailureReportingContract {
            /** @var array<int, FailureRecord> */
            public array $reports = [];

            public function report(FailureRecord $record): FailureTransitionResult
            {
                $this->reports[] = $record;
                return new FailureTransitionResult(
                    nextState: \App\Runtime\Failure\Enums\FailureState::Resolved,
                    handlerClass: \App\Runtime\Failure\Handlers\CommandFailureHandler::class,
                    logLevel: 'WARNING',
                    userMessage: 'stubbed',
                    coalesce: false,
                    coalesceWindowSeconds: 0,
                );
            }
        };
        $this->app->instance(FailureReportingContract::class, $this->fakeRouter);

        // Wire probes (Phase F does this in the provider).
        $this->app->tag(
            [DatabaseHealthProbe::class, CacheHealthProbe::class, QueueHealthProbe::class],
            'runtime.health_probe',
        );
        $this->app->bind(KernelSnapshotFactory::class, function ($app) {
            return new KernelSnapshotFactory(
                $app->make(\App\Shared\Contracts\ConfigurationContract::class),
                $app->make(\App\Shared\Contracts\EnvironmentContract::class),
                DB::connection(),
                Cache::store(),
                Queue::connection()->getQueueManager() ?? app(\Illuminate\Queue\QueueManager::class),
                $app->make(Application::class),
            );
        });
        $this->app->forgetInstance(HealthCheckAggregator::class);
    }

    #[Test]
    public function the_command_prints_a_table_with_all_subsystems_ok(): void
    {
        $exitCode = Artisan::call('temple:runtime');

        $this->assertSame(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('Database', $output);
        $this->assertStringContainsString('Cache', $output);
        $this->assertStringContainsString('Queue', $output);
        $this->assertStringContainsString('PHP', $output);
        $this->assertStringContainsString('Laravel', $output);
        $this->assertStringContainsString('Env', $output);
    }

    #[Test]
    public function the_command_does_not_report_a_failure_when_all_probes_healthy(): void
    {
        Artisan::call('temple:runtime');

        $this->assertCount(0, $this->fakeRouter->reports);
    }

    #[Test]
    public function the_command_exits_non_zero_when_a_probe_fails_and_reports_to_router(): void
    {
        // Replace DatabaseHealthProbe with a stub that returns fail().
        $failingProbe = new class implements \App\Runtime\Diagnostics\HealthProbe {
            public function name(): string { return 'database'; }
            public function probe(): HealthCheckResult
            {
                return HealthCheckResult::fail('database', 5.0, 'connection refused');
            }
        };
        $this->app->instance(DatabaseHealthProbe::class, $failingProbe);
        $this->app->forgetInstance(HealthCheckAggregator::class);

        $exitCode = Artisan::call('temple:runtime');

        $this->assertSame(1, $exitCode);
        $this->assertCount(1, $this->fakeRouter->reports);
        $this->assertSame(
            \App\Runtime\Failure\Enums\FailureKind::CommandFailed,
            $this->fakeRouter->reports[0]->kind,
        );
    }

    #[Test]
    public function the_command_class_resolves_from_the_container(): void
    {
        $command = $this->app->make(RuntimeStatusCommand::class);
        $this->assertInstanceOf(RuntimeStatusCommand::class, $command);
    }
}