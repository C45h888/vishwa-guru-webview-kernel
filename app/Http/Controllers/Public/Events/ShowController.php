<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public\Events;

use App\Events\Contracts\EventsQueryContract;
use App\Cms\Services\PublicMediaPresentationService;
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
    public function __invoke(EventsQueryContract $events, PublicMediaPresentationService $media, string $slug): Response
    {
        $detail = $events->findBySlug($slug);
        if ($detail === null) {
            throw new NotFoundHttpException("Event [{$slug}] not found");
        }

        return Inertia::render('events/Show', [
            'event' => $media->enrich($detail->toArray(), 'banner_file_id', 'banner_image'),
            'appName' => config('app.name', 'Temple Trust'),
            'appUrl' => config('app.url'),
        ]);
    }
}
