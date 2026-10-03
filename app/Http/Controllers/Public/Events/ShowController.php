<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public\Events;

use App\Events\Contracts\EventsQueryContract;
use App\Cms\Services\PublicMediaPresentationService;
use App\Seo\Contracts\SeoMetaContract;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Detail page for one event by slug.
 *
 * Replaces the 501 stub reserved at routes/events.php:26-31.
 * EventDetailDTO already carries a related events list (DTO field
 * `related: list<EventSummaryDTO>`); pass it through to Inertia so
 * the show page can render related-event tiles.
 */
final class ShowController
{
    public function __invoke(
        EventsQueryContract $events,
        PublicMediaPresentationService $media,
        SeoMetaContract $seo,
        string $slug,
    ): Response {
        $detail = $events->findBySlug($slug);
        if ($detail === null) {
            throw new NotFoundHttpException("Event [{$slug}] not found");
        }

        $enriched = $media->enrich($detail->toArray(), 'banner_file_id', 'banner_image');

        $banner = is_array($enriched['banner_image'] ?? null) ? $enriched['banner_image'] : [];

        return Inertia::render('events/Show', [
            'event' => $enriched,
            'appName' => config('app.name', 'Temple Trust'),
            'appUrl' => config('app.url'),
            'seo' => $seo->forPage(
                title: (string) ($enriched['title'] ?? ''),
                description: $enriched['short_description'] ?? null,
                imageUrl: $banner['url'] ?? null,
                imageAlt: $banner['alt_text'] ?? null,
                type: 'article',
            ),
        ]);
    }
}
