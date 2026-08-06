<?php

declare(strict_types=1);

namespace App\Campaigns\Domain\Repositories;

use App\Campaigns\Domain\DTOs\CampaignDetailDTO;

/**
 * Persistence boundary for Campaign reads + writes.
 *
 * V1 was read-only. Phase 4 (admin kernel) added authoring methods so
 * the admin can create and edit campaigns. The read methods continue
 * to filter `state IN ('active','completed') AND deleted_at IS NULL`
 * because the public surface only sees displayable rows.
 *
 * Authoring methods (create / update / findByIdIncludingDrafts /
 * listAllIncludingDrafts) operate on the FULL row including drafts —
 * callers must be authorised (EnsureUserIsAdmin middleware) before
 * reaching these. Doctrine: the repository does NOT enforce auth;
 * it provides the storage boundary. Auth is a service-layer concern.
 *
 * Doctrine: services depend on this contract, NEVER on the Eloquent
 * implementation directly.
 */
interface CampaignRepositoryContract
{
    /**
     * Look up a displayable campaign by primary-key id.
     * Returns null when missing, soft-deleted, or not in
     * {active, completed}.
     */
    public function findById(string $id): ?CampaignDetailDTO;

    /**
     * Look up a displayable campaign by slug.
     * Returns null when missing, soft-deleted, or not in
     * {active, completed}.
     */
    public function findBySlug(string $slug): ?CampaignDetailDTO;

    /**
     * Look up a campaign by id INCLUDING drafts (state='draft').
     * Used by the admin authoring surface for the edit form. The
     * caller is responsible for authorisation — repository does NOT
     * gate.
     */
    public function findByIdIncludingDrafts(string $id): ?CampaignDetailDTO;

    /**
     * Paged list of displayable campaigns plus a total count.
     *
     * Implementation uses `COUNT(*) OVER ()` to derive the total in
     * the same round-trip; the total is 0 when the result set is empty.
     *
     * @return array{items: list<\App\Campaigns\Domain\DTOs\CampaignSummaryDTO>, total: int}
     */
    public function listDisplayable(int $perPage, int $offset): array;

    /**
     * Featured campaigns (is_featured = TRUE), most relevant first.
     *
     * @return list<\App\Campaigns\Domain\DTOs\CampaignSummaryDTO>
     */
    public function listFeatured(int $limit): array;

    /**
     * Paged list of ALL campaigns including drafts, for the admin
     * authoring surface. Sorted by updated_at DESC so freshly-edited
     * campaigns surface first.
     *
     * @return array{items: list<\App\Campaigns\Domain\DTOs\CampaignSummaryDTO>, total: int}
     */
    public function listAllIncludingDrafts(int $perPage, int $offset): array;

    /**
     * Insert a new campaign row. Returns the persisted DTO (with
     * the canonical ULID id assigned by storage).
     *
     * @param  array<string, mixed>  $data  column → value mapping. Keys
     *                                      MUST match the campaigns table
     *                                      columns. The id, created_at,
     *                                      and updated_at are populated
     *                                      by the repository if absent.
     */
    public function create(array $data): CampaignDetailDTO;

    /**
     * Update an existing campaign row. Returns the post-update DTO.
     * Returns null when the row does not exist (caller should treat
     * this as a 404).
     *
     * @param  array<string, mixed>  $data  column → value mapping. Only
     *                                      keys present in $data are
     *                                      updated; absent keys are
     *                                      preserved. updated_at is
     *                                      bumped automatically.
     */
    public function update(string $id, array $data): ?CampaignDetailDTO;
}
