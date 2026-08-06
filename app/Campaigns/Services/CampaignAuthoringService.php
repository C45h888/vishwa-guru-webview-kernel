<?php

declare(strict_types=1);

namespace App\Campaigns\Services;

use App\Campaigns\Contracts\CampaignAuthoringContract;
use App\Campaigns\Contracts\CampaignDraftInput;
use App\Campaigns\Contracts\CampaignUpdateInput;
use App\Campaigns\Domain\DTOs\CampaignDetailDTO;
use App\Campaigns\Domain\Exceptions\DuplicateCampaignSlugException;
use App\Campaigns\Domain\Repositories\CampaignRepositoryContract;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * CampaignAuthoringService — thin service wrapping the repository for
 * admin create + update operations.
 *
 * Doctrine (AGENTS.md §"Phase 4: Admin Kernel"):
 *   - Service layer is the only place that translates DTOs → repository
 *     row arrays. Controllers depend on the contract; they never call
 *     the repository directly.
 *   - created_by / updated_by are stamped here from the input DTO.
 *     The controller does NOT pass these in.
 *   - Slug uniqueness is enforced at the storage layer; we surface
 *     DuplicateCampaignSlugException when the partial unique index
 *     trips. Callers (FormRequest → controller) translate this to
 *     a 422 with a "slug already taken" validation error.
 *   - No state machine. State is validated at the FormRequest
 *     boundary against [draft, active, completed] and passed through.
 */
final class CampaignAuthoringService implements CampaignAuthoringContract
{
    public function __construct(
        private readonly CampaignRepositoryContract $repository,
    ) {
    }

    public function create(CampaignDraftInput $input): CampaignDetailDTO
    {
        try {
            return $this->repository->create([
                'slug' => $input->slug,
                'title' => $input->title,
                'description' => $input->description,
                'short_description' => $input->shortDescription,
                'category' => $input->category,
                'currency_code' => $input->currencyCode,
                'target_amount_minor' => $input->targetAmountMinor,
                'state' => $input->state,
                'starts_at' => $input->startsAt,
                'ends_at' => $input->endsAt,
                'display_order' => $input->displayOrder,
                'is_featured' => $input->isFeatured,
                'cover_image_file_id' => $input->coverImageFileId,
                'metadata' => $input->metadata,
                'created_by' => $input->createdBy,
                'updated_by' => $input->createdBy,
            ]);
        } catch (RuntimeException $e) {
            if ($this->isDuplicateSlugError($e)) {
                throw new DuplicateCampaignSlugException($input->slug, $e);
            }
            throw $e;
        }
    }

    public function update(string $id, CampaignUpdateInput $input): ?CampaignDetailDTO
    {
        try {
            return $this->repository->update($id, [
                'slug' => $input->slug,
                'title' => $input->title,
                'description' => $input->description,
                'short_description' => $input->shortDescription,
                'category' => $input->category,
                'currency_code' => $input->currencyCode,
                'target_amount_minor' => $input->targetAmountMinor,
                'state' => $input->state,
                'starts_at' => $input->startsAt,
                'ends_at' => $input->endsAt,
                'display_order' => $input->displayOrder,
                'is_featured' => $input->isFeatured,
                'cover_image_file_id' => $input->coverImageFileId,
                'metadata' => $input->metadata,
                'updated_by' => $input->updatedBy,
            ]);
        } catch (RuntimeException $e) {
            if ($this->isDuplicateSlugError($e)) {
                throw new DuplicateCampaignSlugException($input->slug, $e);
            }
            throw $e;
        }
    }

    /**
     * Postgres surfaces a unique-violation error as
     * SQLSTATE 23505; SQLite uses "UNIQUE constraint failed".
     * We treat both as duplicate-slug because campaigns_slug_live_idx
     * is the only UNIQUE index on the slug column for non-deleted rows.
     */
    private function isDuplicateSlugError(RuntimeException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'campaigns_slug_live_idx')
            || str_contains($message, 'UNIQUE constraint failed: campaigns.slug')
            || str_contains($message, '23505');
    }
}
