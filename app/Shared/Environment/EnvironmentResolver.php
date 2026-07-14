<?php

declare(strict_types=1);

namespace App\Shared\Environment;

use App\Shared\Contracts\EnvironmentContract;
use App\Shared\Enums\EnvironmentType;
use App\Shared\Exceptions\ConfigurationException;

/**
 * Default implementation of {@see EnvironmentContract}.
 *
 * Resolves the deployment environment from APP_ENV, and reads secrets
 * through Laravel's encrypted environment configuration. Secret values
 * are NEVER logged.
 */
final class EnvironmentResolver implements EnvironmentContract
{
    private readonly EnvironmentType $type;

    public function __construct()
    {
        $this->type = EnvironmentType::fromAppEnv(
            (string) config('app.env', 'production'),
        );
    }

    public function environment(): string
    {
        return (string) config('app.env', 'production');
    }

    public function is(string|array $environments): bool
    {
        $current = $this->environment();

        if (is_string($environments)) {
            return $current === $environments;
        }

        foreach ($environments as $env) {
            if ($current === $env) {
                return true;
            }
        }

        return false;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return env($key, $default);
    }

    public function type(): EnvironmentType
    {
        return $this->type;
    }

    public function isProduction(): bool
    {
        return $this->type === EnvironmentType::Production;
    }

    public function isLocal(): bool
    {
        return $this->type === EnvironmentType::Local;
    }

    public function isTesting(): bool
    {
        return $this->type === EnvironmentType::Testing;
    }

    public function isStaging(): bool
    {
        return $this->type === EnvironmentType::Staging;
    }

    public function secret(string $key): string
    {
        $encrypted = env($key);

        if ($encrypted === null || $encrypted === '') {
            throw ConfigurationException::missingKey($key);
        }

        // Plain secrets are returned as-is. Encrypted secrets (prefixed
        // with "base64:" or matching the encrypted-payload format) are
        // decoded through Laravel's encryption layer. Phase 0.25 only
        // requires that the method exists and validates non-emptiness;
        // the encrypted-secret flow is added in Phase 2 (Runtime
        // Configuration).
        return (string) $encrypted;
    }
}
