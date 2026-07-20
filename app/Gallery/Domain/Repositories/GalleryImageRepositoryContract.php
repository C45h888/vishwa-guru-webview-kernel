<?php

declare(strict_types=1);

namespace App\Gallery\Domain\Repositories;

use App\Gallery\Domain\DTOs\GalleryImageDTO;

/**
 * Persistence boundary for GalleryImage reads.
 *
 * V1 is read-only. State filter = 'published' AND deleted_at IS NULL.
 * Ordered by display_order ASC, id ASC for stable pagination.
 */
interface GalleryImageRepositoryContract
{
    /**
     * List published images for a gallery, ordered by display_order.
     * Limit is required (caller picks a sensible bound).
     *
     * @return list<GalleryImageDTO>
     */
    public function listByGallery(string $galleryId, ?int $limit): array;
}
