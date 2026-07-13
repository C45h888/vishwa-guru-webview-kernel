<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

/**
 * Contract for configuration objects.
 * Configuration objects provide type-safe access to app settings.
 */
interface ConfigurationContract
{
    /**
     * Get a configuration value by key.
     *
     * @param string $key Dot-notation key: 'services.razorpay.key_id'
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Get a configuration value as a string.
     *
     * @param string $key
     * @param string $default
     * @return string
     */
    public function string(string $key, string $default = ''): string;

    /**
     * Get a configuration value as an integer.
     *
     * @param string $key
     * @param int $default
     * @return int
     */
    public function integer(string $key, int $default = 0): int;

    /**
     * Get a configuration value as a boolean.
     *
     * @param string $key
     * @param bool $default
     * @return bool
     */
    public function boolean(string $key, bool $default = false): bool;

    /**
     * Check whether a key is set.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Get all configuration as an array.
     *
     * @return array<string, mixed>
     */
    public function all(): array;
}