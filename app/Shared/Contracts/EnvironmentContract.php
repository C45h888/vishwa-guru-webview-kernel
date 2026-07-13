<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

use App\Shared\Enums\EnvironmentType;

/**
 * Contract for environment abstraction.
 * Environment provides type-safe access to environment variables.
 */
interface EnvironmentContract
{
    /**
     * Get the current environment name.
     *
     * @return string
     */
    public function environment(): string;

    /**
     * Check if running in a given environment.
     *
     * @param string|array<string> $environments
     * @return bool
     */
    public function is(string|array $environments): bool;

    /**
     * Check if running in a local environment.
     *
     * @return bool
     */
    public function isLocal(): bool;

    /**
     * Check if running in production.
     *
     * @return bool
     */
    public function isProduction(): bool;

    /**
     * Check if running in testing.
     *
     * @return bool
     */
    public function isTesting(): bool;

    /**
     * Get an environment variable value.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Get the environment type enum.
     *
     * @return EnvironmentType
     */
    public function type(): EnvironmentType;
}
