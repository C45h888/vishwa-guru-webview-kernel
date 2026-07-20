<?php

declare(strict_types=1);

namespace App\Campaigns\Domain\Repositories;

use App\Campaigns\Domain\DTOs\CampaignDetailDTO;
use App\Campaigns\Domain\DTOs\CampaignSummaryDTO;

/**
 * Persistence boundary for Campaign reads.
 *
 * V1 is read-only. State filter = 'active' | 'completed' AND
 * deleted_at IS NULL. Implementation lives in
 * `App\Campaigns\Infrastructure\Repositories\EloquentCampaignRepository`.
 *
 * Doctrine: services depend on this contract, NEVER on the Eloquent
 * implementation directly. The service is the only caller in V1.
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
     * Paged list of displayable campaigns plus a total count.
     *
     * Implementation uses `COUNT(*) OVER ()` to derive the total in
     * the same round-trip; the total is 0 when the result set is empty.
     *
     * @return array{items: list<CampaignSummaryDTO>, total: int}
     */
    public function listDisplayable(int $perPage, int $offset): array;

    /**
     * Featured campaigns (is_featured = TRUE), most relevant first.
     *
     * @return list<CampaignSummaryDTO>
     */
    public function listFeatured(int $limit): array;
}
