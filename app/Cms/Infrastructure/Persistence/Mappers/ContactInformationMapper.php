<?php

declare(strict_types=1);

namespace App\Cms\Infrastructure\Persistence\Mappers;

use App\Cms\Domain\Entities\ContactInformation;

/**
 * ContactInformationMapper — thin row↔entity delegator (spec §5.4).
 *
 * Doctrine: keeps `ContactInformation::fromRow()` and `toArray()` as the
 * canonical seams (used by every concrete repository). This class exists
 * so the row↔entity round-trip is independently testable without booting
 * the kernel or resolving a repository.
 *
 * Note: `ContactInformation::toArray()` encodes the `metadata` field as
 * a JSON string; `fromRow()` decodes it. The mapper round-trip respects
 * this — `toRow()` returns the JSON-encoded string, `fromRow()` parses
 * it back into the entity's array property.
 */
final class ContactInformationMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function toRow(ContactInformation $entity): array
    {
        return $entity->toArray();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromRow(array $row): ContactInformation
    {
        return ContactInformation::fromRow($row);
    }
}
