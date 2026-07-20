<?php

declare(strict_types=1);

namespace App\Gallery\Services;

use App\Gallery\Contracts\GalleryQueryContract;
use App\Gallery\Domain\DTOs\GalleryDetailDTO;
use App\Gallery\Domain\DTOs\GalleryPagedResultDTO;
use App\Gallery\Domain\DTOs\GallerySummaryDTO;
use App\Gallery\Domain\Repositories\GalleryRepositoryContract;

/**
 * GalleryQueryService — public read surface for temple galleries.
 *
 * Composes GalleryRepositoryContract. State filter and image_count
 * aggregation are handled by the repository; this service only clamps
 * paging inputs.
 *
 * Doctrine: thin service. V1 is read-only; admin mutations arrive in
 * Phase 4. Page limits: perPage clamped to [1, 50], default 12.
 */
final class GalleryQueryService implements GalleryQueryContract
{
    private const DEFAULT_PER_PAGE = 12;

    private const MIN_PER_PAGE = 1;

    private const MAX_PER_PAGE = 50;

    public function __construct(
        private readonly GalleryRepositoryContract $galleries,
    ) {
    }

    public function listDisplayable(int $page = 1, int $perPage = self::DEFAULT_PER_PAGE): GalleryPagedResultDTO
    {
        [$page, $perPage] = self::clampPaging($page, $perPage);
        $offset = ($page - 1) * $perPage;
        $result = $this->galleries->listDisplayable($perPage, $offset);

        return new GalleryPagedResultDTO(
            items: $result['items'],
            total: $result['total'],
            page: $page,
            perPage: $perPage,
            hasMore: ($page * $perPage) < $result['total'],
        );
    }

    public function listFeatured(int $limit = 6): array
    {
        if ($limit < 1) {
            $limit = 1;
        }

        return $this->galleries->listFeatured($limit);
    }

    public function findById(string $id): ?GalleryDetailDTO
    {
        return $this->galleries->findById($id);
    }

    public function findBySlug(string $slug): ?GalleryDetailDTO
    {
        return $this->galleries->findBySlug($slug);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private static function clampPaging(int $page, int $perPage): array
    {
        if ($page < 1) {
            $page = 1;
        }
        if ($perPage < self::MIN_PER_PAGE) {
            $perPage = self::DEFAULT_PER_PAGE;
        } elseif ($perPage > self::MAX_PER_PAGE) {
            $perPage = self::MAX_PER_PAGE;
        }

        return [$page, $perPage];
    }
}
