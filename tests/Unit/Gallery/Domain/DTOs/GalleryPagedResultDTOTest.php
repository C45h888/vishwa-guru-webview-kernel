<?php

declare(strict_types=1);

namespace Tests\Unit\Gallery\Domain\DTOs;

use App\Gallery\Domain\DTOs\GalleryPagedResultDTO;
use App\Gallery\Domain\DTOs\GallerySummaryDTO;
use PHPUnit\Framework\TestCase;

final class GalleryPagedResultDTOTest extends TestCase
{
    public function testEmptyItemsList(): void
    {
        $dto = new GalleryPagedResultDTO(
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
        $dto = new GalleryPagedResultDTO(
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
        new GalleryPagedResultDTO(
            items: [],
            total: -1,
            page: 1,
            perPage: 12,
            hasMore: false,
        );
    }

    public function testToArrayShape(): void
    {
        $summary = new GallerySummaryDTO(
            id: 'gallery_x',
            slug: 's',
            title: 't',
            shortDescription: null,
            coverImageFileId: null,
            imageCount: 0,
            isFeatured: false,
            publishedAt: null,
            state: 'published',
        );
        $dto = new GalleryPagedResultDTO(
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
