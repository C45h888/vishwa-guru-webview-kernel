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
        // Connection guard mirrored from up(): this destructive down is
        // a no-op on non-pgsql connections (no PG enums to drop).
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Drop every enum type defined in V1-schema.sql lines 47-166.
        // Without this, db:wipe + migrate:fresh on a pre-populated PG
        // database fails on the second run because Postgres enums
        // outlive table drops (db:wipe drops tables only).
        //
        // Production guidance: do NOT run `migrate:rollback` against
        // production. Use the Neon branch reset to undo schema. This
        // down() is here for test-environment freshness only.
        $types = [
            'donation_state',
            'payment_status',
            'campaign_state',
            'static_page_state',
            'gallery_state',
            'event_state',
            'receipt_state',
            'failure_classification',
            'notification_channel',
            'notification_status',
            'file_owner_type',
            'page_reference_type',
            'contact_type',
            'audit_actor_type',
        ];
        foreach ($types as $type) {
            DB::statement("DROP TYPE IF EXISTS {$type} CASCADE");
        }
    }
};
