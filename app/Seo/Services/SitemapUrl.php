<?php

declare(strict_types=1);

namespace App\Seo\Services;

/**
 * Single sitemap entry.
 *
 * `lastmod` is null when the producing DTO exposes no timestamp —
 * omitting it is valid per the sitemap spec and preferable to a
 * fabricated date.
 */
final readonly class SitemapUrl
{
    public function __construct(
        public string $loc,
        public ?string $lastmod = null,
        public string $changefreq = 'weekly',
        public string $priority = '0.6',
    ) {
    }
}
