<?php

declare(strict_types=1);

namespace App\Gallery\Domain\Repositories;

use App\Gallery\Domain\DTOs\GalleryDetailDTO;
use App\Gallery\Domain\DTOs\GallerySummaryDTO;

/**
 * Persistence boundary for Gallery reads.
 *
 * V1 is read-only. State filter = 'published' AND deleted_at IS NULL.
 * Implementation lives in
 * `App\Gallery\Infrastructure\Repositories\EloquentGalleryRepository`.
 */
interface GalleryRepositoryContract
{
    /**
     * Look up a published gallery by primary-key id.
     */
    public function findById(string $id): ?GalleryDetailDTO;

    /**
     * Look up a published gallery by slug.
     */
    public function findBySlug(string $slug): ?GalleryDetailDTO;

    /**
     * Paged list of published galleries plus a total count.
     * image_count is joined from gallery_images in the same query.
     *
     * @return array{items: list<GallerySummaryDTO>, total: int}
     */
    public function listDisplayable(int $perPage, int $offset): array;

    /**
     * Featured galleries (is_featured = TRUE), most relevant first.
     *
     * @return list<GallerySummaryDTO>
     */
    public function listFeatured(int $limit): array;
}
