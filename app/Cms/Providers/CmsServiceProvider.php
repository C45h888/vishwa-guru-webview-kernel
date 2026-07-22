<?php

declare(strict_types=1);

namespace App\Cms\Providers;

use App\Cms\CmsModule;
use App\Cms\Domain\Entities\ContactInformation;
use App\Cms\Domain\Entities\HeroBanner;
use App\Cms\Domain\Entities\StaticPage;
use App\Cms\Domain\Entities\StaticPageReference;
use App\Cms\Domain\StateMachines\StaticPageStateMachine;
use App\Cms\Infrastructure\Caching\CacheInvalidationListener;
use App\Cms\Infrastructure\Caching\RedisResolvedPageCache;
use App\Cms\Infrastructure\Rendering\BlockRendererRegistry;
use App\Cms\Infrastructure\Rendering\BlockRenderers\CtaButtonBlockRenderer;
use App\Cms\Infrastructure\Rendering\BlockRenderers\DividerBlockRenderer;
use App\Cms\Infrastructure\Rendering\BlockRenderers\HeadingBlockRenderer;
use App\Cms\Infrastructure\Rendering\BlockRenderers\ImageBlockRenderer;
use App\Cms\Infrastructure\Rendering\BlockRenderers\ParagraphBlockRenderer;
use App\Cms\Infrastructure\Rendering\StaticPageBodyRenderer;
use App\Cms\Infrastructure\Repositories\EloquentContactInformationRepository;
use App\Cms\Infrastructure\Repositories\EloquentHeroBannerRepository;
use App\Cms\Infrastructure\Repositories\EloquentStaticPageReferenceRepository;
use App\Cms\Infrastructure\Repositories\EloquentStaticPageRepository;
use App\Cms\Infrastructure\Repositories\PublicMediaQuery;
use App\Cms\Contracts\ImageUrlResolverContract;
use App\Cms\Contracts\PublicMediaQueryContract;
use App\Cms\Contracts\ResolvedPageCacheContract;
use App\Cms\Contracts\StaticPageRendererContract;
use App\Cms\Domain\Repositories\ContactInformationRepositoryContract;
use App\Cms\Domain\Repositories\HeroBannerRepositoryContract;
use App\Cms\Domain\Repositories\StaticPageReferenceRepositoryContract;
use App\Cms\Domain\Repositories\StaticPageRepositoryContract;
use App\Cms\Infrastructure\Events\CmsDomainEvents;
use App\Cms\Services\ContactInformationService;
use App\Cms\Services\HeroBannerService;
use App\Cms\Services\ReferenceResolutionService;
use App\Cms\Services\PublicMediaPresentationService;
use App\Cms\Services\StaticPageQueryService;
use App\Cms\Services\StaticPageRendererService;
use App\Cms\Services\StaticPageService;
use App\Persistence\Contracts\RepositoryRegistryContract;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

/**
 * CmsServiceProvider — DI wiring for the CMS kernel.
 *
 * The CMS kernel binds every Domain contract to its Infrastructure
 * implementation, registers the state machine as a pure-function
 * singleton, wires the rendering pipeline (block renderer registry +
 * body renderer), binds the Redis-backed resolved-page cache, and
 * registers the cache invalidation listener for kernel-emitted domain
 * events.
 *
 * Architectural invariants enforced here (mirrors PaymentsServiceProvider):
 *   - One binding per interface; the kernel owns its own bindings.
 *   - PersistenceAdapterContract is owned by PersistenceServiceProvider;
 *     this provider does not re-bind it.
 *   - No business logic in this file (all logic lives in Services/, the
 *     domain layer, and the infrastructure implementations).
 *   - BlockRendererContract is a marker import — the registry itself
 *     is the binding surface for the per-block renderers; the marker
 *     forces the contract class to be loaded so the registry can
 *     register implementations against it.
 *
 * @see /Users/kamii/Vishwaguru-webview-kernel/vishwa-guru-webview-kernel/cms-architecture.md §6
 */
