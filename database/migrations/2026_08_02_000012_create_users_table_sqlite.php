<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4: Admin Kernel — SQLite mirror of the canonical users table.
 *
 * SQLite is the in-memory test backend (phpunit.xml's <env> tags set
 * DB_CONNECTION=sqlite + DB_DATABASE=:memory:). Every test that
 * exercises the auth kernel needs the users table present.
 *
 * Doctrine (AGENTS.md §"Phase 4: Admin Kernel"):
 *   - SQLite has no CITEXT, so email uses TEXT COLLATE NOCASE which
 *     gives case-insensitive matching for the UNIQUE constraint.
 *   - TIMESTAMPTZ reduces to TEXT on SQLite (Laravel stores ISO-8601).
 *     Same default 'now()' (SQLite literal) is used in tests.
 *   - The role CHECK is added INLINE in CREATE TABLE because SQLite's
 *     `ALTER TABLE … ADD CONSTRAINT … CHECK` syntax is not portable
 *     (SQLite 3.25+ allows it but only with table-recreation logic
 *     that Laravel's Schema Builder does not emit by default).
 *   - Connection guard: this migration ONLY runs on sqlite. PG is
 *     owned by the sibling migration 2026_08_02_000011.
 *   - Doctrine parity with `2026_07_16_000002_create_v1_schema_sqlite`:
 *     raw DB::statement for indexes that the Schema Builder doesn't
 *     expose cleanly.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Connection guard: SQLite-only mirror. PG owns the canonical.
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        if (Schema::hasTable('users')) {
            return;
        }

        // Add the role column inline so the CHECK constraint lands
        // at table-creation time (the only reliable path on SQLite).
        // Schema Builder doesn't expose CHECK constraints cleanly, so
        // we use raw DDL.
        DB::statement(<<<'SQL'
            CREATE TABLE users (
                id CHAR(26) PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email TEXT NOT NULL COLLATE NOCASE,
                email_verified_at TIMESTAMPTZ NULL,
                password VARCHAR(255) NOT NULL,
                remember_token VARCHAR(100) NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
                role VARCHAR(20) NOT NULL DEFAULT 'admin' CHECK (role = 'admin')
            )
        SQL);

        // UNIQUE on email — applied as a separate CREATE UNIQUE INDEX
        // so the COLLATE NOCASE clause takes effect for case-insensitive
        // uniqueness. The PG canonical uses CITEXT for the same goal.
        DB::statement('CREATE UNIQUE INDEX users_email_key ON users (email)');

        // Indexes for the lookup patterns the auth kernel hits.
        DB::statement('CREATE INDEX users_remember_token_idx ON users (remember_token)');
        DB::statement('CREATE INDEX users_role_idx ON users (role)');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        Schema::dropIfExists('users');
    }
};
