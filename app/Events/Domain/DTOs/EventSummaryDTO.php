<?php

declare(strict_types=1);

namespace App\Events\Domain\DTOs;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * List-row DTO for public event reads.
 *
 * Returned by `EventsQueryContract::listUpcoming()`, `listPast()`,
 * `listFeatured()`, `listAll()`, and embedded in `EventDetailDTO::$related`.
 *
 * Doctrine: `isUpcoming` is computed at DTO build time as
 * `$startsAt >= $now`. `bannerFileId` is opaque in V1.
 */
final readonly class EventSummaryDTO
{
    public function __construct(
        public string $id,
        public string $slug,
        public string $title,
        public ?string $shortDescription,
        public DateTimeImmutable $startsAt,
        public ?DateTimeImmutable $endsAt,
        public string $timezone,
        public ?string $venue,
        public ?string $venueAddress,
        public string $state,
        public bool $isFeatured,
        public bool $isUpcoming,
        public ?string $bannerFileId,
    ) {
        if ($id === '') {
            throw new InvalidArgumentException('EventSummaryDTO id cannot be empty');
        }
        if ($slug === '') {
            throw new InvalidArgumentException('EventSummaryDTO slug cannot be empty');
        }
        if ($title === '') {
            throw new InvalidArgumentException('EventSummaryDTO title cannot be empty');
        }
        if ($timezone === '') {
            throw new InvalidArgumentException('EventSummaryDTO timezone cannot be empty');
        }
        if ($state === '') {
            throw new InvalidArgumentException('EventSummaryDTO state cannot be empty');
        }
    }

    /**
     * Compute the canonical isUpcoming flag from a timestamp + reference.
     */
    public static function isUpcomingAt(DateTimeImmutable $startsAt, DateTimeImmutable $now): bool
    {
        return $startsAt >= $now;
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
            'starts_at'           => $this->startsAt->format(DATE_ATOM),
            'ends_at'             => $this->endsAt?->format(DATE_ATOM),
            'timezone'            => $this->timezone,
            'venue'               => $this->venue,
            'venue_address'       => $this->venueAddress,
            'state'               => $this->state,
            'is_featured'         => $this->isFeatured,
            'is_upcoming'         => $this->isUpcoming,
            'banner_file_id'      => $this->bannerFileId,
        ];
    }
}
