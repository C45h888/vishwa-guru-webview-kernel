<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * The runtime environment type.
 * PascalCase enum cases (PHP convention).
 */
enum EnvironmentType: string
{
    case Local      = 'local';
    case Testing    = 'testing';
    case Production = 'production';
    case CI         = 'ci';

    public function label(): string
    {
        return match ($this) {
            self::Local      => 'Local',
            self::Testing    => 'Testing',
            self::Production => 'Production',
            self::CI         => 'CI',
        };
    }

    public function isProduction(): bool
    {
        return $this === self::Production;
    }

    public function isDevelopment(): bool
    {
        return $this === self::Local || $this === self::CI;
    }

    public function isTesting(): bool
    {
        return $this === self::Testing;
    }
}