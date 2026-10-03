<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public\Events;

use App\Seo\Contracts\SeoMetaContract;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Show page for one event journal article.
 *
 * Looks up the article by slug in the static catalog. 404 on miss.
 * Exposes the full body, the metadata, and a small "related" set drawn
 * from the same category (excluding the current article).
 */
final class JournalShowController
{
    private const RELATED_LIMIT = 3;

    public function __invoke(SeoMetaContract $seo, string $slug): Response
    {
        $articles = require app_path('Events/Content/event-articles.php');

        $current = null;
        $category = null;
        foreach ($articles as $article) {
            if ($article['slug'] === $slug) {
                $current = $article;
                $category = $article['category'];
                break;
            }
        }

        if ($current === null) {
            throw new NotFoundHttpException("Article [{$slug}] not found");
        }

        $related = [];
        foreach ($articles as $article) {
            if ($article['slug'] === $slug) {
                continue;
            }
            if ($article['category'] === $category) {
                $related[] = [
                    'slug' => $article['slug'],
                    'title' => $article['title'],
                    'eyebrow' => $article['eyebrow'],
                    'excerpt' => $article['excerpt'],
                    'image' => $article['image'],
                    'image_alt' => $article['image_alt'],
                ];
                if (count($related) >= self::RELATED_LIMIT) {
                    break;
                }
            }
        }

        return Inertia::render('events/Article', [
            'article' => $current,
            'related' => $related,
            'appName' => config('app.name', 'Temple Trust'),
            'appUrl' => config('app.url'),
            'seo' => $seo->forPage(
                title: (string) ($current['title'] ?? ''),
                description: $current['excerpt'] ?? null,
                type: 'article',
            ),
        ]);
    }
}
