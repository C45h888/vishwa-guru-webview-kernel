<?php

declare(strict_types=1);

namespace App\Shared\Configuration;

use App\Shared\Contracts\ConfigurationContract;

/**
 * Laravel-backed configuration implementation.
 * Delegates to Laravel's config() helper.
 */
class LaravelConfiguration implements ConfigurationContract
{
    public function get(string $key, mixed $default = null): mixed
    {
        return config($key, $default);
    }

    public function string(string $key, string $default = ''): string
    {
        $value = config($key, $default);

        if ($value === null) {
            return $default;
        }

        return (string) $value;
    }

    public function integer(string $key, int $default = 0): int
    {
        $value = config($key, $default);

        if ($value === null) {
            return $default;
        }

        return (int) $value;
    }

    public function boolean(string $key, bool $default = false): bool
    {
        $value = config($key, $default);

        if ($value === null) {
            return $default;
        }

        return (bool) $value;
    }

    public function has(string $key): bool
    {
        return config()->has($key);
    }

    public function all(): array
    {
        return config()->all();
    }
}
