<?php

declare(strict_types=1);

namespace App\Gallery\Contracts;

use App\Gallery\Domain\DTOs\GalleryDetailDTO;
use App\Gallery\Domain\DTOs\GalleryPagedResultDTO;
use App\Gallery\Domain\DTOs\GallerySummaryDTO;

/**
 * Public read API for temple galleries.
 *
 * Implemented by `App\Gallery\Services\GalleryQueryService` and
 * consumed by Sub-project 3 (UI) via `ModuleContract::dependencies()`.
 *
 * Doctrine (cms-architecture.md §7):
 *   - The producing kernel owns the contract.
 *   - Consumers never import Gallery\Services or Gallery\Infrastructure
 *     directly — only the contract surface is sanctioned.
 *   - Methods return null / empty list (no throw) on missing or
 *     non-displayable rows so callers can degrade gracefully.
 *
 * Displayable = state = 'published' AND deleted_at IS NULL.
 */
interface GalleryQueryContract
{
    /**
     * Paged list of publicly published galleries.
     *
     * Ordering: featured DESC, display_order ASC, published_at DESC
     * NULLS LAST, id ASC.
     */
    public function listDisplayable(int $page = 1, int $perPage = 12): GalleryPagedResultDTO;

    /**
     * Featured galleries for the homepage / site-nav tile.
     *
     * @return list<GallerySummaryDTO>
     */
    public function listFeatured(int $limit = 6): array;

    /**
     * Look up a single published gallery by its primary-key id.
     * Returns null when missing, soft-deleted, or not published.
     */
    public function findById(string $id): ?GalleryDetailDTO;

    /**
     * Look up a single published gallery by slug.
     * Returns null when missing, soft-deleted, or not published.
     */
    public function findBySlug(string $slug): ?GalleryDetailDTO;
}
