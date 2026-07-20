<?php

declare(strict_types=1);

namespace App\Cms\Infrastructure\Caching;

use App\Cms\Contracts\ResolvedPageCacheContract;
use App\Cms\Domain\ValueObjects\PageSlug;
use App\Cms\Infrastructure\Events\CmsDomainEvents;
use App\Persistence\ValueObjects\EntityId;
use Psr\Log\LoggerInterface;

/**
 * CacheInvalidationListener — listens to CMS kernel domain events and
 * invalidates the resolved-page cache so cached pages never serve
 * stale content after a state transition.
 *
 * Event → action mapping (per cms-architecture.md §5.3.3):
 *   STATIC_PAGE_PUBLISHED   → invalidate(slug)
 *   STATIC_PAGE_UPDATED     → invalidate(slug)
 *   STATIC_PAGE_ARCHIVED    → invalidate(slug)
 *   STATIC_PAGE_DELETED     → invalidate(slug)
 *   STATIC_PAGE_BODY_CHANGED → invalidate(slug)
 *   HOMEPAGE_CHANGED         → invalidate(oldSlug) + invalidate(newSlug)
 *   HERO_BANNER_CHANGED     → invalidateAll (every page attached to this banner)
 *   REFERENCE_ATTACHED      → invalidateAll (affects page assembly)
 *   REFERENCE_DETACHED      → invalidateAll
 *
 * Synchronous dispatch in V1. Async via queue is a Phase 5+ concern.
 *
 * @see /Users/kamii/Vishwaguru-webview-kernel/vishwa-guru-webview-kernel/cms-architecture.md §5.3.3
 */
final class CacheInvalidationListener
{
    public function __construct(
        private readonly ResolvedPageCacheContract $cache,
        private readonly \App\Cms\Domain\Repositories\StaticPageRepositoryContract $pages,
        private readonly \App\Cms\Domain\Repositories\HeroBannerRepositoryContract $banners,
        private readonly \App\Cms\Domain\Repositories\StaticPageReferenceRepositoryContract $references,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Single dispatch entry point. Laravel's event listener invokes
     * this with the event name as the first arg and payload as the
     * remaining args.
     *
     * Usage from the kernel's event() helper:
     *   event(CmsDomainEvents::STATIC_PAGE_PUBLISHED, [$slug->value()]);
     */
    public function handle(string $eventName, mixed ...$payload): void
    {
        try {
            match ($eventName) {
                CmsDomainEvents::STATIC_PAGE_PUBLISHED,
                CmsDomainEvents::STATIC_PAGE_UPDATED,
                CmsDomainEvents::STATIC_PAGE_ARCHIVED,
                CmsDomainEvents::STATIC_PAGE_DELETED,
                CmsDomainEvents::STATIC_PAGE_BODY_CHANGED => $this->invalidateSlug($payload[0] ?? null),

                CmsDomainEvents::HOMEPAGE_CHANGED => $this->invalidateHomepageChange($payload),

                CmsDomainEvents::HERO_BANNER_CHANGED => $this->invalidateBannerChange($payload[0] ?? null),

                CmsDomainEvents::REFERENCE_ATTACHED,
                CmsDomainEvents::REFERENCE_DETACHED => $this->cache->invalidateAll(),

                default => null,
            };
        } catch (\Throwable $e) {
            // Cache invalidation must not fail business operations. Log
            // and continue; stale cache will TTL out within 1 hour.
            if ($this->logger !== null) {
                $this->logger->error('CMS cache invalidation failed', [
                    'event' => $eventName,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function invalidateSlug(mixed $slugValue): void
    {
        if (! is_string($slugValue) || $slugValue === '') {
            return;
        }
        $this->cache->invalidate(new PageSlug($slugValue));
    }

    private function invalidateHomepageChange(array $payload): void
    {
        $oldSlug = $payload[0] ?? null;
        $newSlug = $payload[1] ?? null;
        if (is_string($oldSlug) && $oldSlug !== '') {
            $this->cache->invalidate(new PageSlug($oldSlug));
        }
        if (is_string($newSlug) && $newSlug !== '') {
            $this->cache->invalidate(new PageSlug($newSlug));
        }
    }

    private function invalidateBannerChange(mixed $bannerId): void
    {
        if (! is_string($bannerId) || $bannerId === '') {
            $this->cache->invalidateAll();

            return;
        }

        // Find all pages attached to this banner and invalidate each.
        // This is more targeted than invalidateAll but still bounded.
        $bannerEntityId = EntityId::fromString($bannerId);
        $pages = $this->pages->listAll(limit: 1000);
        foreach ($pages as $page) {
            $bannerSlots = $page->heroBannerSlots();
            foreach ($bannerSlots as $slot) {
                if ($slot->bannerId()->equals($bannerEntityId)) {
                    $this->cache->invalidate($page->slug());
                    break;
                }
            }
        }
    }
}