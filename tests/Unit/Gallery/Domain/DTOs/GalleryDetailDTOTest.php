<?php

declare(strict_types=1);

namespace Tests\Unit\Gallery\Domain\DTOs;

use App\Gallery\Domain\DTOs\GalleryDetailDTO;
use App\Gallery\Domain\DTOs\GalleryImageDTO;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GalleryDetailDTOTest extends TestCase
{
    public function testImagesListAlwaysNonNull(): void
    {
        $dto = new GalleryDetailDTO(
            id: 'gallery_x',
            slug: 's',
            title: 't',
            shortDescription: null,
            description: null,
            coverImageFileId: null,
            imageCount: 0,
            isFeatured: false,
            displayOrder: 0,
            publishedAt: null,
            state: 'published',
            metadata: [],
            createdAt: new DateTimeImmutable(),
            images: [],
        );
        $this->assertSame([], $dto->images);
    }

    public function testImagesListAcceptsGalleryImageDtos(): void
    {
        $image = new GalleryImageDTO(
            id: 'image_x',
            galleryId: 'gallery_x',
            fileAssetId: null,
            title: 'Sunset',
            caption: 'Temple at sunset',
            altText: 'Temple silhouette at sunset',
            photographerCredit: null,
            takenAt: null,
            displayOrder: 1,
            isFeatured: false,
            publishedAt: null,
        );
        $dto = new GalleryDetailDTO(
            id: 'gallery_x',
            slug: 's',
            title: 't',
            shortDescription: null,
            description: null,
            coverImageFileId: null,
            imageCount: 1,
            isFeatured: false,
            displayOrder: 0,
            publishedAt: null,
            state: 'published',
            metadata: [],
            createdAt: new DateTimeImmutable(),
            images: [$image],
        );
        $this->assertCount(1, $dto->images);
        $this->assertSame('image_x', $dto->images[0]->id);
    }

    public function testToArrayShape(): void
    {
        $dto = new GalleryDetailDTO(
            id: 'gallery_x',
            slug: 's',
            title: 't',
            shortDescription: null,
            description: null,
            coverImageFileId: null,
            imageCount: 0,
            isFeatured: false,
            displayOrder: 0,
            publishedAt: null,
            state: 'published',
            metadata: [],
            createdAt: new DateTimeImmutable(),
            images: [],
        );
        $arr = $dto->toArray();
        $this->assertArrayHasKey('images', $arr);
        $this->assertSame([], $arr['images']);
    }
}
