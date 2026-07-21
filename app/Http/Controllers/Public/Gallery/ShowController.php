<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public\Gallery;

use App\Gallery\Contracts\GalleryQueryContract;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Detail page for one gallery by slug.
 *
 * Replaces the 501 stub reserved at routes/gallery.php:26-31.
 * GalleryDetailDTO::toArray() carries an `images` array (each
 * GalleryImageDTO serialised) so the show page can render the
 * image grid directly from the Inertia props without a second
 * fetch.
 */
final class ShowController
{
    public function __invoke(GalleryQueryContract $gallery, string $slug): Response
    {
        $detail = $gallery->findBySlug($slug);
        if ($detail === null) {
            throw new NotFoundHttpException("Gallery [{$slug}] not found");
        }

        return Inertia::render('gallery/Show', [
            'gallery' => $detail->toArray(),
            'appName' => config('app.name', 'Temple Trust'),
            'appUrl' => config('app.url'),
        ]);
    }
}
