<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Cms\Contracts\StaticPageRendererContract;
use App\Cms\Services\PublicMediaPresentationService;
use App\Seo\Contracts\SeoMetaContract;
use App\Cms\Domain\ValueObjects\PageSlug;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Generic CMS page resolver for /about, /privacy, /terms, /trustee, /mission, /policies.
 * (Slug whitelist is enforced at routes/web.php:29 — `(about|privacy|terms|trustee|mission|policies)`.)
 *
 * Doctrine: thin controller. Renders any published static page through
 * StaticPageRendererContract::renderBySlug — never reaches into a repository.
 * Falls through to 404 when the slug is not in the route whitelist, the page
 * does not exist, or it is not in a publicly-readable state.
 */
final class CmsPageController
{
    public function show(
        StaticPageRendererContract $renderer,
        PublicMediaPresentationService $media,
        SeoMetaContract $seo,
        string $slug,
    ): Response {
        $rendered = $renderer->renderBySlug(new PageSlug($slug));

        if ($rendered === null) {
            throw new NotFoundHttpException("Page [{$slug}] not found.");
        }

        return Inertia::render('cms/Page', [
            'page' => $rendered->page->toReadSummary(),
            'heroBanners' => array_map(
                static fn ($b) => $media->enrich(
                    $media->enrich(
                        $b->toArray(),
                        'image_file_id',
                        'image',
                    ),
                    'mobile_image_file_id',
                    'mobile_image',
                ),
                $rendered->heroBanners,
            ),
            'html' => $rendered->html,
            'resolvedAt' => $rendered->resolvedAt->format(\DATE_ATOM),
            'appName' => (string) config('app.name', 'Temple Trust'),
            'appUrl' => (string) config('app.url'),
            'seo' => $seo->forPage(
                title: (string) ($rendered->page->toReadSummary()['title'] ?? ''),
                description: $rendered->page->toReadSummary()['meta_description'] ?? null,
            ),
        ]);
    }
}