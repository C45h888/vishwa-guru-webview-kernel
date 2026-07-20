<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Runs the canonical Neon PostgreSQL schema. Use this when DB_CONNECTION=pgsql.
     * The schema is owned by the Neon branch and must not be edited here — edit
     * schema-neon/V1-schema.sql and copy it here.
     */
    public function up(): void
    {
        // Connection guard: this migration is Postgres-authoritative.
        // Without the guard, every RefreshDatabase on a SQLite-backed
        // test (phpunit.xml sets DB_CONNECTION=sqlite) would attempt
        // to run V1-schema.sql — full of CREATE TYPE / EXCLUDE / btree_gist
        // syntax that SQLite cannot parse, killing every feature test.
        // The sibling migration 2026_07_16_000002 owns the SQLite path.
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $schemaPath = database_path('schema-neon/V1-schema.sql');

        if (! file_exists($schemaPath)) {
            // Fallback: try relative path from project root
            $schemaPath = base_path('schema-neon/V1-schema.sql');
        }

        if (! file_exists($schemaPath)) {
            throw new RuntimeException(
                "Neon V1 schema not found at {$schemaPath}. ".
                "Ensure schema-neon/V1-schema.sql exists."
            );
        }

        // Execute the full Postgres schema as raw SQL.
        // Neon extensions (pgcrypto, citext, btree_gist) must already be available
        // on the target database.
        DB::unprepared(file_get_contents($schemaPath));
    }

    public function down(): void
    {
        // Schema destruction is intentionally NOT implemented.
        // This migration is one-way — use the Neon branch reset to undo.
    }
};
