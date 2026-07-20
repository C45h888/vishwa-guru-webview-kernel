<?php

declare(strict_types=1);

namespace Tests\Unit\Events\Domain\DTOs;

use App\Events\Domain\DTOs\EventDetailDTO;
use App\Events\Domain\DTOs\EventSummaryDTO;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class EventDetailDTOTest extends TestCase
{
    private function makeDto(?DateTimeImmutable $completedAt = null): EventDetailDTO
    {
        return new EventDetailDTO(
            id: 'event_x',
            slug: 's',
            title: 't',
            shortDescription: null,
            description: 'long description',
            startsAt: new DateTimeImmutable('2026-04-15T09:00:00+05:30'),
            endsAt: null,
            timezone: 'Asia/Kolkata',
            venue: null,
            venueAddress: null,
            state: 'published',
            isFeatured: false,
            isUpcoming: true,
            bannerFileId: null,
            publishedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
            completedAt: $completedAt,
            displayOrder: 0,
            metadata: [],
            createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
            related: [],
        );
    }

    public function testRelatedListAlwaysArray(): void
    {
        $dto = $this->makeDto();
        $this->assertSame([], $dto->related);
    }

    public function testRelatedListAcceptsEventSummaries(): void
    {
        $summary = new EventSummaryDTO(
            id: 'event_y',
            slug: 'related-event',
            title: 'Related',
            shortDescription: null,
            startsAt: new DateTimeImmutable('2026-05-01T09:00:00+05:30'),
            endsAt: null,
            timezone: 'Asia/Kolkata',
            venue: null,
            venueAddress: null,
            state: 'published',
            isFeatured: false,
            isUpcoming: true,
            bannerFileId: null,
        );
        $dto = new EventDetailDTO(
            id: 'event_x',
            slug: 's',
            title: 't',
            shortDescription: null,
            description: null,
            startsAt: new DateTimeImmutable('2026-04-15T09:00:00+05:30'),
            endsAt: null,
            timezone: 'Asia/Kolkata',
            venue: null,
            venueAddress: null,
            state: 'published',
            isFeatured: false,
            isUpcoming: true,
            bannerFileId: null,
            publishedAt: null,
            completedAt: null,
            displayOrder: 0,
            metadata: [],
            createdAt: new DateTimeImmutable(),
            related: [$summary],
        );
        $this->assertCount(1, $dto->related);
        $this->assertSame('event_y', $dto->related[0]->id);
    }

    public function testCompletedAtNullable(): void
    {
        $this->assertNull($this->makeDto(null)->completedAt);
        $ts = new DateTimeImmutable('2026-04-16T00:00:00+00:00');
        $this->assertSame($ts, $this->makeDto($ts)->completedAt);
    }
}
