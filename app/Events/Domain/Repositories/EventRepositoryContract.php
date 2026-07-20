<?php

declare(strict_types=1);

namespace App\Events\Domain\Repositories;

use App\Events\Domain\DTOs\EventDetailDTO;
use App\Events\Domain\DTOs\EventSummaryDTO;

/**
 * Persistence boundary for Event reads.
 *
 * V1 is read-only. Displayable states = 'published' | 'completed'.
 * `starts_at >= :now` (upcoming) and `starts_at < :now` (past)
 * filters are applied at query time because PG rejects NOW() in
 * partial-index predicates (see V1-schema.sql comments around line
 * 866-870).
 */
interface EventRepositoryContract
{
    /**
     * Look up a displayable event by primary-key id.
     */
    public function findById(string $id): ?EventDetailDTO;

    /**
     * Look up a displayable event by slug.
     */
    public function findBySlug(string $slug): ?EventDetailDTO;

    /**
     * Upcoming published events (state='published', starts_at >= now),
     * ascending by starts_at.
     *
     * @param  string  $now  ISO-8601 timestamp passed as the :now bind
     *
     * @return list<EventSummaryDTO>
     */
    public function listUpcoming(string $now, int $limit): array;

    /**
     * Past events (state IN ('published','completed'), starts_at < now),
     * descending by starts_at, paged.
     *
     * @param  string  $now  ISO-8601 timestamp passed as the :now bind
     *
     * @return array{items: list<EventSummaryDTO>, total: int}
     */
    public function listPast(string $now, int $perPage, int $offset): array;

    /**
     * Featured events, most relevant first.
     *
     * @return list<EventSummaryDTO>
     */
    public function listFeatured(int $limit): array;

    /**
     * All displayable events, paged.
     *
     * @return array{items: list<EventSummaryDTO>, total: int}
     */
    public function listAll(int $perPage, int $offset): array;
}
