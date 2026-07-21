<?php

declare(strict_types=1);

namespace App\Cms\Services;

use App\Cms\Contracts\ResolvedPageCacheContract;
use App\Cms\Contracts\StaticPageRendererContract;
use App\Cms\Domain\DTOs\RenderedStaticPage;
use App\Cms\Domain\DTOs\ResolvedReference;
use App\Cms\Domain\Entities\StaticPage;
use App\Cms\Domain\Enums\PageReferenceType;
use App\Cms\Domain\Repositories\HeroBannerRepositoryContract;
use App\Cms\Domain\Repositories\StaticPageReferenceRepositoryContract;
use App\Cms\Domain\Repositories\StaticPageRepositoryContract;
use App\Cms\Domain\ValueObjects\PageSlug;
use App\Persistence\ValueObjects\EntityId;
use App\Shared\Support\Clock;
use DateTimeImmutable;

/**
 * StaticPageRendererService — the public-content orchestrator.
 *
 * Phase 3 frontend calls renderBySlug / renderHomepage on every public
 * page load. The service:
 *   1. Checks the resolved-page cache (Redis via ResolvedPageCacheContract)
 *   2. On miss: loads the page, hero banners, and references
 *   3. Resolves cross-kernel references (CAMPAIGN via CampaignQueryContract)
 *   4. Assembles a RenderedStaticPage DTO with the entity's pre-rendered body_html
 *   5. Caches the result for subsequent requests
 *
 * The body_html is NOT re-rendered here — the StaticPageService writes
 * it on every publish, and the entity carries the pre-rendered HTML.
 *
 * In-process mutex (per §5.7) protects against cache stampede within
 * a single PHP worker. Multi-worker stampede is mitigated by the
 * shared Redis cache.
 *
 * @see /Users/kamii/Vishwaguru-webview-kernel/vishwa-guru-webview-kernel/cms-architecture.md §4.3
 */
final class StaticPageRendererService implements StaticPageRendererContract
{
    /**
     * Per-process memoization of in-flight resolutions (cache stampede mitigation).
     * @var array<string, RenderedStaticPage>
     */
    private array $resolving = [];

    public function __construct(
        private readonly StaticPageRepositoryContract $pages,
        private readonly HeroBannerRepositoryContract $banners,
        private readonly StaticPageReferenceRepositoryContract $references,
        private readonly ReferenceResolutionService $resolutionService,
        private readonly ResolvedPageCacheContract $cache,
        private readonly Clock $clock,
    ) {
    }

    public function renderBySlug(PageSlug $slug): ?RenderedStaticPage
    {
        // 1. Cache hit
        $cached = $this->cache->get($slug);
        if ($cached !== null) {
            return $cached;
        }

        // 2. Per-process memoization: a sibling request in this process
        //    may already be resolving the same slug
        if (isset($this->resolving[$slug->value()])) {
            return $this->resolving[$slug->value()];
        }

        // 3. Compute
        $page = $this->pages->findBySlug($slug);
        if ($page === null) {
            return null;
        }
        if (! $page->state()->isPubliclyReadable()) {
            return null;
        }

        $resolved = $this->assemble($page);
        $this->resolving[$slug->value()] = $resolved;
        $this->cache->put($slug, $resolved);
        unset($this->resolving[$slug->value()]);

        return $resolved;
    }

    public function renderHomepage(): RenderedStaticPage
    {
        $page = $this->pages->findHomepage();
        if ($page === null) {
            throw \App\Cms\Domain\Exceptions\StaticPageNotFoundException::bySlug('homepage');
        }
        if (! $page->state()->isPubliclyReadable()) {
            throw \App\Cms\Domain\Exceptions\StaticPageNotFoundException::bySlug('homepage');
        }

        $slug = $page->slug();
        $cached = $this->cache->get($slug);
        if ($cached !== null) {
            return $cached;
        }

        return $this->assemble($page);
    }

    private function assemble(StaticPage $page): RenderedStaticPage
    {
        $heroBanners = $this->banners->listForPage($page->id());
        $references = $this->references->listForPage($page->id());
        $resolved = $this->resolutionService->resolveMany($references);

        return new RenderedStaticPage(
            page: $page,
            heroBanners: $heroBanners,
            resolvedReferences: $resolved,
            html: $page->bodyHtml() ?? '',
            resolvedAt: $this->clock->now(),
        );
    }
}