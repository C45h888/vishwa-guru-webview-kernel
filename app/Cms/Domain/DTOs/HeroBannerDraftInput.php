<?php

declare(strict_types=1);

namespace App\Cms\Domain\DTOs;

use App\Persistence\ValueObjects\EntityId;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Input DTO for creating a new hero banner in DRAFT.
 */
final readonly class HeroBannerDraftInput
{
    public function __construct(
        public ?string $title,
        public ?string $subtitle,
        public ?string $ctaLabel,
        public ?string $ctaUrl,
        public ?EntityId $imageFileId,
        public ?EntityId $mobileImageFileId,
        public int $displayOrder = 0,
        public ?DateTimeImmutable $startsAt = null,
        public ?DateTimeImmutable $endsAt = null,
        public ?string $createdBy = null,
    ) {
        if ($startsAt !== null && $endsAt !== null && $endsAt < $startsAt) {
            throw new InvalidArgumentException('HeroBannerDraftInput endsAt must be >= startsAt');
        }
        if ($displayOrder < 0) {
            throw new InvalidArgumentException("displayOrder cannot be negative (got {$displayOrder})");
        }
    }
}