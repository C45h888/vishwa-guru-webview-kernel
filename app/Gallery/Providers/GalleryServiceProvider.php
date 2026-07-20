<?php

declare(strict_types=1);

namespace App\Gallery\Providers;

use App\Gallery\Contracts\GalleryQueryContract;
use App\Gallery\Domain\Repositories\GalleryImageRepositoryContract;
use App\Gallery\Domain\Repositories\GalleryRepositoryContract;
use App\Gallery\GalleryModule;
use App\Gallery\Infrastructure\Repositories\EloquentGalleryImageRepository;
use App\Gallery\Infrastructure\Repositories\EloquentGalleryRepository;
use App\Gallery\Services\GalleryQueryService;
use App\Persistence\Contracts\RepositoryRegistryContract;
use Illuminate\Support\ServiceProvider;

/**
 * GalleryServiceProvider — DI wiring for the Gallery kernel.
 *
 * The Gallery kernel binds two repository contracts (galleries +
 * gallery_images) to their Eloquent-via-PersistenceAdapterContract
 * implementations, wires the public read contract to the
 * GalleryQueryService, and registers both entity types against the
 * RepositoryRegistry.
 *
 * Architectural invariants enforced here (mirrors CmsServiceProvider):
 *   - One binding per interface; the kernel owns its own bindings.
 *   - PersistenceAdapterContract is owned by PersistenceServiceProvider;
 *     this provider does not re-bind it.
 *   - No business logic in this file.
 */
final class GalleryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $app = $this->app;

        // ════════════════════════════════════════════════════════════════
        // AXIS D — Repository interface → concrete bindings
        // ════════════════════════════════════════════════════════════════
        $repoBindings = [
            GalleryRepositoryContract::class      => EloquentGalleryRepository::class,
            GalleryImageRepositoryContract::class => EloquentGalleryImageRepository::class,
        ];
        foreach ($repoBindings as $contract => $impl) {
            $app->bind($contract, $impl);
        }

        // ════════════════════════════════════════════════════════════════
        // AXIS G — Public read service + contract
        // ════════════════════════════════════════════════════════════════
        $app->singleton(GalleryQueryService::class);
        $app->bind(
            GalleryQueryContract::class,
            GalleryQueryService::class,
        );
    }

    public function boot(): void
    {
        // ════════════════════════════════════════════════════════════════
        // Repository registry entries
        // ════════════════════════════════════════════════════════════════
        /** @var RepositoryRegistryContract $registry */
        $registry = $this->app->make(RepositoryRegistryContract::class);
        $registry->register('gallery',       EloquentGalleryRepository::class);
        $registry->register('gallery_image', EloquentGalleryImageRepository::class);
    }

    /**
     * @return array<int, class-string>
     */
    public function provides(): array
    {
        return [
            // Repository contracts → implementations
            GalleryRepositoryContract::class,
            GalleryImageRepositoryContract::class,
            // Service + public contract
            GalleryQueryService::class,
            GalleryQueryContract::class,
            // Module declaration (consumed by Shared's discovery)
            GalleryModule::class,
            // Repository registry contract (we depend on it in boot())
            RepositoryRegistryContract::class,
        ];
    }
}
