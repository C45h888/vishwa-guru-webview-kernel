<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public\Events;

use App\Events\Contracts\EventsQueryContract;
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
    public function __invoke(EventsQueryContract $events): Response
    {
        $page = max(1, (int) request()->query('page', 1));
        $perPage = 12;
        $upcomingLimit = 12;

        $upcoming = array_map(
            static fn ($dto) => $dto->toArray(),
            $events->listUpcoming($upcomingLimit),
        );

        $pastResult = $events->listPast($page, $perPage);
        $past = array_map(
            static fn ($dto) => $dto->toArray(),
            $pastResult->items,
        );

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
