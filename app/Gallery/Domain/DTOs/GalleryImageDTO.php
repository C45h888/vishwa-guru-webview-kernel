<?php

declare(strict_types=1);

namespace App\Gallery\Domain\DTOs;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Single gallery-image DTO.
 *
 * Returned as part of `GalleryDetailDTO::$images`. Read-only and
 * crosses the kernel boundary to the UI layer.
 *
 * Doctrine: nullable fields mirror nullable DB columns. `fileAssetId`
 * is opaque in V1 (URL resolution belongs to a future file-asset
 * kernel — Phase 4).
 */
final readonly class GalleryImageDTO
{
    public function __construct(
        public string $id,
        public string $galleryId,
        public ?string $fileAssetId,
        public ?string $title,
        public ?string $caption,
        public ?string $altText,
        public ?string $photographerCredit,
        public ?DateTimeImmutable $takenAt,
        public int $displayOrder,
        public bool $isFeatured,
        public ?DateTimeImmutable $publishedAt,
    ) {
        if ($id === '') {
            throw new InvalidArgumentException('GalleryImageDTO id cannot be empty');
        }
        if ($galleryId === '') {
            throw new InvalidArgumentException('GalleryImageDTO galleryId cannot be empty');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'                  => $this->id,
            'gallery_id'          => $this->galleryId,
            'file_asset_id'       => $this->fileAssetId,
            'title'               => $this->title,
            'caption'             => $this->caption,
            'alt_text'            => $this->altText,
            'photographer_credit' => $this->photographerCredit,
            'taken_at'            => $this->takenAt?->format('Y-m-d'),
            'display_order'       => $this->displayOrder,
            'is_featured'         => $this->isFeatured,
            'published_at'        => $this->publishedAt?->format(DATE_ATOM),
        ];
    }
}
