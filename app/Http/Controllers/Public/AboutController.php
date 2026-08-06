<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Cms\Contracts\StaticPageRendererContract;
use App\Cms\Domain\DTOs\RenderedStaticPage;
use App\Cms\Domain\ValueObjects\PageSlug;
use App\Cms\Services\PublicMediaPresentationService;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * About page (GET /about).
 *
 * Doctrine (constitutional):
 *   - The controller is pure transport. It calls the read-side contract
 *     `StaticPageRendererContract::renderBySlug` and shapes the Inertia
 *     response. No repository calls, no business logic.
 *   - Trustee photo `file_id` strings are enriched via the shared
 *     `PublicMediaPresentationService` so the Svelte layer receives
 *     `PublicMediaProps` (with `url`, `mime_type`, `alt_text`, etc.) on
 *     each trustee — no client-side file lookups.
 *   - When no `about` row exists (or its `about_page_content` is null),
 *     the renderer returns null and the route falls through to 404.
 *     The Svelte layer handles the JSON-level null with a fallback
 *     default — that branch is exercised at runtime even before the
 *     seed lands.
 */
final class AboutController
{
    public function index(
        StaticPageRendererContract $renderer,
        PublicMediaPresentationService $media,
    ): Response {
        $rendered = $renderer->renderBySlug(new PageSlug('about'));

        if ($rendered === null) {
            throw new NotFoundHttpException('Page [about] not found.');
        }

        $appName = (string) config('app.name', 'Temple Trust');
        $appUrl = (string) config('app.url');

        return Inertia::render('cms/About', $this->props($rendered, $media, $appName, $appUrl));
    }

    /**
     * Shape the Inertia payload. Mirrors the Home page's
     * `renderedProps` shape so the Svelte layer's typed contract
     * stays aligned with the rest of the cms domain.
     *
     * @return array<string, mixed>
     */
    private function props(
        RenderedStaticPage $rendered,
        PublicMediaPresentationService $media,
        string $appName,
        string $appUrl,
    ): array {
        $aboutContent = $rendered->aboutPageContent?->toArray() ?? null;

        if (is_array($aboutContent) && isset($aboutContent['trustees']) && is_array($aboutContent['trustees'])) {
            $aboutContent['trustees'] = $media->enrichMany(
                $aboutContent['trustees'],
                'photo_file_id',
                'photo',
            );
        }

        if (is_array($aboutContent) && isset($aboutContent['values']) && is_array($aboutContent['values'])) {
            $aboutContent['values'] = $media->enrich(
                $aboutContent['values'],
                'image_file_id',
                'image',
            );
        }

        if (is_array($aboutContent) && isset($aboutContent['story']) && is_array($aboutContent['story'])) {
            $aboutContent['story'] = $media->enrich(
                $aboutContent['story'],
                'image_file_id',
                'image',
            );
        }

        if (is_array($aboutContent) && isset($aboutContent['timeline']) && is_array($aboutContent['timeline'])) {
            $aboutContent['timeline'] = $media->enrichMany(
                $aboutContent['timeline'],
                'image_file_id',
                'image',
            );
        }

        return [
            'page' => $rendered->page->toArray(),
            'aboutContent' => $aboutContent,
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
            'appName' => $appName,
            'appUrl' => $appUrl,
        ];
    }
}
