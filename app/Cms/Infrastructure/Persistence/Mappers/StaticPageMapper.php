<?php

declare(strict_types=1);

namespace App\Cms\Infrastructure\Persistence\Mappers;

use App\Cms\Domain\Entities\StaticPage;

/**
 * StaticPageMapper — thin row↔entity delegator (spec §5.4).
 *
 * Doctrine: keeps `StaticPage::fromRow()` and `StaticPage::toArray()` as
 * the canonical hydration/serialisation seams (every repository already
 * uses them). This class exists so the row↔entity round-trip is
 * independently testable in isolation, without booting the kernel or
 * resolving a repository via the container.
 *
 * Reuse targets: prefer `StaticPageMapper::fromRow($row)` over calling
 * `StaticPage::fromRow($row)` directly in test code so the test name
 * expresses intent ("mapper round-trip") rather than implementation.
 */
final class StaticPageMapper
{
    /**
     * Entity → row. Returns the same shape `StaticPage::toArray()` produces.
     *
     * @return array<string, mixed>
     */
    public static function toRow(StaticPage $entity): array
    {
        return $entity->toArray();
    }

    /**
     * Row → entity. Delegates to `StaticPage::fromRow()`, which is the
     * canonical factory used by every concrete repository.
     *
     * @param  array<string, mixed>  $row
     */
    public static function fromRow(array $row): StaticPage
    {
        return StaticPage::fromRow($row);
    }
}
