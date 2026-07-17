<?php

declare(strict_types=1);

namespace App\Persistence\Neon\ValueObjects;

/**
 * Closed vocabulary of the Neon role tiers the project uses.
 *
 * Doctrine: documented role separation (in architecture.md) lives in code
 * as a type. Every Neon connection is one of these three roles; the role
 * is parsed once at config time and surfaces in /health JSON and
 * temple:neon:ping output so operators can verify which role the runtime
 * is actually using.
 *
 * Mapping to env: NEON_ROLE env string → enum case via NeonRole::tryFrom().
 *
 * Role grant model (operator-side, not enforced in code):
 *   - Owner: DDL only (migrations, schema changes). Used for `php artisan migrate`.
 *   - App:   Read-write for the runtime application. Used at request time.
 *   - Reader: Read-only for future read replicas / reporting.
 */
enum NeonRole: string
{
    case Owner  = 'owner';
    case App    = 'app';
    case Reader = 'reader';

    /**
     * Whether this role is permitted to run DDL statements (CREATE/ALTER/DROP).
     * Used by diagnostics + ops tooling to warn when the runtime is
     * connected with a DDL-capable role (anti-pattern — runtime should
     * never own DDL).
     */
    public function requiresDdl(): bool
    {
        return $this === self::Owner;
    }

    /**
     * Human-readable description for CLI / JSON output.
     */
    public function label(): string
    {
        return match ($this) {
            self::Owner  => 'owner (DDL only)',
            self::App    => 'app (read-write)',
            self::Reader => 'reader (read-only)',
        };
    }
}