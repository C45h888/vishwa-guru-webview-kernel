<?php

declare(strict_types=1);

namespace App\Shared\ValueObjects;

use App\Shared\Contracts\ValueObjectContract;
use App\Shared\Support\UlidGenerator;
use InvalidArgumentException;

/**
 * Represents a unique identifier for business entities.
 * Format: ULID (26 chars, Crockford Base32, lexicographically sortable).
 *
 * Implements ValueObjectContract directly (not via AbstractValueObject)
 * because the equals() signature needs the concrete type for type-safe
 * comparisons in domain code.
 */
final class Identifier implements ValueObjectContract
{
    public function __construct(
        private readonly string $value,
    ) {
        if (!UlidGenerator::isValid($value)) {
            throw new InvalidArgumentException(
                "Invalid Identifier value: {$value} (must be a valid ULID)"
            );
        }
    }

    /**
     * Generate a new Identifier.
     */
    public static function generate(): self
    {
        return new self(UlidGenerator::generate());
    }

    /**
     * Wrap an existing string as an Identifier (validates format).
     */
    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(ValueObjectContract $other): bool
    {
        return $other instanceof self
            && $other->value === $this->value;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}