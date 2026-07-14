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
     */
    public function environment(): string;

    /**
     * Check if running in a given environment.
     *
     * @param  string|array<string>  $environments
     */
    public function is(string|array $environments): bool;

    /**
     * Check if running in a local environment.
     */
    public function isLocal(): bool;

    /**
     * Check if running in production.
     */
    public function isProduction(): bool;

    /**
     * Check if running in testing.
     */
    public function isTesting(): bool;

    /**
     * Get an environment variable value.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Get the environment type enum.
     */
    public function type(): EnvironmentType;
}
