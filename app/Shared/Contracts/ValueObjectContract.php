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
     *
     * @param ValueObjectContract $other
     * @return bool
     */
    public function equals(ValueObjectContract $other): bool;

    /**
     * Return the primitive value or array representation.
     *
     * @return mixed
     */
    public function value(): mixed;

    /**
     * Return a string representation for logging/debugging.
     *
     * @return string
     */
    public function toString(): string;
}
