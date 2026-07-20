<?php

declare(strict_types=1);

namespace App\Events\Contracts;

use App\Events\Domain\DTOs\EventDetailDTO;
use App\Events\Domain\DTOs\EventPagedResultDTO;
use App\Events\Domain\DTOs\EventSummaryDTO;

/**
 * Public read API for temple events.
 *
 * Implemented by `App\Events\Services\EventsQueryService` and
 * consumed by Sub-project 3 (UI) via `ModuleContract::dependencies()`.
 *
 * Doctrine (cms-architecture.md §7):
 *   - The producing kernel owns the contract.
 *   - Consumers never import Events\Services or Events\Infrastructure
 *     directly — only the contract surface is sanctioned.
 *   - Methods return null / empty list (no throw) on missing or
 *     non-displayable rows so callers can degrade gracefully.
 *
 * Displayable = state IN ('published','completed') AND deleted_at IS NULL.
 * Upcoming = displayable AND starts_at >= NOW().
 * Past = displayable AND starts_at < NOW().
 */
interface EventsQueryContract
{
    /**
     * Upcoming published events (state='published', starts_at >= NOW()),
     * ascending by starts_at.
     *
     * @return list<EventSummaryDTO>
     */
    public function listUpcoming(int $limit = 6): array;

    /**
     * Past events (displayable AND starts_at < NOW()), descending by
     * starts_at, paged.
     */
    public function listPast(int $page = 1, int $perPage = 12): EventPagedResultDTO;

    /**
     * Featured events for the homepage / site-nav tile.
     *
     * @return list<EventSummaryDTO>
     */
    public function listFeatured(int $limit = 6): array;

    /**
     * All displayable events (state IN ('published','completed')),
     * paged, descending by starts_at.
     */
    public function listAll(int $page = 1, int $perPage = 12): EventPagedResultDTO;

    /**
     * Look up a single displayable event by its primary-key id.
     * Returns null when missing, soft-deleted, or non-displayable.
     */
    public function findById(string $id): ?EventDetailDTO;

    /**
     * Look up a single displayable event by slug.
     * Returns null when missing, soft-deleted, or non-displayable.
     */
    public function findBySlug(string $slug): ?EventDetailDTO;
}
