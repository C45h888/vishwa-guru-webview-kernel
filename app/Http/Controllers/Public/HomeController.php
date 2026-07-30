<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Campaigns\Contracts\CampaignsQueryContract;
use App\Cms\Contracts\StaticPageRendererContract;
use App\Cms\Domain\DTOs\RenderedStaticPage;
use App\Cms\Domain\ValueObjects\HomepageContent;
use App\Cms\Domain\ValueObjects\PageSlug;
use App\Cms\Services\PublicMediaPresentationService;
use App\Events\Contracts\EventsQueryContract;
use App\Gallery\Contracts\GalleryQueryContract;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Homepage (GET /).
 *
 * Doctrine (constitutional):
 *   - The controller is pure transport. It calls read-side contracts and shapes
 *     a response.
 *   - Featured campaigns / events / galleries are sourced via the read-side
 *     contracts (CampaignsQueryContract, EventsQueryContract, GalleryQueryContract).
 *     Controllers never reach repositories directly.
 *   - The CMS-rendered hero page + hero banners + homepage prose come from
 *     StaticPageRendererContract. When no homepage CMS row exists, the
 *     StaticPageRenderer returns null and the Svelte layer (cms/Home) falls
 *     back to graceful placeholder copy + gradient hero. We do NOT bounce to
 *     a separate HomeEmpty route — the new Home page is intentionally
 *     placeholder-aware so it looks complete with zero DB content.
 *
 * The homepage prose payload is `homepageContent`. Null when the row is
 * missing or the JSONB column is null; the Svelte layer treats null as
 * "use the complete FALLBACK_HOMEPAGE_CONTENT object" — we never merge
 * individual fields across CMS + fallback.
 */
final class HomeController
{
    public function index(
        StaticPageRendererContract $renderer,
        CampaignsQueryContract $campaigns,
        EventsQueryContract $events,
        GalleryQueryContract $gallery,
        PublicMediaPresentationService $media,
    ): Response {
        $payload = $this->homePayload($campaigns, $events, $gallery, $media);

        $rendered = $renderer->renderBySlug(new PageSlug('home'));

        $appName = (string) config('app.name', 'Temple Trust');
        $appUrl = (string) config('app.url');

        // Server-side fallback page so the Inertia payload always carries
        // a fully-shaped page object — even when the DB has no homepage row.
        // Home.svelte reads page.title / page.meta_description and renders
        // gradient fallbacks when those are empty.
        $fallbackPage = [
            'id' => 'fallback',
            'slug' => 'home',
            'title' => $appName,
            'meta_description' => 'Preserving sacred traditions through daily pooja, annadanam, and the care of our temple.',
            'state' => 'fallback',
            'is_homepage' => true,
        ];

        $base = array_merge(
            $payload,
            [
                'heroBanners' => [],
                'resolvedReferences' => [],
                'html' => '',
                'resolvedAt' => (new \DateTimeImmutable())->format(\DATE_ATOM),
                'homepageContent' => null,
                'appName' => $appName,
                'appUrl' => $appUrl,
            ],
        );

        if ($rendered === null) {
            return Inertia::render('cms/Home', array_merge(
                $base,
                ['page' => $fallbackPage],
            ));
        }

        return Inertia::render(
            'cms/Home',
            array_merge(
                $base,
                $this->renderedProps($rendered, $media),
            ),
        );
    }

    /**
     * @return array{
     *   featuredCampaigns: list<array<string, mixed>>,
     *   featuredEvents:    list<array<string, mixed>>,
     *   featuredGalleries: list<array<string, mixed>>,
     * }
     */
    private function homePayload(
        CampaignsQueryContract $campaigns,
        EventsQueryContract $events,
        GalleryQueryContract $gallery,
        PublicMediaPresentationService $media,
    ): array {
        return [
            'featuredCampaigns' => $media->enrichMany(array_map(
                static fn ($dto) => $dto->toArray(),
                $campaigns->listFeatured(3),
            ), 'cover_image_file_id', 'cover_image'),
            'featuredEvents' => $media->enrichMany(array_map(
                static fn ($dto) => $dto->toArray(),
                $events->listUpcoming(3),
            ), 'banner_file_id', 'banner_image'),
            'featuredGalleries' => $media->enrichMany(array_map(
                static fn ($dto) => $dto->toArray(),
                $gallery->listFeatured(3),
            ), 'cover_image_file_id', 'cover_image'),
        ];
    }

    /**
     * Shape the renderer-derived properties for the Inertia payload. The
     * homepage prose and image references live here; the controller
     * enriches the nested story + program image fields via the
     * existing PublicMediaPresentationService.
     *
     * @return array<string, mixed>
     */
    private function renderedProps(
        RenderedStaticPage $rendered,
        PublicMediaPresentationService $media,
    ): array {
        return [
            'page' => $rendered->page->toReadSummary(),
            'homepageContent' => $this->enrichHomepageContent(
                $rendered->homepageContent,
                $media,
            ),
            'heroBanners' => array_map(
                fn ($b) => $media->enrich(
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
            'resolvedReferences' => array_map(
                static fn ($r) => [
                    'reference_type' => $r->referenceType->value,
                    'reference_id' => $r->referenceId->value(),
                    'context' => $r->context,
                    'display_order' => $r->displayOrder,
                    'status' => $r->status->value,
                    'payload' => $r->payload,
                ],
                $rendered->resolvedReferences,
            ),
            'html' => $rendered->html,
            'resolvedAt' => $rendered->resolvedAt->format(\DATE_ATOM),
            'appName' => (string) config('app.name', 'Temple Trust'),
            'appUrl' => (string) config('app.url'),
        ];
    }

    /**
     * Convert the typed HomepageContent into an Inertia-friendly array,
     * enriching the nested image references via the same
     * PublicMediaPresentationService the rest of the page uses. Null in,
     * null out.
     *
     * @return array<string, mixed>|null
     */
    private function enrichHomepageContent(
        ?HomepageContent $content,
        PublicMediaPresentationService $media,
    ): ?array {
        if ($content === null) {
            return null;
        }

        $payload = $content->toArray();

        $payload['story'] = $media->enrich(
            $payload['story'],
            'image_file_id',
            'image',
        );

        $payload['programs'] = array_values(array_map(
            static fn (array $program): array => $media->enrich(
                $program,
                'image_file_id',
                'image',
            ),
            $payload['programs'],
        ));

        return $payload;
    }
}
