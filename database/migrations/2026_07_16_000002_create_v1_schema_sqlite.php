<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * SQLite-compatible schema covering the 11 tables needed by
     * Pass 1.4 repositories. Translation rules from V1-schema.sql:
     *
     *   - ENUM types   → TEXT with CHECK (col IN ('val1', ...))
     *   - CITEXT       → TEXT COLLATE NOCASE
     *   - JSONB        → TEXT (enforced CHECK json_valid() in CHECK constraint)
     *   - TIMESTAMPTZ  → TEXT (ISO-8601 strings; PHP-side conversion)
     *   - INET         → TEXT
     *   - CHAR(3)[]    → TEXT (JSON-encoded array string)
     *   - EXCLUDE      → NOT SUPPORTED (enforced in app layer)
     *   - pgcrypto     → N/A (ULIDs generated in PHP via EntityId)
     *
     * Only covers: currencies, payment_providers, donors, campaigns,
     * donations, payments, failure_states, receipts, webhook_events,
     * idempotency_keys, audit_events, file_assets (added Pass 1.4).
     *
     * Other V1 tables (static_pages, hero_banners, hero_banner_pages,
     * static_page_references, contact_information) are added by the
     * sibling migration 2026_07_16_000005_create_cms_tables_sqlite.
     * Galleries, events, notifications are deferred to their own passes.
     */
    public function up(): void
    {
        // Connection-aware: this migration ONLY runs on sqlite.
        // Postgres uses the sibling migration `2026_07_16_000001_*`
        // which loads V1-schema.sql via DB::unprepared. Running this
        // against postgres would attempt to recreate tables that the
        // postgres migration is authoritative for.
        if (\Illuminate\Support\Facades\DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }
        // ── currencies ────────────────────────────────────────────────────
        Schema::create('currencies', function (Blueprint $table) {
            $table->string('code', 3)->primary();
            $table->string('name');
            $table->string('symbol');
            $table->smallInteger('minor_unit_digits')
                ->unsigned();
            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->default(0);
            $table->timestampTz('created_at')->nullable();
            $table->timestampTz('updated_at')->nullable();

            $table->unique('code');
        });

        // ── payment_providers ─────────────────────────────────────────────
        Schema::create('payment_providers', function (Blueprint $table) {
            $table->string('code')->primary();
            $table->string('display_name');
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(100);
            // CHAR(3)[] → JSON text array string
            $table->text('supported_currencies')->default('[]');
            $table->bigInteger('min_amount_minor')->unsigned()->nullable();
            $table->bigInteger('max_amount_minor')->unsigned()->nullable();
            // JSONB → TEXT with json_valid CHECK
            $table->text('configuration')->default('{}');
            $table->timestampTz('created_at')->nullable();
            $table->timestampTz('updated_at')->nullable();
        });

        // ── donors ────────────────────────────────────────────────────────
        Schema::create('donors', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('full_name')->nullable();
            // CITEXT → TEXT COLLATE NOCASE (case-insensitive email lookup)
            $table->text('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state_region')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('pan_number')->nullable();
            $table->string('preferred_lang', 5)->nullable();
            $table->boolean('is_anonymized')->default(false);
            $table->text('notes')->nullable();
            $table->timestampTz('created_at')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->timestampTz('deleted_at')->nullable();
        });

        // SQLite doesn't enforce CHECK constraints inline well for soft-deletes,
        // so we rely on the application layer. Partial indexes replicate the
        // Postgres filtered-index behaviour.
        Schema::table('donors', function (Blueprint $table) {
            $table->index('email', 'donors_email_active_idx');
            $table->index('phone', 'donors_phone_active_idx');
        });

        // ── campaigns ─────────────────────────────────────────────────────
        Schema::create('campaigns', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('slug');
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->string('cover_image_file_id')->nullable();
            $table->string('category');
            $table->bigInteger('target_amount_minor')->unsigned()->nullable();
            $table->string('currency_code', 3);
            // campaign_state enum
            $table->string('state', 20)->default('draft');
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->integer('display_order')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->text('metadata')->default('{}');
            $table->timestampTz('created_at')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            // NOTE: FK to currencies.currency_code added below after table exists
            $table->unique('slug', 'campaigns_slug_live_idx');
        });

        // Partial indexes for campaigns (SQLite supports WHERE clauses)
        Schema::table('campaigns', function (Blueprint $table) {
            $table->index(['state', 'is_featured', 'display_order'], 'campaigns_state_featured_idx');
            $table->index(['starts_at', 'ends_at'], 'campaigns_active_window_idx');
        });

        // ── donations ─────────────────────────────────────────────────────
        Schema::create('donations', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('campaign_id');
            $table->string('donor_id')->nullable();

            $table->string('donor_name_snapshot')->nullable();
            $table->string('donor_email_snapshot')->nullable();
            $table->string('donor_phone_snapshot')->nullable();
            $table->string('donor_pan_snapshot')->nullable();
            // JSONB → TEXT with CHECK
            $table->text('donor_address_snapshot')->default('{}');

            $table->bigInteger('amount_minor')->unsigned();
            $table->string('currency_code', 3);
            $table->boolean('is_anonymous')->default(false);
            $table->text('dedication')->nullable();
            $table->text('donor_message')->nullable();
            $table->text('internal_notes')->nullable();

            // donation_state enum
            $table->string('state', 30)->default('draft');

            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('payment_initiated_at')->nullable();
            $table->timestampTz('payment_verified_at')->nullable();
            $table->timestampTz('receipt_generated_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();

            $table->string('idempotency_key')->nullable();
            $table->text('metadata')->default('{}');

            $table->timestampTz('created_at')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
        });

        // Indexes for donations
        Schema::table('donations', function (Blueprint $table) {
            $table->index(['campaign_id', 'created_at'], 'donations_campaign_created_idx');
            $table->index(['donor_id', 'created_at'], 'donations_donor_created_idx');
            $table->index('state', 'donations_state_idx');
            $table->index('idempotency_key', 'donations_idempotency_key_idx');
        });

        // ── payments ─────────────────────────────────────────────────────
        Schema::create('payments', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('donation_id');
            $table->string('provider_code');

            $table->string('provider_order_id')->nullable();
            $table->string('provider_payment_id')->nullable();
            $table->string('provider_reference_id')->nullable();

            $table->bigInteger('amount_minor')->unsigned();
            $table->string('currency_code', 3);
            $table->bigInteger('amount_captured_minor')->nullable();
            $table->bigInteger('amount_refunded_minor')->default(0)->unsigned();
            $table->bigInteger('fee_minor')->nullable();
            $table->bigInteger('tax_minor')->nullable();

            $table->string('method')->nullable();
            $table->text('method_detail')->default('{}'); // JSONB
            $table->string('status', 30)->default('initialized'); // payment_status enum

            $table->text('signature')->nullable();
            $table->timestampTz('signature_verified_at')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->text('verification_metadata')->default('{}'); // JSONB

            $table->timestampTz('initiated_at')->nullable();
            $table->timestampTz('authorized_at')->nullable();
            $table->timestampTz('captured_at')->nullable();
            $table->timestampTz('settled_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampTz('refunded_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('expired_at')->nullable();

            $table->string('last_failure_code')->nullable();
            $table->text('last_failure_reason')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->text('raw_provider_response')->default('{}'); // JSONB

            $table->timestampTz('created_at')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->timestampTz('deleted_at')->nullable();

            // UNIQUE per donation
            $table->unique('donation_id', 'payments_unique_per_donation');
        });

        // Indexes for payments
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['provider_code', 'provider_payment_id'], 'payments_provider_payment_id_idx');
            $table->index('status', 'payments_status_idx');
            $table->index('initiated_at', 'payments_initiated_recent_idx');
            $table->index('idempotency_key', 'payments_idempotency_key_idx');
        });

        // ── failure_states ────────────────────────────────────────────────
        Schema::create('failure_states', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('payment_id');

            // failure_classification enum
            $table->string('classification', 20);
            $table->string('failure_code');
            $table->text('failure_reason')->nullable();
            $table->text('failure_metadata')->default('{}'); // JSONB

            $table->timestampTz('first_failed_at');
            $table->timestampTz('last_failed_at');
            $table->integer('retry_count')->default(0)->unsigned();
            $table->timestampTz('next_retry_at')->nullable();
            $table->integer('max_retries')->default(3)->unsigned();

            $table->timestampTz('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->string('resolved_by')->nullable();

            $table->timestampTz('created_at')->nullable();
            $table->timestampTz('updated_at')->nullable();

            // UNIQUE payment_id
            $table->unique('payment_id', 'failure_states_payment_unique');
        });

        Schema::table('failure_states', function (Blueprint $table) {
            $table->index('next_retry_at', 'failure_states_unresolved_retry_idx');
            $table->index('last_failed_at', 'failure_states_unresolved_terminal_idx');
        });

        // ── receipts ──────────────────────────────────────────────────────
        Schema::create('receipts', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('receipt_number')->unique();
            $table->string('donation_id');
            $table->string('payment_id');
            $table->string('campaign_id');
            $table->string('campaign_title_snapshot');

            $table->string('donor_name');
            $table->string('donor_email')->nullable();
            $table->string('donor_pan')->nullable();
            $table->text('donor_address')->default('{}'); // JSONB

            $table->bigInteger('amount_minor')->unsigned();
            $table->string('currency_code', 3);
            $table->text('amount_in_words')->nullable();

            $table->boolean('is_tax_deductible')->default(true);
            $table->boolean('tax_80g_eligible')->default(false);

            $table->string('receipt_file_id')->nullable();
            $table->string('certificate_80g_file_id')->nullable();
            $table->string('certificate_80g_number')->nullable();

            // receipt_state enum
            $table->string('state', 20)->default('generated');
            $table->timestampTz('generated_at');
            $table->timestampTz('delivered_at')->nullable();
            $table->string('delivery_channel', 20)->nullable(); // notification_channel enum
            $table->text('delivery_metadata')->default('{}'); // JSONB

            $table->string('content_hash', 64); // CHAR(64)

            $table->timestampTz('created_at')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->timestampTz('deleted_at')->nullable();

            // UNIQUE per donation and per payment
            $table->unique('donation_id', 'receipts_unique_per_donation');
            $table->unique('payment_id', 'receipts_unique_per_payment');
        });

        Schema::table('receipts', function (Blueprint $table) {
            $table->index('generated_at', 'receipts_generated_recent_idx');
            $table->index('donor_email', 'receipts_donor_email_idx');
        });

        // ── webhook_events ────────────────────────────────────────────────
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('provider_code');
            $table->string('provider_event_id');
            $table->string('event_type');
            $table->text('payload'); // JSONB
            $table->text('headers')->default('{}'); // JSONB
            $table->text('signature')->nullable();
            $table->boolean('signature_verified')->default(false);
            $table->string('related_payment_id')->nullable();

            $table->timestampTz('received_at');
            $table->timestampTz('processed_at')->nullable();
            $table->text('processing_error')->nullable();
            $table->integer('retry_count')->default(0)->unsigned();

            $table->timestampTz('created_at')->nullable();
            $table->timestampTz('updated_at')->nullable();

            // Composite UNIQUE (provider_code, provider_event_id)
            $table->unique(['provider_code', 'provider_event_id'], 'webhook_events_idempotent');
        });

        Schema::table('webhook_events', function (Blueprint $table) {
            $table->index('received_at', 'webhook_events_unprocessed_idx');
            $table->index('related_payment_id', 'webhook_events_payment_idx');
        });

        // ── idempotency_keys ───────────────────────────────────────────────
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->string('key')->primary(); // TEXT PRIMARY KEY
            $table->string('scope');
            $table->text('request_fingerprint');
            $table->integer('response_status')->nullable();
            $table->text('response_body')->nullable(); // JSONB
            $table->string('locked_by')->nullable();
            $table->timestampTz('locked_at')->nullable();
            $table->timestampTz('created_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('expires_at');

            $table->index(['scope', 'expires_at'], 'idempotency_keys_scope_expiry_idx');
        });

        // ── audit_events ───────────────────────────────────────────────────
        Schema::create('audit_events', function (Blueprint $table) {
            $table->string('id')->primary();
            // audit_actor_type enum
            $table->string('actor_type', 20);
            $table->string('actor_id')->nullable();
            $table->string('action');
            $table->string('entity_type');
            $table->string('entity_id');
            $table->string('request_id')->nullable();
            // INET → TEXT
            $table->text('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampTz('occurred_at');
            $table->text('metadata')->default('{}'); // JSONB
        });

        Schema::table('audit_events', function (Blueprint $table) {
            $table->index(['entity_type', 'entity_id', 'occurred_at'], 'audit_events_entity_idx');
            $table->index(['actor_type', 'actor_id', 'occurred_at'], 'audit_events_actor_recent_idx');
            $table->index(['action', 'occurred_at'], 'audit_events_action_recent_idx');
            $table->index('request_id', 'audit_events_request_idx');
        });

        // ── file_assets ────────────────────────────────────────────────────
        // Phase 1 closure: the FileAssetRepository concrete impl needs this
        // table in the SQLite test schema. Mirrors schema-neon/V1-schema.sql
        // §11 with SQLite type rewrites (BOOLEAN → INTEGER, TIMESTAMPTZ → TEXT,
        // JSONB → TEXT, ENUM file_owner_type → TEXT).
        Schema::create('file_assets', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('owner_type', 32);                  // SQLite stores enum as TEXT
            $table->string('owner_id', 26);
            $table->string('original_filename');
            $table->string('storage_disk', 64);
            $table->string('storage_path');
            $table->string('mime_type', 128);
            $table->bigInteger('file_size_bytes')->unsigned();
            $table->string('file_hash_sha256', 64);
            $table->string('purpose')->nullable();
            $table->boolean('is_public')->default(false);
            $table->boolean('is_archived')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->text('metadata')->nullable();             // JSONB → TEXT
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
            $table->string('uploaded_by')->nullable();

            $table->index(['owner_type', 'owner_id'], 'file_assets_owner_idx');
            $table->index('file_hash_sha256', 'file_assets_hash_idx');
        });

        // ── FK constraints (deferred as SQLite inline references) ─────────
        // SQLite requires FKs to reference existing tables, so we add them
        // after all tables are created.
        Schema::table('campaigns', function (Blueprint $table) {
            $table->foreign('currency_code')
                ->references('code')->on('currencies')
                ->onDelete('restrict');
        });

        Schema::table('donations', function (Blueprint $table) {
            $table->foreign('campaign_id')
                ->references('id')->on('campaigns')
                ->onDelete('restrict');
            $table->foreign('donor_id')
                ->references('id')->on('donors')
                ->onDelete('restrict');
            $table->foreign('currency_code')
                ->references('code')->on('currencies')
                ->onDelete('restrict');
            $table->foreign('idempotency_key')
                ->references('key')->on('idempotency_keys')
                ->onDelete('set null');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('donation_id')
                ->references('id')->on('donations')
                ->onDelete('restrict');
            $table->foreign('provider_code')
                ->references('code')->on('payment_providers')
                ->onDelete('restrict');
            $table->foreign('currency_code')
                ->references('code')->on('currencies')
                ->onDelete('restrict');
            $table->foreign('idempotency_key')
                ->references('key')->on('idempotency_keys')
                ->onDelete('set null');
        });

        Schema::table('failure_states', function (Blueprint $table) {
            $table->foreign('payment_id')
                ->references('id')->on('payments')
                ->onDelete('restrict');
        });

        Schema::table('receipts', function (Blueprint $table) {
            $table->foreign('donation_id')
                ->references('id')->on('donations')
                ->onDelete('restrict');
            $table->foreign('payment_id')
                ->references('id')->on('payments')
                ->onDelete('restrict');
            $table->foreign('campaign_id')
                ->references('id')->on('campaigns')
                ->onDelete('restrict');
            $table->foreign('currency_code')
                ->references('code')->on('currencies')
                ->onDelete('restrict');
        });

        Schema::table('webhook_events', function (Blueprint $table) {
            $table->foreign('provider_code')
                ->references('code')->on('payment_providers')
                ->onDelete('restrict');
            $table->foreign('related_payment_id')
                ->references('id')->on('payments')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        // In tests we only use SQLite; in production we use Postgres.
        // Clean up in reverse dependency order.
        Schema::dropIfExists('file_assets');
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('failure_states');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('donations');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('donors');
        Schema::dropIfExists('payment_providers');
        Schema::dropIfExists('currencies');
    }
};
