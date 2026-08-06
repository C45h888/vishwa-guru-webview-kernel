<?php

declare(strict_types=1);

namespace App\Campaigns\Providers;

use App\Campaigns\CampaignsModule;
use App\Campaigns\Contracts\CampaignAuthoringContract;
use App\Campaigns\Contracts\CampaignsQueryContract;
use App\Campaigns\Domain\Repositories\CampaignRepositoryContract;
use App\Campaigns\Infrastructure\Repositories\EloquentCampaignRepository;
use App\Campaigns\Services\CampaignAuthoringService;
use App\Campaigns\Services\CampaignCoverUploadService;
use App\Campaigns\Services\CampaignsQueryService;
use App\Persistence\Contracts\RepositoryRegistryContract;
use Illuminate\Support\ServiceProvider;

/**
 * CampaignsServiceProvider — DI wiring for the Campaigns kernel.
 *
 * The Campaigns kernel binds the repository contract to the
 * Eloquent-via-PersistenceAdapterContract implementation, wires the
 * public read contract to the CampaignsQueryService, and registers
 * the entity type against the RepositoryRegistry.
 *
 * Architectural invariants enforced here (mirrors PaymentsServiceProvider):
 *   - One binding per interface; the kernel owns its own bindings.
 *   - PersistenceAdapterContract is owned by PersistenceServiceProvider;
 *     this provider does not re-bind it.
 *   - No business logic in this file (all logic lives in Services/, the
 *     domain layer, and the infrastructure implementations).
 */
final class CampaignsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $app = $this->app;

        // ════════════════════════════════════════════════════════════════
        // AXIS A — Persistence adapter is owned by PersistenceServiceProvider.
        // We do NOT re-bind PersistenceAdapterContract here.
        // ════════════════════════════════════════════════════════════════

        // ════════════════════════════════════════════════════════════════
        // AXIS D — Repository interface → concrete binding
        // ════════════════════════════════════════════════════════════════
        $app->bind(
            CampaignRepositoryContract::class,
            EloquentCampaignRepository::class,
        );

        // ════════════════════════════════════════════════════════════════
        // AXIS G — Public read service + contract
        // ════════════════════════════════════════════════════════════════
        $app->singleton(CampaignsQueryService::class);
        $app->bind(
            CampaignsQueryContract::class,
            CampaignsQueryService::class,
        );

        // ════════════════════════════════════════════════════════════════
        // AXIS H — Phase 4: Admin Kernel authoring surface
        //   - CampaignAuthoringContract → CampaignAuthoringService
        //   - CampaignCoverUploadService (depends on FileAssetRepositoryContract,
        //     PersistenceAdapterContract — both resolved from the Payments +
        //     Persistence kernels respectively)
        // ════════════════════════════════════════════════════════════════
        $app->bind(
            CampaignAuthoringContract::class,
            CampaignAuthoringService::class,
        );
        $app->singleton(CampaignCoverUploadService::class);
    }

    public function boot(): void
    {
        // ════════════════════════════════════════════════════════════════
        // Repository registry entries — entity_type → repository class
        // ════════════════════════════════════════════════════════════════
        /** @var RepositoryRegistryContract $registry */
        $registry = $this->app->make(RepositoryRegistryContract::class);
        $registry->register('campaign', EloquentCampaignRepository::class);
    }

    /**
     * Declares every contract and concrete class this provider registers.
     * Required so the provider is visible to `php artisan` introspection
     * and the container optimizer.
     *
     * @return array<int, class-string>
     */
    public function provides(): array
    {
        return [
            // Repository contract → implementation
            CampaignRepositoryContract::class,
            // Service + public contract
            CampaignsQueryService::class,
            CampaignsQueryContract::class,
            // Phase 4: Admin Kernel authoring surface
            CampaignAuthoringContract::class,
            CampaignAuthoringService::class,
            CampaignCoverUploadService::class,
            // Module declaration (consumed by Shared's discovery)
            CampaignsModule::class,
            // Repository registry contract (we depend on it in boot())
            RepositoryRegistryContract::class,
        ];
    }
}
