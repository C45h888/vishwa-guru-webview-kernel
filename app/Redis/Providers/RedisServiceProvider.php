<?php

declare(strict_types=1);

namespace App\Redis\Providers;

use App\Redis\Console\Commands\RedisInfoCommand;
use App\Redis\Contracts\RedisConnectorContract;
use App\Redis\Infrastructure\LaravelRedisConnector;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Support\ServiceProvider;

/**
 * RedisServiceProvider — wires the Redis module's contract bindings.
 *
 * Single responsibility: bind RedisConnectorContract to the Laravel-backed
 * implementation. Everything else (Cache::, Queue::, RateLimiter::) is
 * configured by Laravel's built-in providers and consumes this connection
 * via the underlying RedisManager.
 *
 * Doctrine alignment:
 *   - Service layer owns business workflows. The connector is the
 *     gateway between service code and the Redis substrate.
 *   - Domain boundaries explicit — the Redis module owns Redis-specific
 *     wiring; the Runtime module owns generic kernel diagnostics;
 *     the Payments module (Phase 1 closure) will own its own providers.
 *   - Service code depends on RedisConnectorContract, NOT on
 *     Illuminate\Support\Facades\Redis. The facade is forbidden in
 *     domain code; tests can substitute a fake.
 */
final class RedisServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RedisConnectorContract::class, function ($app) {
            /** @var RedisFactory $factory */
            $factory = $app->make(RedisFactory::class);

            $config = $app['config']->get('database.redis', []);

            return new LaravelRedisConnector(
                $factory,
                is_array($config) ? $config : [],
            );
        });
    }

    public function boot(): void
    {
        // 2026-10-03 fix: the scheduler runs `temple:redis:info` every
        // minute, but the command was never registered — every run errored
        // with "command not found" (thousands of occurrences in logs).
        if ($this->app->runningInConsole()) {
            $this->commands([
                RedisInfoCommand::class,
            ]);
        }
    }
}
