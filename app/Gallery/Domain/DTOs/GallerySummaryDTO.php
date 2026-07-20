<?php

declare(strict_types=1);

namespace App\Gallery\Domain\DTOs;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * List-row DTO for public gallery reads.
 *
 * Returned by `GalleryQueryContract::listDisplayable()` and
 * `listFeatured()`. Includes `imageCount` so the UI can render gallery
 * cards without a follow-up N+1 query per gallery.
 *
 * Doctrine: nullable fields mirror nullable DB columns. `coverImageFileId`
 * is opaque in V1.
 */
final readonly class GallerySummaryDTO
{
    public function __construct(
        public string $id,
        public string $slug,
        public string $title,
        public ?string $shortDescription,
        public ?string $coverImageFileId,
        public int $imageCount,
        public bool $isFeatured,
        public ?DateTimeImmutable $publishedAt,
        public string $state,
    ) {
        if ($id === '') {
            throw new InvalidArgumentException('GallerySummaryDTO id cannot be empty');
        }
        if ($slug === '') {
            throw new InvalidArgumentException('GallerySummaryDTO slug cannot be empty');
        }
        if ($title === '') {
            throw new InvalidArgumentException('GallerySummaryDTO title cannot be empty');
        }
        if ($imageCount < 0) {
            throw new InvalidArgumentException(
                "GallerySummaryDTO imageCount cannot be negative, got: {$imageCount}"
            );
        }
        if ($state === '') {
            throw new InvalidArgumentException('GallerySummaryDTO state cannot be empty');
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
            'cover_image_file_id' => $this->coverImageFileId,
            'image_count'         => $this->imageCount,
            'is_featured'         => $this->isFeatured,
            'published_at'        => $this->publishedAt?->format(DATE_ATOM),
            'state'               => $this->state,
        ];
    }
}
