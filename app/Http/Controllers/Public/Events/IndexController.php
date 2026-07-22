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
 * Replaces the 501 stub reserved at routes/events.php:19-24.
 * Splits displayable events into two sections — upcoming (top, no
 * pagination, limited to 12) and past (paginated). Each EventSummaryDTO
 * is serialised via toArray() before reaching Inertia.
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
