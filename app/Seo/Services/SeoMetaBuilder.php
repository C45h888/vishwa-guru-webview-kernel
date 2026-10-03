<?php

declare(strict_types=1);

namespace App\Seo\Services;

use App\Seo\Contracts\SeoMetaContract;
use Illuminate\Http\Request;

/**
 * SeoMetaBuilder — the only place public <head> tags are assembled.
 *
 * Consumes domain values (title, description, share image) and emits a
 * flat, ordered list of `{tag, attrs}` pairs. The list shape is
 * deliberate: the Inertia root Blade shell can render it with a single
 * generic loop, which keeps business/SEO logic out of the view and
 * keeps app.blade.php the runtime's only Blade template (AGENTS.md).
 *
 * HOST NORMALISATION
 *
 * Every absolute URL in the payload is built from `config('app.url')`
 * and never from the incoming request host. This is the fix for the
 * www-vs-apex conflict: `route()` and `url()` default to whatever host
 * the request arrived on, so a visitor hitting the site via `www.` (or
 * a bare IP) would otherwise produce a sitemap full of that host while
 * the canonical tags advertised a different one. AppServiceProvider
 * pins the root URL for `route()`; this builder simply never asks the
 * request what host it is.
 */
final class SeoMetaBuilder implements SeoMetaContract
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    public function forPage(
        string $title,
        ?string $description = null,
        ?string $imageUrl = null,
        ?string $imageAlt = null,
        string $type = 'website',
        bool $noindex = false,
        ?array $jsonLd = null,
        bool $appendTrustName = true,
        ?string $path = null,
    ): array {
        $trustName = trim((string) config('app.name', 'Temple Trust'));
        $description = $this->clean($description);
        $image = $this->absoluteUrl($this->clean($imageUrl));
        $imageAlt = $this->clean($imageAlt);

        $bareTitle = trim($title);
        $fullTitle = $appendTrustName && $bareTitle !== ''
            ? $bareTitle.' — '.$trustName
            : ($bareTitle !== '' ? $bareTitle : $trustName);

        $canonical = $this->canonical($path);

        $tags = [];

        if ($description !== null) {
            $tags[] = $this->meta('description', $description);
        }

        // Robots: emitted only when it deviates from the default index,
        // follow. A blanket "index, follow" tag is noise.
        if ($noindex) {
            $tags[] = $this->meta('robots', 'noindex, nofollow');
        }

        if ($canonical !== null) {
            $tags[] = ['tag' => 'link', 'attrs' => ['rel' => 'canonical', 'href' => $canonical]];
        }

        // Open Graph — the single source for WhatsApp, Facebook and
        // iMessage link previews. All three read these server-side.
        $tags[] = $this->meta('og:site_name', $trustName, property: true);
        $tags[] = $this->meta('og:type', $type, property: true);
        $tags[] = $this->meta('og:title', $fullTitle, property: true);

        if ($description !== null) {
            $tags[] = $this->meta('og:description', $description, property: true);
        }

        if ($canonical !== null) {
            $tags[] = $this->meta('og:url', $canonical, property: true);
        }

        if ($image !== null) {
            $tags[] = $this->meta('og:image', $image, property: true);

            if ($imageAlt !== null) {
                $tags[] = $this->meta('og:image:alt', $imageAlt, property: true);
                $tags[] = $this->meta('og:image:width', '1200', property: true);
                $tags[] = $this->meta('og:image:height', '630', property: true);
            }
        }

        $tags[] = $this->meta('twitter:card', $image !== null ? 'summary_large_image' : 'summary');
        $tags[] = $this->meta('twitter:title', $fullTitle);

        if ($description !== null) {
            $tags[] = $this->meta('twitter:description', $description);
        }

        if ($image !== null) {
            $tags[] = $this->meta('twitter:image', $image);
        }

        return [
            'title' => $fullTitle,
            'tags' => $tags,
            'jsonLd' => $jsonLd,
            // Pre-encoded so the Blade shell and SeoHead.svelte emit a
            // byte-identical <script> block. If each side encoded the graph
            // itself, the two writers would drift the moment a title
            // contained a quote or an ampersand.
            'jsonLdString' => $jsonLd === null
                ? null
                : json_encode(
                    $jsonLd,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
                ),
        ];
    }

    public function trustGraph(): array
    {
        $base = $this->baseUrl();

        $graph = [
            '@context' => 'https://schema.org',
            '@type' => 'HinduTemple',
            'name' => trim((string) config('app.name', 'Temple Trust')),
        ];

        if ($base !== null) {
            $graph['url'] = $base;
            $graph['logo'] = $base.'/icon-512.png';
        }

        return $graph;
    }

    /**
     * Absolute canonical for the current page, or null when no host is
     * configured (local/dev). Never guessed — a canonical pointing at
     * the wrong host is worse than no canonical at all.
     */
    private function canonical(?string $path = null): ?string
    {
        $base = $this->baseUrl();

        if ($base === null) {
            return null;
        }

        $path ??= '/'.ltrim($this->request->path(), '/');

        if ($path === '' || $path === '/') {
            return $base.'/';
        }

        return $base.'/'.ltrim($path, '/');
    }

    private function baseUrl(): ?string
    {
        $url = trim((string) config('app.url', ''));

        // A scheme is REQUIRED. A bare host or IP — which is what APP_URL
        // looks like when it is misconfigured — would otherwise produce a
        // malformed canonical such as "86.107.77.79/campaigns". That is
        // strictly worse than emitting no canonical at all, because it
        // asks a search engine to consolidate real pages away to a URL it
        // cannot fetch. Same reasoning suppresses localhost, where an
        // absolute canonical would leak the test/dev host.
        if ($url === '' || preg_match('#^https?://#i', $url) !== 1) {
            return null;
        }

        if (str_starts_with(strtolower($url), 'http://localhost')) {
            return null;
        }

        return rtrim($url, '/');
    }

    /**
     * Promote a root-relative share image to an absolute URL. Scrapers
     * reject relative og:image values outright.
     */
    private function absoluteUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }

        $base = $this->baseUrl();

        return $base === null ? null : $base.'/'.ltrim($url, '/');
    }

    private function meta(string $key, string $content, bool $property = false): array
    {
        return [
            'tag' => 'meta',
            'attrs' => [$property ? 'property' : 'name' => $key, 'content' => $content],
        ];
    }

    private function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
