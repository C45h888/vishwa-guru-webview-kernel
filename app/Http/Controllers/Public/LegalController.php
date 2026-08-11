<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Cms\Contracts\StaticPageRendererContract;
use App\Cms\Domain\DTOs\RenderedStaticPage;
use App\Cms\Domain\ValueObjects\PageSlug;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Legal page (GET /legal).
 *
 * Doctrine (constitutional):
 *   - The controller is pure transport. It calls the read-side contract
 *     `StaticPageRendererContract::renderBySlug` and shapes the Inertia
 *     response. No repository calls, no business logic.
 *   - The structured certificate content (intro + certificates[]) is
 *     carried by `static_pages.legal_page_content` (JSONB) and surfaced
 *     by the renderer as a typed `LegalPageContent` value object.
 *     The controller passes `$rendered->legalPageContent?->toArray()`
 *     to the Svelte layer; if no row exists or its `legal_page_content`
 *     column is null, the renderer returns null and the Svelte layer
 *     falls back to FALLBACK_LEGAL_PAGE_CONTENT.
 *   - When no `legal` row exists (or it is not publicly readable),
 *     the renderer returns null and the route throws 404.
 *
 * The page intentionally has no hero banners — the regulatory content
 * is content-only and the existing hero authoring surface applies to
 * the other public pages (homepage, about, contact) where a magazine
 * hero drives the visual entry. The Legal page is information-dense
 * and forgoes the hero band.
 */
final class LegalController
{
    public function index(
        StaticPageRendererContract $renderer,
    ): Response {
        $rendered = $renderer->renderBySlug(new PageSlug('legal'));

        if ($rendered === null) {
            throw new NotFoundHttpException('Page [legal] not found.');
        }

        $appName = (string) config('app.name', 'Temple Trust');
        $appUrl = (string) config('app.url');

        return Inertia::render('cms/Legal', $this->props($rendered, $appName, $appUrl));
    }

    /**
     * Shape the Inertia payload. Mirrors the About page's `props`
     * shape so the Svelte layer's typed contract stays aligned.
     *
     * @return array<string, mixed>
     */
    private function props(
        RenderedStaticPage $rendered,
        string $appName,
        string $appUrl,
    ): array {
        return [
            'page' => $rendered->page->toReadSummary(),
            'legalContent' => $rendered->legalPageContent?->toArray() ?? null,
            'appName' => $appName,
            'appUrl' => $appUrl,
        ];
    }
}
