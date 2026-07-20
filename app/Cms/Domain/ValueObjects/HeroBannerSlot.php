<?php

declare(strict_types=1);

namespace App\Cms\Domain\ValueObjects;

use App\Persistence\ValueObjects\EntityId;
use InvalidArgumentException;

/**
 * Pairing of a hero banner id with display metadata for attachment to
 * a Static Page. The actual m:n mapping is stored in `hero_banner_pages`;
 * this VO is the in-memory carrier when assembling a page.
 */
final readonly class HeroBannerSlot
{
    public function __construct(
        private readonly EntityId $bannerId,
        private readonly int $displayOrder = 0,
        private readonly int $weight = 100,
    ) {
        if ($displayOrder < 0) {
            throw new InvalidArgumentException(
                "HeroBannerSlot displayOrder cannot be negative (got {$displayOrder})"
            );
        }
        if ($weight < 0) {
            throw new InvalidArgumentException(
                "HeroBannerSlot weight cannot be negative (got {$weight})"
            );
        }
    }

    public function bannerId(): EntityId
    {
        return $this->bannerId;
    }

    public function displayOrder(): int
    {
        return $this->displayOrder;
    }

    public function weight(): int
    {
        return $this->weight;
    }
}