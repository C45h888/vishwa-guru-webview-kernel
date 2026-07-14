<?php

declare(strict_types=1);

namespace App\Persistence\Contracts;

use App\Persistence\ValueObjects\EntityId;

/**
 * Marker + minimum surface for any persistable domain entity.
 *
 * Every aggregate root or entity in the domain layer implements this.
 * Repositories accept/return entities that satisfy this contract.
 */
interface EntityContract
{
    /**
     * The unique identifier of this entity.
     */
    public function id(): EntityId;

    /**
     * The entity type identifier (used for repository resolution).
     * E.g. "donation", "transaction", "donor", "event".
     */
    public function entityType(): string;

    /**
     * Snapshot of the entity for persistence comparison.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * Produce a new instance with the given changes applied.
     *
     * @param  array<string, mixed>  $changes
     */
    public function withChanges(array $changes): static;
}
