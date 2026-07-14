<?php

declare(strict_types=1);

namespace App\Shared\Configuration;

use App\Shared\Contracts\ConfigurationContract;
use App\Shared\Exceptions\ConfigurationException;

/**
 * Abstract base implementation of {@see ConfigurationContract}.
 *
 * Reads typed configuration values from an arbitrary backing source.
 * Concrete subclasses define where the values come from (Laravel config,
 * Vault, AWS SSM, ...) and how they are fetched.
 *
 * Every read is funnelled through {@see self::lookup()}, which subclasses
 * must implement. Type coercion and validation are handled here.
 */
abstract class AbstractConfiguration implements ConfigurationContract
{
    public function string(string $key, string $default = ''): string
    {
        $value = $this->lookup($key);

        if ($value === null) {
            return $default;
        }

        if (! is_string($value)) {
            throw ConfigurationException::invalidType($key, 'string', get_debug_type($value));
        }

        return $value;
    }

    public function integer(string $key, int $default = 0): int
    {
        $value = $this->lookup($key);

        if ($value === null) {
            return $default;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw ConfigurationException::invalidType($key, 'integer', get_debug_type($value));
    }

    public function boolean(string $key, bool $default = false): bool
    {
        $value = $this->lookup($key);

        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if (in_array($normalized, ['true', '1', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($normalized, ['false', '0', 'no', 'off', ''], true)) {
                return false;
            }
        }

        throw ConfigurationException::invalidType($key, 'boolean', get_debug_type($value));
    }

    public function array(string $key): array
    {
        $value = $this->lookup($key);

        if (! is_array($value)) {
            throw ConfigurationException::invalidType($key, 'array', get_debug_type($value));
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    public function has(string $key): bool
    {
        return $this->exists($key);
    }

    /**
     * Fetch a raw value from the underlying configuration source.
     *
     * Implementations MUST return null when the key does not exist.
     * They MUST NOT throw on missing keys.
     */
    abstract protected function lookup(string $key): mixed;

    /**
     * Determine whether the key exists in the underlying source.
     */
    abstract protected function exists(string $key): bool;
}
