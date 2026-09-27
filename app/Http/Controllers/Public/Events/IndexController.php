<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public\Events;

use App\Events\Contracts\EventsQueryContract;
use App\Cms\Services\PublicMediaPresentationService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Browse page for temple events.
 *
 * Surface is fully DB-driven. The 32-article journal catalog from
 * app/Events/Content/event-articles.php is no longer merged here —
 * the journal routes were removed when the events surface was
 * thinned to its 4 canonical entries (see routes/events.php and
 * align_events.php for the seed surface). Each event card on this
 * page resolves its banner through PublicMediaPresentationService,
 * which points at /media/{cms_media_id} → file_assets.storage_path,
 * i.e. storage/app/public/cms-media-upscaled/canonical/<file>.
 */
final class IndexController
{
    public function __invoke(EventsQueryContract $events, PublicMediaPresentationService $media): Response
    {
        $page = max(1, (int) request()->query('page', 1));
        $perPage = 12;
        $upcomingLimit = 12;

        $upcoming = $media->enrichMany(array_map(
            static fn ($dto) => $dto->toArray(),
            $events->listUpcoming($upcomingLimit),
        ), 'banner_file_id', 'banner_image');

        $pastResult = $events->listPast($page, $perPage);
        $past = $media->enrichMany(array_map(
            static fn ($dto) => $dto->toArray(),
            $pastResult->items,
        ), 'banner_file_id', 'banner_image');

        return Inertia::render('events/Index', [
            'upcoming' => $upcoming,
            'past' => $past,
            'pagination' => [
                'page' => $pastResult->page,
                'per_page' => $pastResult->perPage,
                'total' => $pastResult->total,
                'has_more' => $pastResult->hasMore,
            ],
            'appName' => config('app.name', 'Temple Trust'),
            'appUrl' => config('app.url'),
        ]);
    }
}
