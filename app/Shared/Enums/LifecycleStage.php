<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * Lifecycle stages of the application — from constitution to production.
 * Used for feature gates and architectural checkpoints.
 *
 * PascalCase enum cases; backing values are snake_case identifiers
 * stored in the database or config files.
 */
enum LifecycleStage: string
{
    case Constitution         = 'constitution';
    case PreFoundation        = 'pre_foundation';
    case DatabaseArchitecture = 'database_architecture';
    case FinancialPlatform    = 'financial_platform';
    case PlatformFoundation   = 'platform_foundation';
    case PublicPlatform       = 'public_platform';
    case Administration       = 'administration';
    case Production           = 'production';

    public function label(): string
    {
        return match ($this) {
            self::Constitution         => 'Constitution',
            self::PreFoundation        => 'Pre-Foundation',
            self::DatabaseArchitecture => 'Database Architecture',
            self::FinancialPlatform    => 'Financial Platform',
            self::PlatformFoundation   => 'Platform Foundation',
            self::PublicPlatform       => 'Public Platform',
            self::Administration       => 'Administration',
            self::Production           => 'Production',
        };
    }

    /**
     * Return the current phase number as a float.
     */
    public function phaseNumber(): float
    {
        return match ($this) {
            self::Constitution         => 0.0,
            self::PreFoundation        => 0.25,
            self::DatabaseArchitecture => 0.5,
            self::FinancialPlatform    => 1.0,
            self::PlatformFoundation   => 2.0,
            self::PublicPlatform       => 3.0,
            self::Administration       => 4.0,
            self::Production           => 99.0,
        };
    }

    /**
     * Whether this stage is operationally live (i.e. accepts donations).
     */
    public function isOperational(): bool
    {
        return $this === self::FinancialPlatform
            || $this === self::PlatformFoundation
            || $this === self::PublicPlatform
            || $this === self::Administration
            || $this === self::Production;
    }

    /**
     * Whether this stage requires authentication for admin routes.
     */
    public function requiresAuth(): bool
    {
        return $this->phaseNumber() >= 1.0;
    }
}