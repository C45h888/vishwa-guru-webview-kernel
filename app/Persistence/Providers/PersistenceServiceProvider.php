<?php

declare(strict_types=1);

namespace App\Persistence\Providers;

use App\Persistence\Contracts\PersistenceAdapterContract;
use App\Persistence\Contracts\RepositoryRegistryContract;
use App\Persistence\Infrastructure\LaravelDbAdapter;
use App\Persistence\Infrastructure\RepositoryRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * PersistenceServiceProvider — DI wiring for the kernel-level persistence layer.
 *
 * Single responsibility: bind the two contracts every persistence-touching
 * domain depends on. Domain providers (Payments, future Donations) register
 * their entity repositories against the registry in their own boot() phase.
 *
 * Architectural invariants enforced here:
 *   - One binding per interface; concrete classes are private to the
 *     container unless explicitly used elsewhere.
 *   - The adapter depends only on the framework-provided ConnectionInterface;
 *     no DB:: facade, no Eloquent, no SDK leakage.
 *   - The registry is a singleton map (entity_type → repository class string);
 *     it is intentionally NOT coupled to a database backend.
 */
final class PersistenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // AXIS A — Persistence adapter.
        // Laravel auto-resolves Illuminate\Database\ConnectionInterface from
        // DB::connection() (the default connection). Tests override via
        // phpunit.xml (DB_CONNECTION=sqlite + DB_DATABASE=:memory:); production
        // uses DB_CONNECTION=pgsql or DB_CONNECTION=neon per config/database.php.
        $this->app->singleton(PersistenceAdapterContract::class, LaravelDbAdapter::class);

        // AXIS A — Repository registry.
        // Phase 0.25 contract; no implementation class existed before Phase 2.
        // Phase 1 PaymentsServiceProvider::boot() depends on this being
        // resolvable — see [phase-1-deviations.md] note about the latent defect.
        $this->app->singleton(RepositoryRegistryContract::class, RepositoryRegistry::class);
    }

    public function boot(): void
    {
        // Registry population is the responsibility of module providers
        // (e.g. PaymentsServiceProvider::boot()). Persistence owns the
        // contract and the empty registry; modules own their entries.
    }

    /**
     * @return array<int, class-string>
     */
    public function provides(): array
    {
        return [
            PersistenceAdapterContract::class,
            RepositoryRegistryContract::class,
        ];
    }
}