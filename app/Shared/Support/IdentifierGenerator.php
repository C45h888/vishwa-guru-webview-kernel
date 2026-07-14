<?php

declare(strict_types=1);

namespace App\Shared\Support;

/**
 * Identifier generator abstraction.
 *
 * Business modules depend on this contract rather than calling any
 * library-specific generator function so that identifier format can be
 * migrated across the system without touching domain code.
 *
 * Phase 0.25 ships only with the ULID strategy. UUID-based generation
 * is preserved as a future fallback for migration scenarios.
 */
interface IdentifierGenerator
{
    /**
     * Generate a new opaque identifier string.
     */
    public function next(): string;
}
