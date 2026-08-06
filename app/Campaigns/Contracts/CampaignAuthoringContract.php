<?php

declare(strict_types=1);

namespace App\Campaigns\Contracts;

use App\Campaigns\Domain\DTOs\CampaignDetailDTO;

/**
 * CampaignAuthoringContract — admin authoring surface for campaigns.
 *
 * Doctrine (AGENTS.md §"Phase 4: Admin Kernel"):
 *   - This contract is the canonical writing surface for campaigns.
 *     Admin controllers depend on it, NEVER on the repository directly.
 *   - Methods accept domain DTOs as inputs (CampaignDraftInput /
 *     CampaignUpdateInput) so the controller layer never touches
 *     raw column arrays.
 *   - The service stamps `created_by` / `updated_by` from the current
 *     authenticated admin user. The controller does NOT pass these in.
 *   - State is validated against the allowed set (draft|active|completed)
 *     at the FormRequest boundary. No state machine in V1.
 *   - Slug uniqueness is enforced at the storage layer (partial
 *     unique index `campaigns_slug_live_idx`). The service surfaces
 *     a DuplicateCampaignSlugException when the index trips.
 *
 * Bound in CampaignsServiceProvider::register() to
 * App\Campaigns\Services\CampaignAuthoringService.
 */
interface CampaignAuthoringContract
{
    /**
     * Create a new campaign. Default state is 'draft'; admin flips to
     * 'active' via the edit form once the campaign is ready to surface
     * on the public site.
     *
     * @throws \App\Campaigns\Domain\Exceptions\DuplicateCampaignSlugException
     */
    public function create(CampaignDraftInput $input): CampaignDetailDTO;

    /**
     * Update an existing campaign. Returns null when the row does
     * not exist. The DTO carries every field — the service diffs
     * against the persisted row to avoid rewriting unchanged values.
     *
     * @throws \App\Campaigns\Domain\Exceptions\DuplicateCampaignSlugException
     */
    public function update(string $id, CampaignUpdateInput $input): ?CampaignDetailDTO;
}

/**
 * CampaignDraftInput — input DTO for creating a new campaign.
 *
 * Lives next to CampaignAuthoringContract so the contract surface
 * (and the controllers that depend on it) doesn't have to reach into
 * Domain\DTOs to know the input shape.
 */
final readonly class CampaignDraftInput
{
    public function __construct(
        public string $slug,
        public string $title,
        public ?string $description,
        public ?string $shortDescription,
        public string $category,
        public string $currencyCode,
        public ?int $targetAmountMinor,
        public string $state,
        public ?string $startsAt,
        public ?string $endsAt,
        public bool $isFeatured,
        public int $displayOrder,
        public ?string $coverImageFileId,
        /** @var array<string, mixed> */
        public array $metadata,
        public string $createdBy,
    ) {
    }
}

/**
 * CampaignUpdateInput — input DTO for updating a campaign.
 *
 * Carries the full set of mutable columns; the service diffs against
 * the persisted row to avoid rewriting unchanged values. Keeping the
 * DTO "wide" (rather than nullable-per-field) simplifies FormRequest
 * validation: every field is either present (with the admin's chosen
 * value) or the DTO simply isn't constructed.
 */
final readonly class CampaignUpdateInput
{
    public function __construct(
        public string $slug,
        public string $title,
        public ?string $description,
        public ?string $shortDescription,
        public string $category,
        public string $currencyCode,
        public ?int $targetAmountMinor,
        public string $state,
        public ?string $startsAt,
        public ?string $endsAt,
        public bool $isFeatured,
        public int $displayOrder,
        public ?string $coverImageFileId,
        /** @var array<string, mixed> */
        public array $metadata,
        public string $updatedBy,
    ) {
    }
}
