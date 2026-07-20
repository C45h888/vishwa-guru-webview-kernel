<?php

declare(strict_types=1);

namespace App\Gallery\Domain\DTOs;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Full-detail DTO for the public gallery page.
 *
 * Returned by `GalleryQueryContract::findById()` and `findBySlug()`.
 * Includes the ordered list of `GalleryImageDTO`s so the UI can render
 * a gallery page in a single round-trip.
 *
 * Doctrine: images is always a `list` (never null) — empty lists are
 * valid (a gallery can exist without published images yet).
 */
final readonly class GalleryDetailDTO
{
    /**
     * @param  list<GalleryImageDTO>  $images
     */
    public function __construct(
        public string $id,
        public string $slug,
        public string $title,
        public ?string $shortDescription,
        public ?string $description,
        public ?string $coverImageFileId,
        public int $imageCount,
        public bool $isFeatured,
        public int $displayOrder,
        public ?DateTimeImmutable $publishedAt,
        public string $state,
        /** @var array<string, mixed> */
        public array $metadata,
        public DateTimeImmutable $createdAt,
        public array $images,
    ) {
        if ($id === '') {
            throw new InvalidArgumentException('GalleryDetailDTO id cannot be empty');
        }
        if ($slug === '') {
            throw new InvalidArgumentException('GalleryDetailDTO slug cannot be empty');
        }
        if ($title === '') {
            throw new InvalidArgumentException('GalleryDetailDTO title cannot be empty');
        }
        if ($imageCount < 0) {
            throw new InvalidArgumentException(
                "GalleryDetailDTO imageCount cannot be negative, got: {$imageCount}"
            );
        }
        if ($state === '') {
            throw new InvalidArgumentException('GalleryDetailDTO state cannot be empty');
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
            'description'         => $this->description,
            'cover_image_file_id' => $this->coverImageFileId,
            'image_count'         => $this->imageCount,
            'is_featured'         => $this->isFeatured,
            'display_order'       => $this->displayOrder,
            'published_at'        => $this->publishedAt?->format(DATE_ATOM),
            'state'               => $this->state,
            'metadata'            => $this->metadata,
            'created_at'          => $this->createdAt->format(DATE_ATOM),
            'images'              => array_map(static fn (GalleryImageDTO $i): array => $i->toArray(), $this->images),
        ];
    }
}
