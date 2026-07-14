<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

/**
 * Base contract for all value objects.
 * Value objects are immutable, self-validating, and compared by value.
 */
interface ValueObjectContract
{
    /**
     * Determine whether this value object equals another.
     */
    public function equals(ValueObjectContract $other): bool;

    /**
     * Return the primitive value or array representation.
     */
    public function value(): mixed;

    /**
     * Return a string representation for logging/debugging.
     */
    public function toString(): string;
}
