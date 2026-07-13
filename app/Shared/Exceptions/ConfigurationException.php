<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

/**
 * Thrown when the configuration surface is invalid or missing required
 * values. Configuration failures prevent the application from booting
 * safely and MUST be surfaced at the earliest possible moment.
 *
 * Examples:
 *   - Required secret missing in production
 *   - Configuration value cannot be cast to its declared type
 *   - Configuration schema mismatch (renamed key)
 */
final class ConfigurationException extends DomainException
{
    public static function missingKey(string $key): self
    {
        return new self(sprintf(
            'Required configuration key [%s] is missing.',
            $key,
        ));
    }

    public static function invalidType(string $key, string $expected, string $actual): self
    {
        return new self(sprintf(
            'Configuration key [%s] expected %s, received %s.',
            $key,
            $expected,
            $actual,
        ));
    }

    public function errorCode(): string
    {
        return 'shared.configuration.invalid';
    }

    public function context(): array
    {
        return ['message' => $this->getMessage()];
    }
}