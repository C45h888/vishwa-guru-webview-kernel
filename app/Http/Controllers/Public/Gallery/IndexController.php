<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public\Gallery;

use App\Gallery\Contracts\GalleryQueryContract;
use App\Cms\Services\PublicMediaPresentationService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Browse page for published galleries.
 *
 * Replaces the 501 stub reserved at routes/gallery.php:19-24.
 * 12 per page, paginated via ?page=. Each GallerySummaryDTO::toArray()
 * exposes image_count so the index card can render the count badge.
 */
final class IndexController
{
    public function __invoke(GalleryQueryContract $gallery, PublicMediaPresentationService $media): Response
    {
        $page = max(1, (int) request()->query('page', 1));
        $perPage = 12;

        $paged = $gallery->listDisplayable($page, $perPage);

        return Inertia::render('gallery/Index', [
            'galleries' => $media->enrichMany(array_map(
                static fn ($dto) => $dto->toArray(),
                $paged->items,
            ), 'cover_image_file_id', 'cover_image'),
            'pagination' => [
                'page' => $paged->page,
                'per_page' => $paged->perPage,
                'total' => $paged->total,
                'has_more' => $paged->hasMore,
            ],
            'appName' => config('app.name', 'Temple Trust'),
            'appUrl' => config('app.url'),
        ]);
    }
}
