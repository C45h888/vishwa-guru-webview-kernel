<?php

declare(strict_types=1);

namespace Tests\Unit\Events\Domain\DTOs;

use App\Events\Domain\DTOs\EventPagedResultDTO;
use App\Events\Domain\DTOs\EventSummaryDTO;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class EventPagedResultDTOTest extends TestCase
{
    public function testEmptyItemsList(): void
    {
        $dto = new EventPagedResultDTO(
            items: [],
            total: 0,
            page: 1,
            perPage: 12,
            hasMore: false,
        );
        $this->assertSame([], $dto->items);
        $this->assertSame(0, $dto->total);
        $this->assertFalse($dto->hasMore);
    }

    public function testHasMoreTrueWhenMorePages(): void
    {
        $dto = new EventPagedResultDTO(
            items: [],
            total: 25,
            page: 1,
            perPage: 12,
            hasMore: true,
        );
        $this->assertTrue($dto->hasMore);
    }

    public function testNegativeTotalRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new EventPagedResultDTO(
            items: [],
            total: -1,
            page: 1,
            perPage: 12,
            hasMore: false,
        );
    }

    public function testToArrayShape(): void
    {
        $summary = new EventSummaryDTO(
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
        $dto = new EventPagedResultDTO(
            items: [$summary],
            total: 1,
            page: 1,
            perPage: 12,
            hasMore: false,
        );
        $arr = $dto->toArray();
        $this->assertSame(['items', 'total', 'page', 'per_page', 'has_more'], array_keys($arr));
    }
}
