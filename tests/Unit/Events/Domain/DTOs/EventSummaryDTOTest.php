<?php

declare(strict_types=1);

namespace Tests\Unit\Events\Domain\DTOs;

use App\Events\Domain\DTOs\EventSummaryDTO;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class EventSummaryDTOTest extends TestCase
{
    public function testConstructionWithValidArgs(): void
    {
        $dto = new EventSummaryDTO(
            id: 'event_01ARZ3NDEKTSV4RRFFQ69G5FAV',
            slug: 'annual-puja-2026',
            title: 'Annual Puja 2026',
            shortDescription: 'Join us for the annual puja.',
            startsAt: new DateTimeImmutable('2026-04-15T09:00:00+05:30'),
            endsAt: new DateTimeImmutable('2026-04-15T17:00:00+05:30'),
            timezone: 'Asia/Kolkata',
            venue: 'Main Temple Hall',
            venueAddress: '123 Temple Road',
            state: 'published',
            isFeatured: true,
            isUpcoming: true,
            bannerFileId: 'file_asset_01HBanner',
        );
        $this->assertSame('event_01ARZ3NDEKTSV4RRFFQ69G5FAV', $dto->id);
        $this->assertSame('Asia/Kolkata', $dto->timezone);
        $this->assertTrue($dto->isFeatured);
        $this->assertTrue($dto->isUpcoming);
    }

    public function testIsUpcomingAtComputation(): void
    {
        $future = new DateTimeImmutable('2030-01-01T00:00:00+00:00');
        $past = new DateTimeImmutable('2020-01-01T00:00:00+00:00');
        $now = new DateTimeImmutable('2025-01-01T00:00:00+00:00');

        $this->assertTrue(EventSummaryDTO::isUpcomingAt($future, $now));
        $this->assertFalse(EventSummaryDTO::isUpcomingAt($past, $now));
        $this->assertTrue(EventSummaryDTO::isUpcomingAt($now, $now)); // starts exactly at now → upcoming
    }

    public function testTimezoneAlwaysString(): void
    {
        $dto = new EventSummaryDTO(
            id: 'event_x',
            slug: 's',
            title: 't',
            shortDescription: null,
            startsAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
            endsAt: null,
            timezone: 'UTC',
            venue: null,
            venueAddress: null,
            state: 'draft',
            isFeatured: false,
            isUpcoming: false,
            bannerFileId: null,
        );
        $this->assertSame('UTC', $dto->timezone);
    }

    public function testToArrayShape(): void
    {
        $dto = new EventSummaryDTO(
            id: 'event_x',
            slug: 's',
            title: 't',
            shortDescription: null,
            startsAt: new DateTimeImmutable('2026-04-15T09:00:00+05:30'),
            endsAt: null,
            timezone: 'Asia/Kolkata',
            venue: null,
            venueAddress: null,
            state: 'published',
            isFeatured: false,
            isUpcoming: true,
            bannerFileId: null,
        );
        $arr = $dto->toArray();
        $this->assertSame(
            [
                'id', 'slug', 'title', 'short_description', 'starts_at', 'ends_at',
                'timezone', 'venue', 'venue_address', 'state', 'is_featured',
                'is_upcoming', 'banner_file_id',
            ],
            array_keys($arr),
        );
        $this->assertTrue($arr['is_upcoming']);
        $this->assertNull($arr['ends_at']);
    }

    public function testEmptyIdRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new EventSummaryDTO(
            id: '',
            slug: 's',
            title: 't',
            shortDescription: null,
            startsAt: new DateTimeImmutable(),
            endsAt: null,
            timezone: 'UTC',
            venue: null,
            venueAddress: null,
            state: 'draft',
            isFeatured: false,
            isUpcoming: false,
            bannerFileId: null,
        );
    }
}
