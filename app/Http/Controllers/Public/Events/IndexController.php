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
 * The live upcoming/past feeds come from the events module (currently
 * empty). The journal articles from event-articles.php are also passed
 * through and rendered as the "Past events" preview list on the page.
 * Once the events table is populated, the live `past` feed will
 * shadow the journal preview.
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

        $articles = require app_path('Events/Content/event-articles.php');
        $pastArticles = array_map(static function (array $a): array {
            return [
                'slug' => $a['slug'],
                'category' => $a['category'],
                'category_label' => $a['category_label'],
                'eyebrow' => $a['eyebrow'],
                'title' => $a['title'],
                'excerpt' => $a['excerpt'],
                'image' => $a['image'],
                'image_alt' => $a['image_alt'],
            ];
        }, $articles);

        return Inertia::render('events/Index', [
            'upcoming' => $upcoming,
            'past' => $past,
            'pagination' => [
                'page' => $pastResult->page,
                'per_page' => $pastResult->perPage,
                'total' => $pastResult->total,
                'has_more' => $pastResult->hasMore,
            ],
            'pastArticles' => $pastArticles,
            'appName' => config('app.name', 'Temple Trust'),
            'appUrl' => config('app.url'),
        ]);
    }
}
