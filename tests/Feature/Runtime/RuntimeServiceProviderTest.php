<?php

declare(strict_types=1);

namespace Tests\Feature\Runtime;

use App\Runtime\Console\Commands\EnvironmentListCommand;
use App\Runtime\Console\Commands\RuntimeStatusCommand;
use App\Runtime\Diagnostics\CacheHealthProbe;
use App\Runtime\Diagnostics\DatabaseHealthProbe;
use App\Runtime\Diagnostics\HealthCheckAggregator;
use App\Runtime\Diagnostics\HealthCheckResult;
use App\Runtime\Diagnostics\KernelSnapshotFactory;
use App\Runtime\Diagnostics\QueueHealthProbe;
use App\Runtime\Failure\Contracts\FailureReportingContract;
use App\Runtime\Failure\FailureRouter;
use App\Runtime\Failure\Handlers\BootFailureHandler;
use App\Runtime\Failure\Handlers\CommandFailureHandler;
use App\Runtime\Failure\Handlers\HttpFailureHandler;
use App\Runtime\Failure\Handlers\ProbeFailureHandler;
use App\Runtime\Failure\StateMachines\FailureStateMachine;
use App\Runtime\Http\Controllers\HealthController;
use App\Runtime\Http\Controllers\PingController;
use App\Runtime\Providers\RuntimeServiceProvider;
use App\Runtime\Validation\BootProbe;
use App\Runtime\Validation\EnvValidator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the RuntimeServiceProvider — verifies that every
 * service declared in `provides()` resolves, the provider's `boot()`
 * runs BootProbe::assert() exactly once, and the tagged probe pool
 * works end-to-end.
 */
final class RuntimeServiceProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        BootProbe::resetForTesting();
    }

    protected function tearDown(): void
    {
        BootProbe::resetForTesting();
        parent::tearDown();
    }

    #[Test]
    public function every_provided_service_resolves_from_the_container(): void
    {
        $provider = $this->app->make(RuntimeServiceProvider::class);
        $provided = $provider->provides();

        foreach ($provided as $serviceClass) {
            $this->assertInstanceOf(
                $serviceClass,
                $this->app->make($serviceClass),
                "{$serviceClass} should be resolvable from the container",
            );
        }
    }

    #[Test]
    public function boot_runs_the_boot_probe_during_application_startup(): void
    {
        // Application boots in setUp via Tests\TestCase::createApplication().
        // BootProbe::assert() runs in RuntimeServiceProvider::boot().
        $probe = $this->app->make(BootProbe::class);

        $this->assertTrue(
            $probe->alreadyRan(),
            'BootProbe::assert() should have been invoked during provider boot',
        );
    }

    #[Test]
    public function boot_probe_is_idempotent_across_container_resolutions(): void
    {
        $probe1 = $this->app->make(BootProbe::class);
        $probe2 = $this->app->make(BootProbe::class);
        $probe3 = $this->app->make(BootProbe::class);

        $this->assertTrue($probe1->alreadyRan());
        $this->assertTrue($probe2->alreadyRan());
        $this->assertTrue($probe3->alreadyRan());
    }

    #[Test]
    public function failure_reporting_contract_resolves_to_failure_router(): void
    {
        $contract = $this->app->make(FailureReportingContract::class);
        $router = $this->app->make(FailureRouter::class);

        $this->assertInstanceOf(FailureRouter::class, $contract);
        $this->assertSame($router, $contract);
    }

    #[Test]
    public function health_check_aggregator_iterates_all_tagged_probes(): void
    {
        $aggregator = $this->app->make(HealthCheckAggregator::class);

        $results = $aggregator->probe();

        $this->assertArrayHasKey('database', $results);
        $this->assertArrayHasKey('cache', $results);
        $this->assertArrayHasKey('queue', $results);
        $this->assertCount(3, $results);
        $this->assertTrue($aggregator->allHealthy($results));
    }

    #[Test]
    public function kernel_snapshot_factory_captures_runtime_facts(): void
    {
        $factory = $this->app->make(KernelSnapshotFactory::class);
        $snapshot = $factory->capture();

        $this->assertNotEmpty($snapshot->phpVersion);
        $this->assertSame('testing', $snapshot->environment->value);
        $this->assertSame('sqlite', $snapshot->dbDriver);
    }

    #[Test]
    public function failure_state_machine_is_singleton_bound(): void
    {
        $a = $this->app->make(FailureStateMachine::class);
        $b = $this->app->make(FailureStateMachine::class);

        $this->assertSame($a, $b, 'FailureStateMachine should be a singleton');
    }

    #[Test]
    public function env_validator_is_singleton_bound(): void
    {
        $a = $this->app->make(EnvValidator::class);
        $b = $this->app->make(EnvValidator::class);

        $this->assertSame($a, $b);
    }

    #[Test]
    public function all_phase_b_c_d_e_classes_resolve_from_the_container(): void
    {
        $expectedClasses = [
            EnvValidator::class,
            BootProbe::class,
            KernelSnapshotFactory::class,
            DatabaseHealthProbe::class,
            CacheHealthProbe::class,
            QueueHealthProbe::class,
            HealthCheckAggregator::class,
            FailureStateMachine::class,
            FailureRouter::class,
            FailureReportingContract::class,
            BootFailureHandler::class,
            ProbeFailureHandler::class,
            CommandFailureHandler::class,
            HttpFailureHandler::class,
            HealthController::class,
            PingController::class,
            RuntimeStatusCommand::class,
            EnvironmentListCommand::class,
        ];

        foreach ($expectedClasses as $class) {
            $this->assertInstanceOf($class, $this->app->make($class), "{$class} should resolve");
        }
    }
}