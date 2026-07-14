<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

/**
 * Thrown when an external infrastructure component fails.
 *
 * Examples:
 *   - Payment gateway unreachable
 *   - Database connection refused
 *   - Email provider timeout
 *   - Storage backend failure
 *
 * Infrastructure failures are NEVER thrown directly from business
 * services. Adapters translate infrastructure failures into
 * InfrastructureException so that business code remains free of
 * third-party exception types.
 */
final class InfrastructureException extends DomainException
{
    public function __construct(
        string $message,
        private readonly string $component = 'unknown',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function errorCode(): string
    {
        return 'shared.infrastructure.failure';
    }

    public function component(): string
    {
        return $this->component;
    }

    public function context(): array
    {
        return ['component' => $this->component];
    }
}
