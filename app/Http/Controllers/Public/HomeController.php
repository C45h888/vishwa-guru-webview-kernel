<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Campaigns\Contracts\CampaignsQueryContract;
use App\Cms\Domain\DTOs\RenderedStaticPage;
use App\Cms\Domain\Exceptions\StaticPageNotFoundException;
use App\Cms\Services\StaticPageRendererService;
use App\Events\Contracts\EventsQueryContract;
use App\Gallery\Contracts\GalleryQueryContract;
use Inertia\Inertia;
use Inertia\Response;

final class HomeController
{
    public function index(
        StaticPageRendererService $renderer,
        CampaignsQueryContract $campaigns,
        EventsQueryContract $events,
        GalleryQueryContract $gallery,
    ): Response {
        $payload = $this->homePayload($campaigns, $events, $gallery);

        try {
            $rendered = $renderer->renderHomepage();

            return Inertia::render('cms/Home', $this->renderedProps($rendered, $payload));
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
    ): array {
        return [
            'featuredCampaigns' => array_map(
                static fn ($dto) => $dto->toArray(),
                $campaigns->listFeatured(3),
            ),
            'featuredEvents' => array_map(
                static fn ($dto) => $dto->toArray(),
                $events->listUpcoming(3),
            ),
            'featuredGalleries' => array_map(
                static fn ($dto) => $dto->toArray(),
                $gallery->listFeatured(3),
            ),
        ];
    }

    /**
     * @param  array{featuredCampaigns: list<array<string,mixed>>, featuredEvents: list<array<string,mixed>>, featuredGalleries: list<array<string,mixed>>}  $payload
     * @return array<string, mixed>
     */
    private function renderedProps(RenderedStaticPage $rendered, array $payload): array
    {
        return array_merge($payload, [
            'page' => $rendered->page->toArray(),
            'heroBanners' => array_map(
                static fn ($b) => $b->toArray(),
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
