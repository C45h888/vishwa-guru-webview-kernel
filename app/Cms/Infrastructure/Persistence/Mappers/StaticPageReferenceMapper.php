<?php

declare(strict_types=1);

namespace App\Cms\Infrastructure\Persistence\Mappers;

use App\Cms\Domain\Entities\StaticPageReference;

/**
 * StaticPageReferenceMapper — thin row↔entity delegator (spec §5.4).
 *
 * Note: `static_page_references` has no `updated_at` or `deleted_at`
 * column (the table is junction-like; `StaticPageReference::fromRow`
 * accordingly does not require those keys). The mapper respects the
 * same canonical factory used by `EloquentStaticPageReferenceRepository`.
 */
final class StaticPageReferenceMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function toRow(StaticPageReference $entity): array
    {
        return $entity->toArray();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromRow(array $row): StaticPageReference
    {
        return StaticPageReference::fromRow($row);
    }
}
