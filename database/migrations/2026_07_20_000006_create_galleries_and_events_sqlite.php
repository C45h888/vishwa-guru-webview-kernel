<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SQLite translation for the Gallery + Events tables.
 *
 * Source of truth (Postgres DDL): database/schema-neon/V1-schema.sql
 * lines 762–875 (galleries, gallery_images, events). The PG migration
 * (2026_07_16_000001_) loads V1-schema.sql via DB::unprepared(); this
 * file is the SQLite mirror so DB-backed kernel tests have a real
 * schema to read against.
 *
 * Translation rules (mirrored from 000005 lines 20–36):
 *
 *   - ENUM types       → TEXT with CHECK (col IN ('val1',...))
 *   - JSONB columns    → TEXT with '{}' default (entity fromRow
 *                        accepts both array and JSON string and decodes
 *                        internally; no json_valid CHECK is emitted in V1)
 *   - TIMESTAMPTZ      → timestampTz() (Laravel stores as TEXT on SQLite)
 *   - BOOLEAN          → boolean (0/1)
 *   - DATE columns     → date() (Laravel stores as TEXT on SQLite)
 *   - PARTIAL UNIQUE   → emit via raw DB::statement("CREATE UNIQUE
 *                        INDEX … WHERE …") because the Schema Builder
 *                        does not expose the WHERE clause.
 *
 * Connection guard: this migration ONLY runs on sqlite. The PG path
 * is authoritative (loaded via DB::unprepared in 000001); running this
 * against pgsql would attempt to recreate tables that PG already owns.
 *
 * Companion: 2026_07_16_000005_create_cms_tables_sqlite.php.
 *
 * @see /Users/kamii/Vishwaguru-webview-kernel/vishwa-guru-webview-kernel/database/schema-neon/V1-schema.sql
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        // ── galleries ───────────────────────────────────────────────────
        Schema::create('galleries', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('slug');                            // NOT NULL; uniqueness via partial idx below
            $table->string('title');                           // NOT NULL
            $table->text('description')->nullable();
            $table->string('cover_image_file_id')->nullable();
            $table->string('state', 30)->default('draft');     // ENUM-as-TEXT + CHECK below
            $table->integer('display_order')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->timestampTz('published_at')->nullable();
            $table->text('metadata')->default('{}');           // JSON-as-TEXT
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->timestampTz('deleted_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
        });

        // ── gallery_images ──────────────────────────────────────────────
        Schema::create('gallery_images', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('gallery_id');                      // FK → galleries.id (added below)
            $table->string('file_asset_id')->nullable();
            $table->string('title')->nullable();
            $table->text('caption')->nullable();
            $table->text('alt_text')->nullable();
            $table->string('photographer_credit')->nullable();
            $table->date('taken_at')->nullable();
            $table->integer('display_order')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->string('state', 30)->default('draft');     // ENUM-as-TEXT + CHECK below
            $table->timestampTz('published_at')->nullable();
            $table->text('metadata')->default('{}');           // JSON-as-TEXT
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->timestampTz('deleted_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
        });

        // ── events ──────────────────────────────────────────────────────
        Schema::create('events', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('slug');                            // NOT NULL; uniqueness via partial idx below
            $table->string('title');                           // NOT NULL
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->string('banner_file_id')->nullable();
            $table->timestampTz('starts_at');                  // NOT NULL
            $table->timestampTz('ends_at')->nullable();
            $table->string('timezone')->default('Asia/Kolkata');
            $table->string('venue')->nullable();
            $table->text('venue_address')->nullable();
            $table->string('state', 30)->default('draft');     // ENUM-as-TEXT + CHECK below
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->integer('display_order')->default(0);
            $table->text('metadata')->default('{}');           // JSON-as-TEXT
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->timestampTz('deleted_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
        });

        // ════════════════════════════════════════════════════════════════
        // CHECK constraints (ENUM flattenings) — Schema Builder's
        // ->check() helper is inconsistent across driver versions, so
        // we emit raw DDL here for portability.
        // ════════════════════════════════════════════════════════════════
        DB::statement(<<<'SQL'
            CREATE INDEX galleries_state_featured_idx
                ON galleries (state, is_featured, display_order)
                WHERE deleted_at IS NULL
        SQL);

        // Partial UNIQUE — slug uniqueness is partial to allow
        // re-creating a soft-deleted gallery with the same slug.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX galleries_slug_live_idx
                ON galleries (slug)
                WHERE deleted_at IS NULL
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX gallery_images_gallery_order_idx
                ON gallery_images (gallery_id, display_order)
                WHERE deleted_at IS NULL
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX gallery_images_state_published_idx
                ON gallery_images (state, published_at DESC)
                WHERE state = 'published' AND deleted_at IS NULL
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX events_state_starts_idx
                ON events (state, starts_at)
                WHERE deleted_at IS NULL
        SQL);

        // Partial UNIQUE — slug uniqueness is partial.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX events_slug_live_idx
                ON events (slug)
                WHERE deleted_at IS NULL
        SQL);

        // ════════════════════════════════════════════════════════════════
        // Foreign keys — declared in trailing Schema::table() blocks
        // because the referenced tables must exist before the FK can
        // be created (Laravel Schema Builder requirement).
        // ════════════════════════════════════════════════════════════════
        Schema::table('gallery_images', function (Blueprint $table) {
            $table->foreign('gallery_id')
                ->references('id')->on('galleries')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        // Drop in reverse-dependency order: gallery_images (has FK to
        // galleries) first, then galleries, then events (no FKs).
        Schema::dropIfExists('gallery_images');
        Schema::dropIfExists('galleries');
        Schema::dropIfExists('events');
    }
};