final class CmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $app = $this->app;

        // ════════════════════════════════════════════════════════════════
        // AXIS C — State machines (pure-function singletons)
        // ════════════════════════════════════════════════════════════════
        $app->singleton(StaticPageStateMachine::class);

        // ════════════════════════════════════════════════════════════════
        // AXIS A — Persistence adapter is owned by PersistenceServiceProvider.
        // We do NOT re-bind PersistenceAdapterContract here.
        // Doctrine: one binding per interface; kernel-level wiring
        // lives in the kernel provider, not in domain providers.
        // ════════════════════════════════════════════════════════════════

        // ════════════════════════════════════════════════════════════════
        // AXIS D — Repository interface → concrete bindings
        // ════════════════════════════════════════════════════════════════
        $repoBindings = [
            StaticPageRepositoryContract::class          => EloquentStaticPageRepository::class,
            HeroBannerRepositoryContract::class          => EloquentHeroBannerRepository::class,
            StaticPageReferenceRepositoryContract::class => EloquentStaticPageReferenceRepository::class,
            ContactInformationRepositoryContract::class  => EloquentContactInformationRepository::class,
        ];
        foreach ($repoBindings as $contract => $impl) {
            $app->bind($contract, $impl);
        }

        // ════════════════════════════════════════════════════════════════
        // AXIS B — Rendering pipeline
        // ════════════════════════════════════════════════════════════════
        $app->singleton(StaticPageBodyRenderer::class);
        $app->singleton(BlockRendererRegistry::class, static function (Container $app): BlockRendererRegistry {
            $registry = new BlockRendererRegistry();
            $registry->register(new ParagraphBlockRenderer());
            $registry->register(new HeadingBlockRenderer());
            $registry->register(new ImageBlockRenderer($app->make(ImageUrlResolverContract::class)));
            $registry->register(new CtaButtonBlockRenderer());
            $registry->register(new DividerBlockRenderer());

            return $registry;
        });

        // ════════════════════════════════════════════════════════════════
        // AXIS E — Cache
        // ════════════════════════════════════════════════════════════════
        $app->singleton(ResolvedPageCacheContract::class, RedisResolvedPageCache::class);

        // ════════════════════════════════════════════════════════════════
        // AXIS F — Image URL resolver stub (Phase 4 replaces with real
        // file storage; V1 returns the file id as a placeholder URL).
        // ════════════════════════════════════════════════════════════════
        $app->bind(PublicMediaQueryContract::class, PublicMediaQuery::class);
        $app->bind(ImageUrlResolverContract::class, static fn (): ImageUrlResolverContract =>
            new \App\Cms\Infrastructure\UrlResolution\PublicMediaUrlResolver());

        // ════════════════════════════════════════════════════════════════
        // AXIS G — Services (auto-resolved via constructor injection)
        // ════════════════════════════════════════════════════════════════
        $app->singleton(StaticPageService::class);
        $app->singleton(StaticPageQueryService::class);
        $app->singleton(StaticPageRendererService::class);
        $app->bind(StaticPageRendererContract::class, StaticPageRendererService::class);
        $app->singleton(HeroBannerService::class);
        $app->singleton(ContactInformationService::class);
        $app->singleton(ReferenceResolutionService::class);
        $app->singleton(PublicMediaPresentationService::class);
    }

    public function boot(): void
    {
        // ════════════════════════════════════════════════════════════════
        // Repository registry entries — entity_type → repository class
        // ════════════════════════════════════════════════════════════════
        $registry = $this->app->make(RepositoryRegistryContract::class);
        $registry->register(StaticPage::ENTITY_TYPE,        EloquentStaticPageRepository::class);
        $registry->register(HeroBanner::ENTITY_TYPE,        EloquentHeroBannerRepository::class);
        $registry->register(StaticPageReference::ENTITY_TYPE, EloquentStaticPageReferenceRepository::class);
        $registry->register(ContactInformation::ENTITY_TYPE, EloquentContactInformationRepository::class);

        // ════════════════════════════════════════════════════════════════
        // Cache invalidation listeners — subscribe to kernel-emitted
        // domain events so the resolved-page cache stays consistent with
        // authoritative state.
        // ════════════════════════════════════════════════════════════════
        $events = $this->app->make('events');
        $listener = $this->app->make(CacheInvalidationListener::class);

        foreach ([
            CmsDomainEvents::STATIC_PAGE_PUBLISHED,
            CmsDomainEvents::STATIC_PAGE_UPDATED,
            CmsDomainEvents::STATIC_PAGE_ARCHIVED,
            CmsDomainEvents::STATIC_PAGE_DELETED,
            CmsDomainEvents::STATIC_PAGE_BODY_CHANGED,
            CmsDomainEvents::HOMEPAGE_CHANGED,
            CmsDomainEvents::HERO_BANNER_CHANGED,
            CmsDomainEvents::REFERENCE_ATTACHED,
            CmsDomainEvents::REFERENCE_DETACHED,
        ] as $eventName) {
            $events->listen($eventName, [$listener, 'handle']);
        }
    }

    /**
     * Declares every contract and concrete class this provider registers.
     * Required so the provider is visible to `php artisan` introspection
     * and the container optimizer. Without this, deferred loading fails
     * silently.
     *
     * @return array<int, class-string>
     */
    public function provides(): array
    {
        return [
            // State machines
            StaticPageStateMachine::class,

            // Repository contracts → implementations
            StaticPageRepositoryContract::class,
            HeroBannerRepositoryContract::class,
            StaticPageReferenceRepositoryContract::class,
            ContactInformationRepositoryContract::class,

            // Rendering pipeline
            StaticPageBodyRenderer::class,
            BlockRendererRegistry::class,
            ImageUrlResolverContract::class,
            PublicMediaQueryContract::class,

            // Cache
            ResolvedPageCacheContract::class,
            CacheInvalidationListener::class,

            // Services
            StaticPageService::class,
            StaticPageQueryService::class,
            StaticPageRendererService::class,
            StaticPageRendererContract::class,
            HeroBannerService::class,
            ContactInformationService::class,
            ReferenceResolutionService::class,

            // Repository registry contract (we depend on it in boot())
            RepositoryRegistryContract::class,

            // Module declaration (consumed by Shared's discovery)
            CmsModule::class,
        ];
    }
}