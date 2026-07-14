<?php

declare(strict_types=1);

namespace App\Shared\Configuration;

use App\Shared\Contracts\ConfigurationContract;

/**
 * ConfigurationContract implementation that prefixes all keys
 * with a fixed namespace segment before delegating to a backing
 * LaravelConfiguration.
 *
 * Used by ConfigurationRegistry to give each registered namespace
 * its own scoped configuration view without duplicating the
 * underlying config() helper.
 */
final class NamespacedConfiguration implements ConfigurationContract
{
    public function __construct(
        private readonly string $namespace,
    ) {}

    private function key(string $key): string
    {
        if ($this->namespace === '') {
            return $key;
        }

        return $this->namespace.'.'.$key;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return config($this->key($key), $default);
    }

    public function string(string $key, string $default = ''): string
    {
        $value = config($this->key($key), $default);
        if ($value === null) {
            return $default;
        }

        return (string) $value;
    }

    public function integer(string $key, int $default = 0): int
    {
        $value = config($this->key($key), $default);
        if ($value === null) {
            return $default;
        }

        return (int) $value;
    }

    public function boolean(string $key, bool $default = false): bool
    {
        $value = config($this->key($key), $default);
        if ($value === null) {
            return $default;
        }

        return (bool) $value;
    }

    public function has(string $key): bool
    {
        return config()->has($this->key($key));
    }

    public function all(): array
    {
        return config()->all();
    }
}
