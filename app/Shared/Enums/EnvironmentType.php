<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * The runtime environment type.
 * PascalCase enum cases (PHP convention).
 */
enum EnvironmentType: string
{
    case Local = 'local';
    case Testing = 'testing';
    case Production = 'production';
    case Staging = 'staging';
    case CI = 'ci';

    public function label(): string
    {
        return match ($this) {
            self::Local => 'Local',
            self::Testing => 'Testing',
            self::Production => 'Production',
            self::Staging => 'Staging',
            self::CI => 'CI',
        };
    }

    public function isProduction(): bool
    {
        return $this === self::Production;
    }

    public function isProductionLike(): bool
    {
        return $this === self::Production || $this === self::Staging;
    }

    public function isDevelopment(): bool
    {
        return $this === self::Local || $this === self::CI;
    }

    public function isTesting(): bool
    {
        return $this === self::Testing;
    }

    /**
     * Resolve an APP_ENV string into the matching enum case.
     * Accepts common aliases (prod, stage, development, ...).
     * Unknown / empty values fall back to Production.
     */
    public static function fromAppEnv(string $appEnv): self
    {
        $normalized = strtolower(trim($appEnv));

        return match ($normalized) {
            'production', 'prod' => self::Production,
            'staging', 'stage' => self::Staging,
            'testing' => self::Testing,
            'local', 'development' => self::Local,
            'ci' => self::CI,
            default => self::Production,
        };
    }
}
