<?php

declare(strict_types=1);

namespace App\Shared\Providers;

use App\Shared\Configuration\ConfigurationRegistry;
use App\Shared\Configuration\LaravelConfiguration;
use App\Shared\Contracts\ConfigurationContract;
use App\Shared\Contracts\EnvironmentContract;
use App\Shared\Environment\LaravelEnvironment;
use App\Shared\Support\Clock;
use App\Shared\Support\IdentifierGenerator;
use App\Shared\Support\SystemClock;
use App\Shared\Support\UlidGenerator;
use Illuminate\Support\ServiceProvider;

class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Core abstractions
        $this->app->singleton(ConfigurationContract::class, LaravelConfiguration::class);
        $this->app->singleton(EnvironmentContract::class, LaravelEnvironment::class);

        // Support utilities
        $this->app->singleton(Clock::class, SystemClock::class);
        $this->app->singleton(IdentifierGenerator::class, UlidGenerator::class);

        // Configuration registry
        $this->app->singleton(ConfigurationRegistry::class);
    }

    public function boot(): void
    {
        //
    }

    public function provides(): array
    {
        return [
            ConfigurationContract::class,
            EnvironmentContract::class,
            Clock::class,
            IdentifierGenerator::class,
            ConfigurationRegistry::class,
        ];
    }
}
