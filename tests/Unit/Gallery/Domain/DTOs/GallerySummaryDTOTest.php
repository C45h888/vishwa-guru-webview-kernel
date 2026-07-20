<?php

declare(strict_types=1);

namespace Tests\Unit\Gallery\Domain\DTOs;

use App\Gallery\Domain\DTOs\GallerySummaryDTO;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class GallerySummaryDTOTest extends TestCase
{
    public function testConstructionWithValidArgs(): void
    {
        $dto = new GallerySummaryDTO(
            id: 'gallery_01HXYZ',
            slug: 'temple-festival-2025',
            title: 'Temple Festival 2025',
            shortDescription: 'Photos from this year\'s celebration.',
            coverImageFileId: 'file_asset_01HABC',
            imageCount: 24,
            isFeatured: true,
            publishedAt: new DateTimeImmutable('2025-12-01T00:00:00+00:00'),
            state: 'published',
        );
        $this->assertSame('gallery_01HXYZ', $dto->id);
        $this->assertSame(24, $dto->imageCount);
        $this->assertTrue($dto->isFeatured);
        $this->assertSame('published', $dto->state);
    }

    public function testEmptyIdRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new GallerySummaryDTO(
            id: '',
            slug: 's',
            title: 't',
            shortDescription: null,
            coverImageFileId: null,
            imageCount: 0,
            isFeatured: false,
            publishedAt: null,
            state: 'published',
        );
    }

    public function testNegativeImageCountRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new GallerySummaryDTO(
            id: 'gallery_x',
            slug: 's',
            title: 't',
            shortDescription: null,
            coverImageFileId: null,
            imageCount: -1,
            isFeatured: false,
            publishedAt: null,
            state: 'published',
        );
    }

    public function testToArrayShape(): void
    {
        $dto = new GallerySummaryDTO(
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
        $arr = $dto->toArray();
        $this->assertSame(
            [
                'id', 'slug', 'title', 'short_description', 'cover_image_file_id',
                'image_count', 'is_featured', 'published_at', 'state',
            ],
            array_keys($arr),
        );
    }
}
