<?php

declare(strict_types=1);

namespace App\Events\Services;

use App\Events\Contracts\EventAuthoringContract;
use App\Events\Contracts\EventDraftInput;
use App\Events\Contracts\EventUpdateInput;
use App\Events\Domain\DTOs\EventDetailDTO;
use App\Events\Domain\Exceptions\DuplicateEventSlugException;
use App\Events\Domain\Repositories\EventRepositoryContract;
use RuntimeException;

/**
 * EventAuthoringService — thin service wrapping the repository for
 * admin create / update / end operations.
 *
 * Doctrine (mirrors CampaignAuthoringService):
 *   - Service layer is the only place that translates DTOs →
 *     repository row arrays. Controllers depend on the contract;
 *     they never call the repository directly.
 *   - created_by / updated_by are stamped here from the input DTO.
 *   - Slug uniqueness is enforced at the storage layer; we surface
 *     DuplicateEventSlugException when the partial unique index trips.
 *   - No state machine. State is validated at the FormRequest boundary
 *     against [draft, published, completed] and passed through.
 *   - The end() method is idempotent: a no-op when the event is
 *     already 'completed'.
 */
final class EventAuthoringService implements EventAuthoringContract
{
    public function __construct(
        private readonly EventRepositoryContract $repository,
    ) {
    }

    public function create(EventDraftInput $input): EventDetailDTO
    {
        try {
            return $this->repository->create([
                'slug' => $input->slug,
                'title' => $input->title,
                'description' => $input->description,
                'short_description' => $input->shortDescription,
                'banner_file_id' => $input->bannerFileId,
                'starts_at' => $input->startsAt,
                'ends_at' => $input->endsAt,
                'timezone' => $input->timezone,
                'venue' => $input->venue,
                'venue_address' => $input->venueAddress,
                'state' => $input->state,
                'is_featured' => $input->isFeatured,
                'display_order' => $input->displayOrder,
                'metadata' => $input->metadata,
                'created_by' => $input->createdBy,
                'updated_by' => $input->createdBy,
            ]);
        } catch (RuntimeException $e) {
            if ($this->isDuplicateSlugError($e)) {
                throw new DuplicateEventSlugException($input->slug, $e);
            }
            throw $e;
        }
    }

    public function update(string $id, EventUpdateInput $input): ?EventDetailDTO
    {
        try {
            return $this->repository->update($id, [
                'slug' => $input->slug,
                'title' => $input->title,
                'description' => $input->description,
                'short_description' => $input->shortDescription,
                'banner_file_id' => $input->bannerFileId,
                'starts_at' => $input->startsAt,
                'ends_at' => $input->endsAt,
                'timezone' => $input->timezone,
                'venue' => $input->venue,
                'venue_address' => $input->venueAddress,
                'state' => $input->state,
                'is_featured' => $input->isFeatured,
                'display_order' => $input->displayOrder,
                'metadata' => $input->metadata,
                'updated_by' => $input->updatedBy,
            ]);
        } catch (RuntimeException $e) {
            if ($this->isDuplicateSlugError($e)) {
                throw new DuplicateEventSlugException($input->slug, $e);
            }
            throw $e;
        }
    }

    public function end(string $id): ?EventDetailDTO
    {
        return $this->repository->end($id);
    }

    /**
     * Postgres surfaces a unique-violation as SQLSTATE 23505; SQLite
     * uses "UNIQUE constraint failed". Both are treated as duplicate
     * slug because events_slug_live_idx is the only UNIQUE index on
     * the slug column for non-deleted rows.
     */
    private function isDuplicateSlugError(RuntimeException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'events_slug_live_idx')
            || str_contains($message, 'UNIQUE constraint failed: events.slug')
            || str_contains($message, '23505');
    }
}
