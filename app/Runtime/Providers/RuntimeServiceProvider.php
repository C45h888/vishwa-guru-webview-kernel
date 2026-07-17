<?php

declare(strict_types=1);

namespace App\Runtime\Providers;

use App\Runtime\Console\Commands\EnvironmentListCommand;
use App\Runtime\Console\Commands\RuntimeStatusCommand;
use App\Runtime\Diagnostics\CacheHealthProbe;
use App\Runtime\Diagnostics\DatabaseHealthProbe;
use App\Runtime\Diagnostics\HealthCheckAggregator;
use App\Runtime\Diagnostics\HealthProbe;
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
use App\Runtime\Validation\BootProbe;
use App\Runtime\Validation\EnvValidator;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Application;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

/**
 * RuntimeServiceProvider — central wiring for the runtime kernel.
 *
 * Single responsibility: bind every service in the App\Runtime\* tree.
 *
 * Architectural invariants:
 *   - Persistence contracts (PersistenceAdapterContract, RepositoryRegistryContract)
 *     are owned by PersistenceServiceProvider, NOT this one.
 *   - Shared contracts (ConfigurationContract, EnvironmentContract, Clock, etc.)
 *     are owned by SharedServiceProvider.
 *   - This provider only owns Runtime-* services and their dependencies.
 *   - HealthProbe implementations are TAGGED 'runtime.health_probe' so the
 *     HealthCheckAggregator can iterate them.
 *   - FailureReportingContract → FailureRouter is the only public entry
 *     point for failures.
 *   - boot() runs BootProbe::assert() guarded by BootProbe::$alreadyRan so
 *     long-running workers don't re-validate on every container resolution.
 */
final class RuntimeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $app = $this->app;

        // ════════════════════════════════════════════════════════════════
        // Validation — fail-fast env key + boot probe
        // ════════════════════════════════════════════════════════════════
        $app->singleton(EnvValidator::class);
        $app->singleton(BootProbe::class);

        // ════════════════════════════════════════════════════════════════
        // Diagnostics — runtime snapshot + health probes + aggregator
        // ════════════════════════════════════════════════════════════════
        $app->singleton(KernelSnapshotFactory::class, function ($app) {
            return new KernelSnapshotFactory(
                $app->make(\App\Shared\Contracts\ConfigurationContract::class),
                $app->make(\App\Shared\Contracts\EnvironmentContract::class),
                DB::connection(),
                Cache::store(),
                Queue::connection()->getQueueManager() ?? $app->make(QueueManager::class),
                $app->make(Application::class),
            );
        });

        $app->bind(DatabaseHealthProbe::class);
        $app->bind(CacheHealthProbe::class);
        $app->bind(QueueHealthProbe::class);

        // Tag the concrete probe classes for the aggregator's iterable dependency.
        $app->tag(
            [DatabaseHealthProbe::class, CacheHealthProbe::class, QueueHealthProbe::class],
            'runtime.health_probe',
        );

        $app->singleton(HealthCheckAggregator::class, function ($app) {
            return new HealthCheckAggregator($app);
        });

        // ════════════════════════════════════════════════════════════════
        // Failure — the doctrine-driven routing membrane
        // ════════════════════════════════════════════════════════════════
        $app->singleton(FailureStateMachine::class);

        $app->singleton(FailureRouter::class, function ($app) {
            return new FailureRouter(
                $app->make(FailureStateMachine::class),
                $app->make(\App\Shared\Support\Clock::class),
                $app,
            );
        });

        // Alias the contract to the router — the only public entry point.
        $app->alias(FailureRouter::class, FailureReportingContract::class);

        $app->bind(BootFailureHandler::class);
        $app->bind(ProbeFailureHandler::class);
        $app->bind(CommandFailureHandler::class);
        $app->bind(HttpFailureHandler::class);

        // ════════════════════════════════════════════════════════════════
        // HTTP — controllers (transient; one per request)
        // ════════════════════════════════════════════════════════════════
        $app->bind(HealthController::class);
        $app->bind(PingController::class);

        // ════════════════════════════════════════════════════════════════
        // Console — artisan commands
        // ════════════════════════════════════════════════════════════════
        $app->bind(RuntimeStatusCommand::class);
        $app->bind(EnvironmentListCommand::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                RuntimeStatusCommand::class,
                EnvironmentListCommand::class,
            ]);
        }
    }

    public function boot(): void
    {
        // Fail-fast env validation. Guarded by BootProbe::$alreadyRan so
        // long-running PHP-FPM workers don't re-validate on every request.
        // The probe throws EnvironmentValidationException on failure;
        // Laravel's default exception handler renders it.
        $this->app->make(BootProbe::class)->assert();
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            // Validation
            EnvValidator::class,
            BootProbe::class,
            // Diagnostics
            KernelSnapshotFactory::class,
            DatabaseHealthProbe::class,
            CacheHealthProbe::class,
            QueueHealthProbe::class,
            HealthCheckAggregator::class,
            // Failure
            FailureStateMachine::class,
            FailureRouter::class,
            FailureReportingContract::class,
            BootFailureHandler::class,
            ProbeFailureHandler::class,
            CommandFailureHandler::class,
            HttpFailureHandler::class,
            // HTTP
            HealthController::class,
            PingController::class,
            // Console
            RuntimeStatusCommand::class,
            EnvironmentListCommand::class,
        ];
    }
}