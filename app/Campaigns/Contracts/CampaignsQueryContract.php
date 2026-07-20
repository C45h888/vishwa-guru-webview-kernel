<?php

declare(strict_types=1);

namespace App\Campaigns\Contracts;

use App\Campaigns\Domain\DTOs\CampaignDetailDTO;
use App\Campaigns\Domain\DTOs\CampaignPagedResultDTO;
use App\Campaigns\Domain\DTOs\CampaignProgressDTO;
use App\Campaigns\Domain\DTOs\CampaignSummaryDTO;

/**
 * Public read API for temple campaigns.
 *
 * Implemented by `App\Campaigns\Services\CampaignsQueryService` and
 * consumed by Sub-project 3 (UI) via `ModuleContract::dependencies()`.
 *
 * Doctrine (cms-architecture.md §7):
 *   - The producing kernel owns the contract.
 *   - Consumers never import Campaigns\Services or Campaigns\Infrastructure
 *     directly — only the contract surface is sanctioned.
 *   - Methods return null / empty list (no throw) on missing or
 *     non-displayable rows so callers can degrade gracefully.
 *
 * Displayable = state IN ('active','completed') AND deleted_at IS NULL.
 */
interface CampaignsQueryContract
{
    /**
     * Paged list of publicly displayable campaigns.
     *
     * Ordering: featured first (is_featured DESC), then display_order
     * ASC, then starts_at DESC NULLS LAST, id ASC as a deterministic
     * tiebreaker.
     */
    public function listDisplayable(int $page = 1, int $perPage = 12): CampaignPagedResultDTO;

    /**
     * Featured campaigns for the homepage tile set.
     *
     * @return list<CampaignSummaryDTO>
     */
    public function listFeatured(int $limit = 6): array;

    /**
     * Look up a single displayable campaign by its primary-key id.
     * Returns null when missing, soft-deleted, or non-displayable.
     */
    public function findById(string $id): ?CampaignDetailDTO;

    /**
     * Look up a single displayable campaign by slug.
     * Returns null when missing, soft-deleted, or non-displayable.
     */
    public function findBySlug(string $slug): ?CampaignDetailDTO;

    /**
     * Progress rollup aggregated over donations in successful states
     * (`payment_verified`, `receipt_generated`, `completed`).
     *
     * Returns one CampaignProgressDTO per `currency_code` present in
     * the donations table for this campaign. Multi-currency safety:
     * amounts NEVER mix currencies; consumers receive one row each.
     *
     * @return list<CampaignProgressDTO>
     */
    public function progressFor(string $campaignId): array;
}
