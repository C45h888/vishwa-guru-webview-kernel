<?php

declare(strict_types=1);

namespace App\Cms\Contracts;

use App\Cms\Domain\DTOs\RenderedStaticPage;
use App\Cms\Domain\ValueObjects\PageSlug;

/**
 * Contract for the resolved-page cache.
 *
 * Backed by Redis in production (RedisResolvedPageCache). The cache
 * is invalidated by CacheInvalidationListener on every state transition.
 *
 * @see /Users/kamii/Vishwaguru-webview-kernel/vishwa-guru-webview-kernel/cms-architecture.md §5.3.1
 */
interface ResolvedPageCacheContract
{
    public function get(PageSlug $slug): ?RenderedStaticPage;

    public function put(PageSlug $slug, RenderedStaticPage $page, int $ttlSeconds = 3600): void;

    public function invalidate(PageSlug $slug): void;

    public function invalidateAll(): void;
}