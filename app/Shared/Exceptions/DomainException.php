<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use RuntimeException;

/**
 * Base exception for every business-domain failure.
 *
 * Domain exceptions represent failures in business rules, not infrastructure
 * problems. Concrete subclasses describe specific failure modes:
 *
 *   - ValidationFailedException — input failed business validation
 *   - ConfigurationException   — configuration surface is invalid
 *   - InfrastructureException  — external system failed
 *
 * Business services MUST throw DomainException (or a subclass) when a
 * business invariant cannot be satisfied. Controllers are responsible for
 * translating these exceptions into appropriate user-facing responses.
 */
abstract class DomainException extends RuntimeException
{
    /**
     * Machine-readable error code suitable for client response surfaces.
     *
     * The code MUST be stable across releases; clients depend on it for
     * programmatic error handling.
     */
    abstract public function errorCode(): string;

    /**
     * Structured error context. Subclasses MAY override to surface
     * domain-relevant metadata.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [];
    }
}