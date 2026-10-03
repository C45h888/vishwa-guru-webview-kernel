<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public\Seo;

use App\Seo\Services\SitemapBuilder;
use Illuminate\Http\Response;

/**
 * Serves /sitemap.xml (not Inertia — crawlers don't do Inertia).
 *
 * The builder owns enumeration + XML; the response is Redis-cached
 * inside the builder (6h TTL). Thin by doctrine.
 */
final class SitemapController
{
    public function __invoke(SitemapBuilder $builder): Response
    {
        return response(
            $builder->cachedXml(),
            200,
            ['Content-Type' => 'application/xml; charset=UTF-8'],
        );
    }
}
