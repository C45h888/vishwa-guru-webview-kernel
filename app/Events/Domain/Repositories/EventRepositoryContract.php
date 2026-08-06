<?php

declare(strict_types=1);

namespace App\Events\Domain\Repositories;

use App\Events\Domain\DTOs\EventDetailDTO;
use App\Events\Domain\DTOs\EventSummaryDTO;

/**
 * Persistence boundary for Event reads + writes.
 *
 * V1 was read-only. Phase 4 (admin kernel) added authoring methods so
 * the admin can create, edit, and end events.
 *
 * Doctrine:
 *   - Read methods filter `state IN ('published','completed') AND
 *     deleted_at IS NULL` so the public surface only sees displayable
 *     rows.
 *   - Authoring methods (create / update / findByIdIncludingDrafts /
 *     listAllIncludingDrafts / end) operate on the FULL row including
 *     drafts. Auth is enforced at the controller / middleware layer.
 *   - Repository does NOT enforce auth; it provides the storage
 *     boundary only.
 *   - Services depend on this contract, NEVER on the Eloquent impl.
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
     * Look up an event by id INCLUDING drafts (state='draft') and
     * completed-without-publish. Used by the admin authoring surface
     * for the edit form. Caller is responsible for authorisation.
     */
    public function findByIdIncludingDrafts(string $id): ?EventDetailDTO;

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

    /**
     * Paged list of ALL events including drafts, for the admin
     * authoring surface. Sorted by starts_at DESC so upcoming or
     * freshest events are surfaced first.
     *
     * @return array{items: list<EventSummaryDTO>, total: int}
     */
    public function listAllIncludingDrafts(int $perPage, int $offset): array;

    /**
     * Insert a new event row. Returns the persisted DTO.
     *
     * @param  array<string, mixed>  $data  column → value mapping. Keys
     *                                      MUST match the events table
     *                                      columns. id, created_at, and
     *                                      updated_at are populated by
     *                                      the repository if absent.
     */
    public function create(array $data): EventDetailDTO;

    /**
     * Update an existing event row. Returns the post-update DTO.
     * Returns null when the row does not exist.
     *
     * @param  array<string, mixed>  $data  column → value mapping. Only
     *                                      keys present in $data are
     *                                      updated; absent keys are
     *                                      preserved. updated_at is
     *                                      bumped automatically.
     */
    public function update(string $id, array $data): ?EventDetailDTO;

    /**
     * End-event transition: flip state from (draft|published) to
     * 'completed' and stamp completed_at = now. Returns the post-update
     * DTO, or null when the row does not exist.
     *
     * Doctrine: idemptotent. If the event is already 'completed', the
     * existing completed_at is preserved (no overwrite). Unpublish
     * (state → draft) is exposed via the regular `update()` path; this
     * method is the dedicated "end" action because it's the
     * user-facing button that admin's click to retire an event.
     */
    public function end(string $id): ?EventDetailDTO;
}
