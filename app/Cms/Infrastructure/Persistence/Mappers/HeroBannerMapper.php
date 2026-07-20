<?php

declare(strict_types=1);

namespace App\Cms\Infrastructure\Persistence\Mappers;

use App\Cms\Domain\Entities\HeroBanner;

/**
 * HeroBannerMapper — thin row↔entity delegator (spec §5.4).
 *
 * Doctrine: keeps `HeroBanner::fromRow()` and `HeroBanner::toArray()` as
 * the canonical seams (every repository already uses them). This class
 * exists so the mapping is independently testable without booting the
 * kernel or resolving a repository.
 */
final class HeroBannerMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function toRow(HeroBanner $entity): array
    {
        return $entity->toArray();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromRow(array $row): HeroBanner
    {
        return HeroBanner::fromRow($row);
    }
}
