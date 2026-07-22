<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Campaigns\Contracts\CampaignsQueryContract;
use App\Cms\Contracts\StaticPageRendererContract;
use App\Cms\Domain\DTOs\RenderedStaticPage;
use App\Cms\Domain\Exceptions\StaticPageNotFoundException;
use App\Cms\Services\PublicMediaPresentationService;
use App\Events\Contracts\EventsQueryContract;
use App\Gallery\Contracts\GalleryQueryContract;
use Inertia\Inertia;
use Inertia\Response;

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

        try {
            $rendered = $renderer->renderHomepage();

            return Inertia::render('cms/Home', $this->renderedProps($rendered, $payload, $media));
        } catch (StaticPageNotFoundException) {
            return Inertia::render('cms/HomeEmpty', $payload);
        }
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
     * @param  array{featuredCampaigns: list<array<string,mixed>>, featuredEvents: list<array<string,mixed>>, featuredGalleries: list<array<string,mixed>>}  $payload
     * @return array<string, mixed>
     */
    private function renderedProps(RenderedStaticPage $rendered, array $payload, PublicMediaPresentationService $media): array
    {
        return array_merge($payload, [
            'page' => $rendered->page->toArray(),
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
                    'reference_id'   => $r->referenceId->value(),
                    'context'        => $r->context,
                    'display_order'  => $r->displayOrder,
                    'status'         => $r->status->value,
                    'payload'        => $r->payload,
                ],
                $rendered->resolvedReferences,
            ),
            'html'       => $rendered->html,
            'resolvedAt' => $rendered->resolvedAt->format(DATE_ATOM),
        ]);
    }
}
