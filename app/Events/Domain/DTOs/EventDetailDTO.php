<?php

declare(strict_types=1);

namespace App\Events\Domain\DTOs;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Full-detail DTO for the public event page.
 *
 * Returned by `EventsQueryContract::findById()` and `findBySlug()`.
 * Extends the summary surface with the long description, metadata,
 * audit timestamps, and a small `related` list (limit 4 — future-dated
 * first, then most-recent past).
 *
 * Doctrine: related is always a `list` (never null) — empty lists are
 * valid (the only event in the system has nothing related).
 */
final readonly class EventDetailDTO
{
    /**
     * @param  list<EventSummaryDTO>  $related
     */
    public function __construct(
        public string $id,
        public string $slug,
        public string $title,
        public ?string $shortDescription,
        public ?string $description,
        public DateTimeImmutable $startsAt,
        public ?DateTimeImmutable $endsAt,
        public string $timezone,
        public ?string $venue,
        public ?string $venueAddress,
        public string $state,
        public bool $isFeatured,
        public bool $isUpcoming,
        public ?string $bannerFileId,
        public ?DateTimeImmutable $publishedAt,
        public ?DateTimeImmutable $completedAt,
        public int $displayOrder,
        /** @var array<string, mixed> */
        public array $metadata,
        public DateTimeImmutable $createdAt,
        public array $related,
    ) {
        if ($id === '') {
            throw new InvalidArgumentException('EventDetailDTO id cannot be empty');
        }
        if ($slug === '') {
            throw new InvalidArgumentException('EventDetailDTO slug cannot be empty');
        }
        if ($title === '') {
            throw new InvalidArgumentException('EventDetailDTO title cannot be empty');
        }
        if ($timezone === '') {
            throw new InvalidArgumentException('EventDetailDTO timezone cannot be empty');
        }
        if ($state === '') {
            throw new InvalidArgumentException('EventDetailDTO state cannot be empty');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'                  => $this->id,
            'slug'                => $this->slug,
            'title'               => $this->title,
            'short_description'   => $this->shortDescription,
            'description'         => $this->description,
            'starts_at'           => $this->startsAt->format(DATE_ATOM),
            'ends_at'             => $this->endsAt?->format(DATE_ATOM),
            'timezone'            => $this->timezone,
            'venue'               => $this->venue,
            'venue_address'       => $this->venueAddress,
            'state'               => $this->state,
            'is_featured'         => $this->isFeatured,
            'is_upcoming'         => $this->isUpcoming,
            'banner_file_id'      => $this->bannerFileId,
            'published_at'        => $this->publishedAt?->format(DATE_ATOM),
            'completed_at'        => $this->completedAt?->format(DATE_ATOM),
            'display_order'       => $this->displayOrder,
            'metadata'            => $this->metadata,
            'created_at'          => $this->createdAt->format(DATE_ATOM),
            'related'             => array_map(static fn (EventSummaryDTO $e): array => $e->toArray(), $this->related),
        ];
    }
}
