<?php

declare(strict_types=1);

namespace App\Queue\Providers;

use App\Queue\Console\Commands\QueueStatsCommand;
use App\Queue\Contracts\QueueConnectorContract;
use App\Queue\Infrastructure\LaravelQueueConnector;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Support\ServiceProvider;

/**
 * QueueServiceProvider — wires the Queue module's contract bindings.
 *
 * Single responsibility: bind QueueConnectorContract to the
 * Laravel-backed implementation. Every other queue surface (Bus,
 * queue:work, queue:failed) is configured by Laravel's built-in
 * QueueServiceProvider.
 *
 * Doctrine alignment:
 *   - Service layer owns business workflows. The connector is the
 *     gateway between service code and the queue substrate.
 *   - Domain boundaries explicit — Queue module owns queue-specific
 *     wiring; Runtime module owns generic kernel diagnostics;
 *     Payments module owns its own providers (Phase 1 territory).
 *   - Service code depends on QueueConnectorContract, NOT on
 *     Illuminate\Support\Facades\Queue. The facade is forbidden
 *     in domain code; tests can substitute a fake.
 */
final class QueueServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(QueueConnectorContract::class, function ($app) {
            /** @var QueueFactory $factory */
            $factory = $app->make(QueueFactory::class);

            return new LaravelQueueConnector($factory);
        });
    }

    public function boot(): void
    {
        // Register the queue stats command. Laravel's auto-discovery
        // only scans app/Console/Commands; the Queue module lives
        // outside that path, so we register explicitly here.
        if ($this->app->runningInConsole()) {
            $this->commands([
                QueueStatsCommand::class,
            ]);
        }
    }
}
