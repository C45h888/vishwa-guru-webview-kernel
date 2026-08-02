<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4: Admin Kernel — canonical PostgreSQL users table.
 *
 * Source of truth for the schema lives on Neon PostgreSQL (br-shiny-poetry-aow2d8mt)
 * and is applied via the Neon MCP server. This migration mirrors the schema
 * shape so the Laravel migration tracker is consistent for `migrate:status`,
 * CI, and any environment that boots against PG.
 *
 * Doctrine (AGENTS.md §"Phase 4: Admin Kernel"):
 *   - This file is the PG-path mirror. SQLite has its own mirror at
 *     2026_08_02_000012_create_users_table_sqlite.php. Each is connection-guarded
 *     so neither runs against the wrong driver.
 *   - In production, `migrate` against PG is a no-op because the table
 *     already exists from the MCP apply. The `Schema::hasTable('users')`
 *     guard makes that explicit instead of relying on the migration
 *     tracker alone.
 *   - Schema must match the Neon canonical: CHAR(26) ULID PK, CITEXT email,
 *     TIMESTAMPTZ timestamps with now() defaults, named NOT NULL CHECK
 *     constraints, role VARCHAR(20) NOT NULL DEFAULT 'admin' with CHECK
 *     role = 'admin'. Drift here will break the ProductionSeeder and the
 *     AdminSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Connection guard: this migration is Postgres-only.
        // The SQLite mirror (sibling migration) owns the SQLite path.
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Idempotent: if the Neon MCP has already created the table
        // (the canonical path), skip the DDL. The migration record
        // is still inserted by Laravel so `migrate:status` reflects
        // the change as applied.
        if (Schema::hasTable('users')) {
            return;
        }

        Schema::create('users', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->string('name');
            $table->string('email');   // CITEXT is set via raw DDL below
            $table->timestampTz('email_verified_at')->nullable();
            $table->string('password');
            $table->string('remember_token', 100)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });

        // Promote email column to CITEXT (case-insensitive unique matching).
        // Doctrine: matches donors.email convention.
        DB::statement('ALTER TABLE public.users ALTER COLUMN email TYPE CITEXT');

        // Role column: locked to 'admin' for Pass 1. CHECK constraint
        // blocks any value other than 'admin' — multi-role expansion is
        // a follow-on migration.
        DB::statement("ALTER TABLE public.users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'admin'");
        DB::statement("ALTER TABLE public.users ADD CONSTRAINT users_role_admin_only CHECK (role = 'admin')");

        // Indexes that match the lookup patterns the auth kernel hits.
        DB::statement('CREATE INDEX users_remember_token_idx ON public.users USING btree (remember_token)');
        DB::statement('CREATE INDEX users_role_idx ON public.users USING btree (role)');

        // Named NOT NULL CHECK constraints — redundant with column-level
        // NOT NULL but match the style every other V1 table uses
        // (currencies_code_not_null, donors_id_not_null, …).
        DB::statement('ALTER TABLE public.users ADD CONSTRAINT users_id_not_null CHECK (id IS NOT NULL)');
        DB::statement('ALTER TABLE public.users ADD CONSTRAINT users_name_not_null CHECK (name IS NOT NULL)');
        DB::statement('ALTER TABLE public.users ADD CONSTRAINT users_email_not_null CHECK (email IS NOT NULL)');
        DB::statement('ALTER TABLE public.users ADD CONSTRAINT users_password_not_null CHECK (password IS NOT NULL)');
        DB::statement('ALTER TABLE public.users ADD CONSTRAINT users_created_at_not_null CHECK (created_at IS NOT NULL)');
        DB::statement('ALTER TABLE public.users ADD CONSTRAINT users_updated_at_not_null CHECK (updated_at IS NOT NULL)');
        DB::statement('ALTER TABLE public.users ADD CONSTRAINT users_role_not_null CHECK (role IS NOT NULL)');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        Schema::dropIfExists('users');
    }
};
