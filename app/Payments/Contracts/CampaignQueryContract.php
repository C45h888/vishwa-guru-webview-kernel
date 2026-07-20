<?php

declare(strict_types=1);

namespace App\Payments\Contracts;

use App\Payments\Domain\DTOs\CampaignDTO;

/**
 * Cross-kernel bridge contract — CMS consumes campaigns from Payments.
 *
 * The CMS kernel imports this interface ONLY. The implementation lives in
 * the Payments kernel (`App\Payments\Infrastructure\Adapters\CampaignQueryAdapter`).
 *
 * Doctrine (cms-architecture.md §7):
 *   - Shape A bridge: producing kernel owns the contract.
 *   - Consumers never import Payments\Services or Payments\Infrastructure
 *     directly — only the contract surface is sanctioned.
 *   - Methods return null/false (no throw) on missing or non-displayable
 *     rows so callers can degrade gracefully.
 *
 * @see App\Payments\Infrastructure\Adapters\CampaignQueryAdapter
 */
interface CampaignQueryContract
{
    /**
     * Whether the campaign with the given id is currently displayable
     * (exists, not soft-deleted, state in {active, completed}).
     *
     * @param  string  $campaignId  campaigns.id value
     */
    public function isDisplayable(string $campaignId): bool;

    /**
     * Look up an active campaign by id. Returns null when the campaign
     * does not exist, is soft-deleted, or is not in a displayable state.
     *
     * @param  string  $campaignId  campaigns.id value
     */
    public function findActiveById(string $campaignId): ?CampaignDTO;
}