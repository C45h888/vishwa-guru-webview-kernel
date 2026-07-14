<?php

declare(strict_types=1);

namespace App\Shared\Environment;

use App\Shared\Contracts\EnvironmentContract;
use App\Shared\Enums\EnvironmentType;

/**
 * Laravel-backed environment abstraction.
 * Reads from Laravel's app() container and env().
 */
class LaravelEnvironment implements EnvironmentContract
{
    private ?EnvironmentType $cachedType = null;

    public function environment(): string
    {
        return app()->environment();
    }

    public function is(string|array $environments): bool
    {
        return app()->environment($environments);
    }

    public function isLocal(): bool
    {
        return app()->isLocal();
    }

    public function isProduction(): bool
    {
        return app()->isProduction();
    }

    public function isTesting(): bool
    {
        return app()->environment('testing');
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return env($key, $default);
    }

    public function type(): EnvironmentType
    {
        if ($this->cachedType !== null) {
            return $this->cachedType;
        }

        $env = $this->environment();

        $this->cachedType = match (strtolower($env)) {
            'production' => EnvironmentType::Production,
            'testing' => EnvironmentType::Testing,
            'ci' => EnvironmentType::CI,
            default => EnvironmentType::Local,
        };

        return $this->cachedType;
    }
}
