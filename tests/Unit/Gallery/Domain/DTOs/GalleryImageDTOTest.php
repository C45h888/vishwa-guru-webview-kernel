<?php

declare(strict_types=1);

namespace Tests\Unit\Gallery\Domain\DTOs;

use App\Gallery\Domain\DTOs\GalleryImageDTO;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GalleryImageDTOTest extends TestCase
{
    public function testAllOptionalFieldsNullable(): void
    {
        $dto = new GalleryImageDTO(
            id: 'image_x',
            galleryId: 'gallery_x',
            fileAssetId: null,
            title: null,
            caption: null,
            altText: null,
            photographerCredit: null,
            takenAt: null,
            displayOrder: 0,
            isFeatured: false,
            publishedAt: null,
        );
        $this->assertNull($dto->fileAssetId);
        $this->assertNull($dto->title);
        $this->assertNull($dto->caption);
        $this->assertNull($dto->altText);
        $this->assertNull($dto->photographerCredit);
        $this->assertNull($dto->takenAt);
        $this->assertNull($dto->publishedAt);
    }

    public function testEmptyIdRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new GalleryImageDTO(
            id: '',
            galleryId: 'gallery_x',
            fileAssetId: null,
            title: null,
            caption: null,
            altText: null,
            photographerCredit: null,
            takenAt: null,
            displayOrder: 0,
            isFeatured: false,
            publishedAt: null,
        );
    }

    public function testTakenAtDateOnlyFormat(): void
    {
        $dto = new GalleryImageDTO(
            id: 'image_x',
            galleryId: 'gallery_x',
            fileAssetId: null,
            title: null,
            caption: null,
            altText: null,
            photographerCredit: null,
            takenAt: new DateTimeImmutable('2025-12-01'),
            displayOrder: 0,
            isFeatured: false,
            publishedAt: null,
        );
        $arr = $dto->toArray();
        $this->assertSame('2025-12-01', $arr['taken_at']);
    }
}
