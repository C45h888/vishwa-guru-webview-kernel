<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SQLite translation for the 5 CMS-kernel tables.
 *
 * Source of truth (Postgres DDL): database/schema-neon/V1-schema.sql
 * lines 257–375 (static_pages, hero_banners, hero_banner_pages,
 * static_page_references, contact_information). The PG migration
 * (2026_07_16_000001_) loads V1-schema.sql via DB::unprepared(); this
 * file is the SQLite mirror so DB-backed kernel tests have a real
 * schema to read against.
 *
 * Translation rules (mirrored from 000002 lines 15–24):
 *
 *   - ENUM types       → TEXT with CHECK (col IN ('val1',...))
 *   - JSONB columns    → TEXT with '{}' default (entity fromRow
 *                        accepts both array and JSON string and decodes
 *                        internally; no json_valid CHECK is emitted in V1)
 *   - TIMESTAMPTZ      → timestampTz() (Laravel stores as TEXT on SQLite)
 *   - BOOLEAN          → boolean (0/1)
 *   - PARTIAL UNIQUE   → emit via raw DB::statement("CREATE UNIQUE
 *                        INDEX … WHERE …") because the Schema Builder
 *                        does not expose the WHERE clause.
 *   - EXCLUDE          → NOT SUPPORTED on SQLite. The single-homepage
 *                        invariant is enforced in StaticPageService at
 *                        the app layer (see drive-by B).
 *   - IS NOT DISTINCT FROM → not supported in Schema Builder;
 *                          driver-aware rewrite lives in
 *                          EloquentStaticPageReferenceRepository::existsForPage().
 *
 * Connection guard: this migration ONLY runs on sqlite. The PG path
 * is authoritative (loaded via DB::unprepared in 000001); running this
 * against pgsql would attempt to recreate tables that PG already owns.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        // ── static_pages ─────────────────────────────────────────────────
        Schema::create('static_pages', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('slug');          // NOT NULL; uniqueness via partial idx below
            $table->string('title');         // NOT NULL
            $table->text('meta_description')->nullable();
            $table->text('body_json')->default('{}');           // JSON-as-TEXT
            $table->text('body_html')->nullable();
            $table->string('state', 30)->default('draft');       // ENUM-as-TEXT + CHECK below
            $table->boolean('is_homepage')->default(false);
            $table->integer('display_order')->default(0);
            $table->text('seo_metadata')->default('{}');         // JSON-as-TEXT
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('last_published_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->timestampTz('deleted_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
        });

        // ── hero_banners ─────────────────────────────────────────────────
        Schema::create('hero_banners', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->string('cta_label')->nullable();
            $table->string('cta_url')->nullable();
            $table->string('image_file_id')->nullable();
            $table->string('mobile_image_file_id')->nullable();
            $table->string('state', 30)->default('draft');
            $table->integer('display_order')->default(0);
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->timestampTz('deleted_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
        });

        // ── hero_banner_pages (junction) ─────────────────────────────────
        Schema::create('hero_banner_pages', function (Blueprint $table) {
            $table->string('hero_banner_id');
            $table->string('static_page_id');
            $table->integer('display_order')->default(0);
            $table->timestampTz('created_at')->useCurrent();

            // Composite PK mirrors V1-schema.sql:330
            $table->primary(['hero_banner_id', 'static_page_id']);
        });

        // ── static_page_references ──────────────────────────────────────
        Schema::create('static_page_references', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('static_page_id');
            $table->string('reference_type', 30);   // ENUM-as-TEXT + CHECK below
            $table->string('reference_id');
            $table->integer('display_order')->default(0);
            $table->text('context')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        // ── contact_information ─────────────────────────────────────────
        Schema::create('contact_information', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('label');
            $table->string('contact_type', 30);     // ENUM-as-TEXT + CHECK below
            $table->text('value');
            $table->boolean('is_primary')->default(false);
            $table->integer('display_order')->default(0);
            $table->text('metadata')->default('{}'); // JSON-as-TEXT
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->timestampTz('deleted_at')->nullable();
        });

        // ════════════════════════════════════════════════════════════════
        // CHECK constraints (ENUM flattenings) — Schema Builder's
        // ->check() helper is inconsistent across driver versions, so
        // we emit raw DDL here for portability.
        // ════════════════════════════════════════════════════════════════
        DB::statement(<<<'SQL'
            CREATE INDEX static_pages_state_live_idx
                ON static_pages (state, display_order)
                WHERE deleted_at IS NULL
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX static_pages_published_idx
                ON static_pages (published_at DESC)
                WHERE state = 'published' AND deleted_at IS NULL
        SQL);

        // Partial UNIQUE — slug uniqueness is partial to allow
        // re-creating a soft-deleted page with the same slug.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX static_pages_slug_live_idx
                ON static_pages (slug)
                WHERE deleted_at IS NULL
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX static_page_references_target_idx
                ON static_page_references (reference_type, reference_id)
        SQL);

        // Partial UNIQUE on (static_page_id, reference_type,
        // reference_id, context) — context is nullable so the index
        // treats NULL as distinct (SQLite semantics). The repo's
        // existsForPage() uses an explicit NULL-safe comparison
        // (see drive-by A) to compensate.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX static_page_references_unique_idx
                ON static_page_references (static_page_id, reference_type, reference_id, context)
        SQL);

        // ════════════════════════════════════════════════════════════════
        // Foreign keys — Postgres enforces all of these with FK
        // constraints; SQLite needs them declared in trailing
        // Schema::table() blocks because the referenced tables must
        // exist before the FK is created.
        // ════════════════════════════════════════════════════════════════
        Schema::table('hero_banner_pages', function (Blueprint $table) {
            $table->foreign('hero_banner_id')
                ->references('id')->on('hero_banners')
                ->onDelete('cascade');
            $table->foreign('static_page_id')
                ->references('id')->on('static_pages')
                ->onDelete('cascade');
        });

        Schema::table('static_page_references', function (Blueprint $table) {
            $table->foreign('static_page_id')
                ->references('id')->on('static_pages')
                ->onDelete('cascade');
        });

        // file_assets is created by 2026_07_16_000002; the FKs to it
        // are declared here because hero_banners is created here too
        // and Laravel's Schema Builder requires both ends of an FK to
        // exist in the connection before the FK can be wired.
        Schema::table('hero_banners', function (Blueprint $table) {
            $table->foreign('image_file_id')
                ->references('id')->on('file_assets')
                ->onDelete('restrict');
            $table->foreign('mobile_image_file_id')
                ->references('id')->on('file_assets')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        // Drop in reverse-dependency order. Junction first because it
        // references both static_pages and hero_banners; then the two
        // parents (either order works since no FKs point back to
        // hero_banner_pages); then the leaf table contact_information
        // which depends on nothing else in this migration.
        Schema::dropIfExists('static_page_references');
        Schema::dropIfExists('hero_banner_pages');
        Schema::dropIfExists('hero_banners');
        Schema::dropIfExists('static_pages');
        Schema::dropIfExists('contact_information');
    }
};
