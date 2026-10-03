<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public\Events;

use App\Seo\Contracts\SeoMetaContract;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Browse page for the events journal — the past-events blog feed.
 *
 * 32 articles across 8 categories, sourced from a static catalog at
 * App\Events\Content\event-articles. The catalog will be replaced by a
 * backed repository when the admin surface lands.
 */
final class JournalIndexController
{
    public function __invoke(SeoMetaContract $seo): Response
    {
        $articles = require app_path('Events/Content/event-articles.php');

        $categories = [];
        $byCategory = [];
        foreach ($articles as $article) {
            $cat = $article['category'];
            $label = $article['category_label'];
            if (!isset($byCategory[$cat])) {
                $byCategory[$cat] = [
                    'key' => $cat,
                    'label' => $label,
                    'count' => 0,
                ];
            }
            $byCategory[$cat]['count']++;
        }
        $categories = array_values($byCategory);

        // Article cards — strip the body, keep the metadata for the feed
        $cards = array_map(static function (array $a): array {
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

        return Inertia::render('events/Journal', [
            'cards' => $cards,
            'categories' => $categories,
            'appName' => config('app.name', 'Temple Trust'),
            'appUrl' => config('app.url'),
            'seo' => $seo->forPage(
                title: 'Events Journal',
                description: "Stories and reflections from the trust's festivals, schools, and community life.",
            ),
        ]);
    }
}
