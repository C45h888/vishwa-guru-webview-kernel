<?php

declare(strict_types=1);

namespace App\Persistence\ValueObjects;

use App\Shared\Support\UlidGenerator;
use InvalidArgumentException;

/**
 * Identifies a persisted entity.
 * Wraps the ULID with type information so that:
 *   - the same identifier string never collides across entity types
 *   - repositories can be type-safe at lookup
 *
 * Format: {entity_type}_{ulid}
 * Example: donation_01ARZ3NDEKTSV4RRFFQ69G5FAV
 */
final class EntityId
{
    private const PATTERN = '/^[a-z][a-z0-9_]*_[0-9A-Z]{26}$/';

    public function __construct(
        private readonly string $entityType,
        private readonly string $ulid,
    ) {
        if (empty($entityType)) {
            throw new InvalidArgumentException('Entity type cannot be empty');
        }
        if (! preg_match('/^[a-z][a-z0-9_]*$/', $entityType)) {
            throw new InvalidArgumentException(
                "Invalid entity type: {$entityType} (must match [a-z][a-z0-9_]*)"
            );
        }
        if (! UlidGenerator::isValid($ulid)) {
            throw new InvalidArgumentException(
                "Invalid ULID: {$ulid}"
            );
        }
    }

    /**
     * Generate a new EntityId for the given entity type.
     */
    public static function generate(string $entityType): self
    {
        return new self($entityType, UlidGenerator::generate());
    }

    /**
     * Parse a string like "donation_01ARZ3..." into an EntityId.
     */
    public static function fromString(string $value): self
    {
        if (! preg_match(self::PATTERN, $value)) {
            throw new InvalidArgumentException(
                "Invalid EntityId format: {$value}"
            );
        }

        $lastUnderscore = strrpos($value, '_');
        $entityType = substr($value, 0, $lastUnderscore);
        $ulid = substr($value, $lastUnderscore + 1);

        return new self($entityType, $ulid);
    }

    public function entityType(): string
    {
        return $this->entityType;
    }

    public function ulid(): string
    {
        return $this->ulid;
    }

    public function value(): string
    {
        return $this->entityType.'_'.$this->ulid;
    }

    public function equals(EntityId $other): bool
    {
        return $this->value() === $other->value();
    }

    public function toString(): string
    {
        return $this->value();
    }
}
