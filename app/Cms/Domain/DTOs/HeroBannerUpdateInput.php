<?php

declare(strict_types=1);

namespace App\Cms\Domain\DTOs;

use App\Persistence\ValueObjects\EntityId;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Input DTO for updating an existing hero banner.
 *
 * All fields nullable. Service merges non-null fields onto the entity.
 */
final readonly class HeroBannerUpdateInput
{
    public function __construct(
        public ?string $title = null,
        public ?string $subtitle = null,
        public ?string $ctaLabel = null,
        public ?string $ctaUrl = null,
        public ?EntityId $imageFileId = null,
        public ?EntityId $mobileImageFileId = null,
        public ?int $displayOrder = null,
        public ?DateTimeImmutable $startsAt = null,
        public ?DateTimeImmutable $endsAt = null,
        public ?string $updatedBy = null,
    ) {
        if ($startsAt !== null && $endsAt !== null && $endsAt < $startsAt) {
            throw new InvalidArgumentException('HeroBannerUpdateInput endsAt must be >= startsAt');
        }
        if ($displayOrder !== null && $displayOrder < 0) {
            throw new InvalidArgumentException("displayOrder cannot be negative (got {$displayOrder})");
        }
    }
}