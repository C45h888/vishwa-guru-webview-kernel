<?php

declare(strict_types=1);

namespace App\Seo\Services;

use App\Campaigns\Contracts\CampaignsQueryContract;
use App\Cms\Contracts\StaticPageRendererContract;
use App\Cms\Domain\ValueObjects\PageSlug;
use App\Events\Contracts\EventsQueryContract;
use App\Gallery\Contracts\GalleryQueryContract;
use Illuminate\Support\Facades\Cache;

/**
 * Builds the public sitemap.xml URL set.
 *
 * Backend-first: every URL is derived from the kernels' own read-side
 * contracts (`listDisplayable` / `listUpcoming` / `listPast`), so an
 * entity that isn't publicly displayable can never leak into the
 * sitemap. Static routes resolve by route NAME via `route()` — the
 * application's own routing table is the single source of truth for
 * paths, never a hardcoded string.
 *
 * XML rendering lives here (not in a Blade view) because
 * `resources/views/app.blade.php` is the runtime's only Blade template
 * by architectural doctrine — it exists solely as the Inertia root
 * shell. Sitemap XML is a pure function of the URL set (`toXml()`),
 * which keeps it unit-testable without HTTP.
 */
final class SitemapBuilder
{
    public const CACHE_KEY = 'seo:sitemap:v1';

    public const CACHE_TTL_HOURS = 6;

    /**
     * CMS whitelist — mirrors the `{slug}` constraint in routes/web.php.
     * Only these content-only pages exist as dedicated public URLs
     * (/about and /legal carry their own routes).
     */
    private const CMS_PAGE_SLUGS = ['privacy', 'terms', 'trustee', 'mission', 'policies'];

    private const PAGE_SIZE = 100;

    public function __construct(
        private CampaignsQueryContract $campaigns,
        private EventsQueryContract $events,
        private GalleryQueryContract $galleries,
        private ?StaticPageRendererContract $pages = null,
    ) {
    }

    /**
     * @return list<SitemapUrl>
     */
    public function build(): array
    {
        $urls = [
            new SitemapUrl(route('home'), null, 'daily', '1.0'),
            new SitemapUrl(route('campaigns.index'), null, 'daily', '0.8'),
            new SitemapUrl(route('events.index'), null, 'daily', '0.8'),
            new SitemapUrl(route('gallery.index'), null, 'daily', '0.8'),
            new SitemapUrl(route('cms.about'), null, 'monthly', '0.7'),
            new SitemapUrl(route('cms.contact'), null, 'monthly', '0.5'),
            new SitemapUrl(route('cms.legal'), null, 'yearly', '0.3'),
            new SitemapUrl(route('donate.form'), null, 'monthly', '0.9'),
        ];

        // Only advertise CMS slugs that resolve to a published page —
        // an unpublished /trustee or /mission must never appear here
        // (they 301 to /about instead; see CmsPageController).
        foreach (self::CMS_PAGE_SLUGS as $slug) {
            if (! $this->cmsPageExists($slug)) {
                continue;
            }

            $urls[] = new SitemapUrl(
                route('cms.public-page.show', ['slug' => $slug]),
                null,
                'monthly',
                '0.5',
            );
        }

        foreach ($this->campaignUrls() as $url) {
            $urls[] = $url;
        }

        foreach ($this->eventUrls() as $url) {
            $urls[] = $url;
        }

        foreach ($this->galleryUrls() as $url) {
            $urls[] = $url;
        }

        return $urls;
    }

    private function cmsPageExists(string $slug): bool
    {
        if ($this->pages === null) {
            return false;
        }

        try {
            return $this->pages->renderBySlug(new PageSlug($slug)) !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Redis-cached XML for the controller. TTL is the invalidation —
     * crawler traffic is far too low to warrant mutation wiring.
     *
     * The cache key carries the canonical host. The previous static
     * `seo:sitemap:v1` key meant that changing APP_URL (e.g. moving the
     * site from `www.` to the apex) left the OLD host baked into the
     * cached XML for up to the full TTL — which is how the live sitemap
     * and the live canonical tags came to disagree.
     */
    public function cachedXml(): string
    {
        $host = rtrim((string) config('app.url', ''), '/');

        return Cache::remember(
            self::CACHE_KEY.':'.($host === '' ? 'no-host' : $host),
            now()->addHours(self::CACHE_TTL_HOURS),
            fn (): string => $this->toXml($this->build()),
        );
    }

    /**
     * @param list<SitemapUrl> $urls
     */
    public function toXml(array $urls): string
    {
        $out = ['<?xml version="1.0" encoding="UTF-8"?>'];
        $out[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($urls as $url) {
            $out[] = '  <url>';
            $out[] = '    <loc>'.$this->escape($url->loc).'</loc>';

            if ($url->lastmod !== null) {
                $out[] = '    <lastmod>'.$this->escape($url->lastmod).'</lastmod>';
            }

            $out[] = '    <changefreq>'.$this->escape($url->changefreq).'</changefreq>';
            $out[] = '    <priority>'.$this->escape($url->priority).'</priority>';
            $out[] = '  </url>';
        }

        $out[] = '</urlset>';

        return implode("\n", $out)."\n";
    }

    /**
     * @return list<SitemapUrl>
     */
    private function campaignUrls(): array
    {
        $urls = [];
        $page = 1;

        do {
            $paged = $this->campaigns->listDisplayable($page, self::PAGE_SIZE);

            foreach ($paged->items as $dto) {
                $urls[] = new SitemapUrl(
                    route('campaigns.show', ['slug' => $dto->slug]),
                    $dto->updatedAt?->format('Y-m-d'),
                );
            }

            $page++;
        } while ($paged->hasMore);

        return $urls;
    }

    /**
     * @return list<SitemapUrl>
     */
    private function eventUrls(): array
    {
        $urls = [];

        foreach ($this->events->listUpcoming(500) as $dto) {
            $urls[] = new SitemapUrl(
                route('events.show', ['slug' => $dto->slug]),
                $dto->updatedAt?->format('Y-m-d'),
            );
        }

        $page = 1;

        do {
            $paged = $this->events->listPast($page, self::PAGE_SIZE);

            foreach ($paged->items as $dto) {
                $urls[] = new SitemapUrl(
                    route('events.show', ['slug' => $dto->slug]),
                    $dto->updatedAt?->format('Y-m-d'),
                );
            }

            $page++;
        } while ($paged->hasMore);

        return $urls;
    }

    /**
     * @return list<SitemapUrl>
     */
    private function galleryUrls(): array
    {
        $urls = [];
        $page = 1;

        do {
            $paged = $this->galleries->listDisplayable($page, self::PAGE_SIZE);

            foreach ($paged->items as $dto) {
                $urls[] = new SitemapUrl(
                    route('gallery.show', ['slug' => $dto->slug]),
                    $dto->publishedAt?->format('Y-m-d'),
                );
            }

            $page++;
        } while ($paged->hasMore);

        return $urls;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
