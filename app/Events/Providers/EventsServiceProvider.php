<?php

declare(strict_types=1);

namespace App\Events\Providers;

use App\Events\Contracts\EventAuthoringContract;
use App\Events\Contracts\EventsQueryContract;
use App\Events\Domain\Repositories\EventRepositoryContract;
use App\Events\EventsModule;
use App\Events\Infrastructure\Repositories\EloquentEventRepository;
use App\Events\Services\EventAuthoringService;
use App\Events\Services\EventBannerUploadService;
use App\Events\Services\EventsQueryService;
use App\Persistence\Contracts\RepositoryRegistryContract;
use Illuminate\Support\ServiceProvider;

/**
 * EventsServiceProvider — DI wiring for the Events kernel.
 *
 * The Events kernel binds the repository contract to the
 * Eloquent-via-PersistenceAdapterContract implementation, wires the
 * public read contract to the EventsQueryService, and registers the
 * entity type against the RepositoryRegistry.
 *
 * Architectural invariants enforced here (mirrors PaymentsServiceProvider):
 *   - One binding per interface; the kernel owns its own bindings.
 *   - PersistenceAdapterContract is owned by PersistenceServiceProvider;
 *     this provider does not re-bind it.
 *   - No business logic in this file.
 */
final class EventsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $app = $this->app;

        // ════════════════════════════════════════════════════════════════
        // AXIS D — Repository interface → concrete binding
        // ════════════════════════════════════════════════════════════════
        $app->bind(
            EventRepositoryContract::class,
            EloquentEventRepository::class,
        );

        // ════════════════════════════════════════════════════════════════
        // AXIS G — Public read service + contract
        // ════════════════════════════════════════════════════════════════
        $app->singleton(EventsQueryService::class);
        $app->bind(
            EventsQueryContract::class,
            EventsQueryService::class,
        );

        // ════════════════════════════════════════════════════════════════
        // AXIS H — Phase 4: Admin Kernel authoring surface
        //   - EventAuthoringContract → EventAuthoringService
        //   - EventBannerUploadService (depends on FileAssetRepositoryContract,
        //     which is resolved from the Payments kernel)
        // ════════════════════════════════════════════════════════════════
        $app->bind(
            EventAuthoringContract::class,
            EventAuthoringService::class,
        );
        $app->singleton(EventBannerUploadService::class);
    }

    public function boot(): void
    {
        // ════════════════════════════════════════════════════════════════
        // Repository registry entry
        // ════════════════════════════════════════════════════════════════
        /** @var RepositoryRegistryContract $registry */
        $registry = $this->app->make(RepositoryRegistryContract::class);
        $registry->register('event', EloquentEventRepository::class);
    }

    /**
     * @return array<int, class-string>
     */
    public function provides(): array
    {
        return [
            // Repository contract → implementation
            EventRepositoryContract::class,
            // Service + public contract
            EventsQueryService::class,
            EventsQueryContract::class,
            // Phase 4: Admin Kernel authoring surface
            EventAuthoringContract::class,
            EventAuthoringService::class,
            EventBannerUploadService::class,
            // Module declaration (consumed by Shared's discovery)
            EventsModule::class,
            // Repository registry contract (we depend on it in boot())
            RepositoryRegistryContract::class,
        ];
    }
}
