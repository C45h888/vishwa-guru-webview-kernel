<?php

declare(strict_types=1);

namespace App\Events\Contracts;

use App\Events\Domain\DTOs\EventDetailDTO;

/**
 * EventAuthoringContract — admin authoring surface for events.
 *
 * Doctrine (mirrors CampaignAuthoringContract):
 *   - The canonical writing surface for events. Admin controllers
 *     depend on this contract, NEVER on the repository directly.
 *   - Methods accept domain DTOs (EventDraftInput / EventUpdateInput)
 *     so the controller never touches raw column arrays.
 *   - The service stamps `created_by` / `updated_by` from the current
 *     authenticated admin user. Controllers do NOT pass these in.
 *   - State is validated against [draft, published, completed] at the
 *     FormRequest boundary. No state machine in V1.
 *   - Slug uniqueness is enforced at the storage layer (partial unique
 *     index `events_slug_live_idx`). The service surfaces
 *     DuplicateEventSlugException when the index trips.
 *   - End-event is a separate action (`end($id)`) because it's the
 *     user-facing button that admins click to retire an event. The
 *     service is idempotent: already-completed events return as-is.
 *
 * Bound in EventsServiceProvider::register() to
 * App\Events\Services\EventAuthoringService.
 */
interface EventAuthoringContract
{
    /**
     * Create a new event. Default state is 'draft'; admin flips to
     * 'published' via the edit form once the event is ready to surface.
     *
     * @throws \App\Events\Domain\Exceptions\DuplicateEventSlugException
     */
    public function create(EventDraftInput $input): EventDetailDTO;

    /**
     * Update an existing event. Returns null when the row does not
     * exist.
     *
     * @throws \App\Events\Domain\Exceptions\DuplicateEventSlugException
     */
    public function update(string $id, EventUpdateInput $input): ?EventDetailDTO;

    /**
     * End an event: state → 'completed', completed_at = now. Idempotent.
     * Returns null when the row does not exist.
     */
    public function end(string $id): ?EventDetailDTO;
}

/**
 * EventDraftInput — input DTO for creating a new event.
 *
 * Lives next to EventAuthoringContract so the contract surface (and
 * the controllers that depend on it) doesn't have to reach into
 * Domain\DTOs to know the input shape.
 */
final readonly class EventDraftInput
{
    public function __construct(
        public string $slug,
        public string $title,
        public ?string $description,
        public ?string $shortDescription,
        public ?string $bannerFileId,
        public string $startsAt,
        public ?string $endsAt,
        public string $timezone,
        public ?string $venue,
        public ?string $venueAddress,
        public string $state,
        public bool $isFeatured,
        public int $displayOrder,
        /** @var array<string, mixed> */
        public array $metadata,
        public string $createdBy,
    ) {
    }
}

/**
 * EventUpdateInput — input DTO for updating an event.
 */
final readonly class EventUpdateInput
{
    public function __construct(
        public string $slug,
        public string $title,
        public ?string $description,
        public ?string $shortDescription,
        public ?string $bannerFileId,
        public string $startsAt,
        public ?string $endsAt,
        public string $timezone,
        public ?string $venue,
        public ?string $venueAddress,
        public string $state,
        public bool $isFeatured,
        public int $displayOrder,
        /** @var array<string, mixed> */
        public array $metadata,
        public string $updatedBy,
    ) {
    }
}
