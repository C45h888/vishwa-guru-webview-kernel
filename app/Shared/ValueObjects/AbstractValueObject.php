<?php

declare(strict_types=1);

namespace App\Shared\ValueObjects;

use App\Shared\Contracts\ValueObjectContract;

/**
 * Base class for all value objects.
 * Value objects are immutable and compared by their value.
 */
abstract class AbstractValueObject implements ValueObjectContract
{
    /**
     * @param  static  $other
     */
    public function equals(ValueObjectContract $other): bool
    {
        return $other::class === static::class
            && $this->value() === $other->value();
    }

    public function toString(): string
    {
        $value = $this->value();

        if (is_scalar($value) || is_null($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        return serialize($value); // @codeCoverageIgnore
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
