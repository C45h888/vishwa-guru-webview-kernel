<?php

declare(strict_types=1);

namespace App\Seo\Providers;

use App\Seo\Contracts\SeoMetaContract;
use App\Seo\Services\SeoMetaBuilder;
use App\Seo\Services\SitemapBuilder;
use Illuminate\Support\ServiceProvider;

/**
 * SeoServiceProvider — DI wiring for the Seo kernel.
 *
 * The Seo kernel is a LEAF. It owns the public webview's discoverability
 * surface and nothing else: the per-page metadata payload, the XML
 * sitemap, and robots.txt. It has no repositories, no entities, and no
 * write path.
 *
 * Architectural invariants enforced here:
 *   - One binding per interface; the kernel owns its own bindings.
 *   - SeoMetaContract is bound with `bind`, NOT `singleton`. The builder
 *     depends on the current `Illuminate\Http\Request` to derive the
 *     canonical path, so a shared instance would leak one request's path
 *     into another under long-lived workers (Octane / queue drains).
 *   - SitemapBuilder is a singleton: it depends only on the three read
 *     contracts and a cache. It holds no request state.
 *   - No business logic in this file.
 */
final class SeoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $app = $this->app;

        // Request-scoped: the canonical path is derived per request.
        $app->bind(SeoMetaContract::class, SeoMetaBuilder::class);

        $app->singleton(SitemapBuilder::class);
    }

    /**
     * @return array<int, class-string>
     */
    public function provides(): array
    {
        return [
            SeoMetaContract::class,
            SeoMetaBuilder::class,
            SitemapBuilder::class,
        ];
    }
}
