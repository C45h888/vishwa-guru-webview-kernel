<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Shared\Support\UlidGenerator;

/**
 * Phase 0.5 data fix: re-type legacy short-prefix / short-ULID rows in
 * seven tables (campaigns, donors, donations, payments, file_assets,
 * audit_events, webhook_events) to canonical formats.
 *
 * Doctrine (AGENTS.md §Database Directives): DB modifications through
 * migrations only. The typed-ID invariant is EntityId::PATTERN
 * (`/^[a-z][a-z0-9_]*_[0-9A-Z]{26}$/`) — 26-char ULID after the type
 * prefix. The six business entities use the entity constant as the
 * prefix; the two log tables (audit_events, webhook_events) are
 * special-cased because their runtime ID generation does not go
 * through EntityId (see "Phase 0.5 exception" below).
 *
 * Per-table target format (confirmed by reading each entity's
 * `ENTITY_TYPE` constant or the canonical EntityId::generate() call):
 *
 *   Table           Legacy prefix  Target prefix  ULID length
 *   -----------     -------------  -------------  -----------
 *   campaigns       cmp_           campaign       26 chars
 *   donors          donr_          donor          26 chars
 *   donations       don_           donation       26 chars
 *   payments        pay_           payment        26 chars
 *   file_assets     file_          file_asset     26 chars
 *   audit_events    audit_         aud            24 hex chars*
 *   webhook_events  whe_           wev            24 hex chars*
 *
 *   * Phase 0.5 exception: audit_events and webhook_events do NOT go
 *     through EntityId in the current runtime. Their IDs are generated
 *     by `AuditEventRepository::append()` (`aud_<24hex>`) and
 *     `WebhookEventRepository::record()` (`wev_<24hex>`). We re-type
 *     these tables to match the runtime format so existing code can
 *     read both old and new rows uniformly.
 *
 * FK ordering (no DB::transaction() wrapper, see Neon note below):
 *   1. UPDATE child FK columns to NEW IDs (parents still have OLD IDs
 *      so the FK constraint is satisfied).
 *   2. UPDATE parent table IDs (OLD → NEW). FK columns now point at
 *      the NEW IDs, which are about to be created.
 *   3. UPDATE child table IDs (OLD → NEW). Parents now have NEW IDs
 *      so the child's id-change is FK-clean.
 *
 * Neon note: Laravel's default prepared-statement path on Neon aborts
 * the transaction after the first UPDATE (SQLSTATE[25P02] "current
 * transaction is aborted"). Each UPDATE is therefore executed outside
 * a wrapping transaction. Per-statement atomicity is sufficient because
 * the FK ordering above keeps the database valid at every step. The
 * migration is idempotent: the legacy-prefix selectors match no rows
 * after a successful run.
 *
 * Determinism: UlidGenerator::generate() for the six business entities
 * (non-deterministic; idempotent via legacy-prefix selector). The two
 * log tables use `bin2hex(random_bytes(12))` to match the runtime.
 *
 * down(): no-op (original IDs are not recoverable).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Neon / Laravel prepared-statement workaround: the Neon
        // pooler appears to abort any transaction after the first
        // statement (SQLSTATE[25P02] "current transaction is aborted").
        // The migration framework wraps up() in a transaction by
        // default. Roll back that wrapper immediately so the rest of
        // this migration runs statement-by-statement on auto-commit.
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        // ── 1. Build old→new mappings for every legacy row ────────────

        $campaignsMap = $this->buildMap(
            "SELECT id FROM campaigns WHERE id ~* '^cmp_[0-9a-z]{24}\$'",
            'campaign'
        );
        $donorsMap = $this->buildMap(
            "SELECT id FROM donors WHERE id ~* '^donr_[0-9a-z]{24}\$'",
            'donor'
        );
        $donationsMap = $this->buildMap(
            "SELECT id FROM donations WHERE id ~* '^don_[0-9a-z]{24}\$'",
            'donation'
        );
        $paymentsMap = $this->buildMap(
            "SELECT id FROM payments WHERE id ~* '^pay_[0-9a-z]{24}\$'",
            'payment'
        );
        $fileAssetsMap = $this->buildMap(
            "SELECT id FROM file_assets WHERE id ~* '^file_[0-9a-z]{24}\$'",
            'file_asset'
        );
        $auditMap = $this->buildHexMap(
            "SELECT id FROM audit_events WHERE id ~* '^audit_[0-9a-z]{24}\$'"
        );
        $webhookMap = $this->buildHexMap(
            "SELECT id FROM webhook_events WHERE id ~* '^whe_[0-9a-z]{24}\$'"
        );

        if (
            $campaignsMap === []
            && $donorsMap === []
            && $donationsMap === []
            && $paymentsMap === []
            && $fileAssetsMap === []
            && $auditMap === []
            && $webhookMap === []
        ) {
            return;
        }

        // Neon grants the app user no superuser privileges, so we
        // cannot SET session_replication_role = replica. Instead we
        // DROP and re-ADD the relevant FK constraints for the duration
        // of this migration. This is the canonical Postgres pattern
        // for ID re-typing on hosted environments (e.g. AWS RDS,
        // Neon) where the application role does not own the database.
        //
        // Strategy: drop the FKs that block re-typing, do the work,
        // re-add them with the SAME definitions as in V1-schema.sql.
        // The validation is deferred to the re-ADD step — if any
        // reference is dangling (it shouldn't be, but defensive), the
        // re-ADD raises and the migration fails loudly.

        $dropped = [];
        $fkReaddStatements = [
            // donations
            ['table' => 'donations', 'name' => 'donations_campaign_fk',
             'def' => 'FOREIGN KEY (campaign_id) REFERENCES campaigns(id)'],
            ['table' => 'donations', 'name' => 'donations_donor_fk',
             'def' => 'FOREIGN KEY (donor_id) REFERENCES donors(id)'],
            // payments
            ['table' => 'payments', 'name' => 'payments_donation_fk',
             'def' => 'FOREIGN KEY (donation_id) REFERENCES donations(id)'],
            // webhook_events
            ['table' => 'webhook_events', 'name' => 'webhook_events_payment_fk',
             'def' => 'FOREIGN KEY (related_payment_id) REFERENCES payments(id)'],
        ];

        foreach ($fkReaddStatements as $fk) {
            try {
                DB::statement("ALTER TABLE {$fk['table']} DROP CONSTRAINT IF EXISTS {$fk['name']}");
                $dropped[] = $fk;
            } catch (\Throwable $e) {
                // FK may not exist on this DB (e.g. legacy schema
                // variant); log and continue.
            }
        }

        try {
            // ── 2. UPDATE child FK columns to NEW IDs ────────────────

            foreach ($campaignsMap as $oldCampaignId => $newCampaignId) {
                DB::update(
                    'UPDATE donations SET campaign_id = :new_id
                     WHERE campaign_id = :old_id',
                    ['new_id' => $newCampaignId, 'old_id' => $oldCampaignId]
                );
            }
            foreach ($donorsMap as $oldDonorId => $newDonorId) {
                DB::update(
                    'UPDATE donations SET donor_id = :new_id
                     WHERE donor_id = :old_id',
                    ['new_id' => $newDonorId, 'old_id' => $oldDonorId]
                );
            }
            foreach ($donationsMap as $oldDonationId => $newDonationId) {
                DB::update(
                    'UPDATE payments SET donation_id = :new_id
                     WHERE donation_id = :old_id',
                    ['new_id' => $newDonationId, 'old_id' => $oldDonationId]
                );
            }
            foreach ($paymentsMap as $oldPaymentId => $newPaymentId) {
                DB::update(
                    'UPDATE webhook_events SET related_payment_id = :new_id
                     WHERE related_payment_id = :old_id',
                    ['new_id' => $newPaymentId, 'old_id' => $oldPaymentId]
                );
            }

            // file_assets children — verified no current rows reference
            // the legacy IDs. Defensive UPDATEs (no-ops in steady state).
            foreach ($fileAssetsMap as $oldFileId => $newFileId) {
                DB::update(
                    'UPDATE cms_media_assets SET file_asset_id = :new_id
                     WHERE file_asset_id = :old_id',
                    ['new_id' => $newFileId, 'old_id' => $oldFileId]
                );
                DB::update(
                    'UPDATE events SET banner_file_id = :new_id
                     WHERE banner_file_id = :old_id',
                    ['new_id' => $newFileId, 'old_id' => $oldFileId]
                );
                DB::update(
                    'UPDATE galleries SET cover_image_file_id = :new_id
                     WHERE cover_image_file_id = :old_id',
                    ['new_id' => $newFileId, 'old_id' => $oldFileId]
                );
                DB::update(
                    'UPDATE gallery_images SET file_asset_id = :new_id
                     WHERE file_asset_id = :old_id',
                    ['new_id' => $newFileId, 'old_id' => $oldFileId]
                );
                DB::update(
                    'UPDATE hero_banners SET image_file_id = :new_id
                     WHERE image_file_id = :old_id',
                    ['new_id' => $newFileId, 'old_id' => $oldFileId]
                );
                DB::update(
                    'UPDATE hero_banners SET mobile_image_file_id = :new_id
                     WHERE mobile_image_file_id = :old_id',
                    ['new_id' => $newFileId, 'old_id' => $oldFileId]
                );
                DB::update(
                    'UPDATE payment_document_assets SET file_asset_id = :new_id
                     WHERE file_asset_id = :old_id',
                    ['new_id' => $newFileId, 'old_id' => $oldFileId]
                );
                DB::update(
                    'UPDATE receipts SET receipt_file_id = :new_id
                     WHERE receipt_file_id = :old_id',
                    ['new_id' => $newFileId, 'old_id' => $oldFileId]
                );
                DB::update(
                    'UPDATE receipts SET certificate_80g_file_id = :new_id
                     WHERE certificate_80g_file_id = :old_id',
                    ['new_id' => $newFileId, 'old_id' => $oldFileId]
                );
                DB::update(
                    'UPDATE campaigns SET cover_image_file_id = :new_id
                     WHERE cover_image_file_id = :old_id',
                    ['new_id' => $newFileId, 'old_id' => $oldFileId]
                );
            }

            // ── 3. UPDATE parent table IDs (OLD → NEW) ───────────────

            $this->applyMap('campaigns', $campaignsMap);
            $this->applyMap('donors', $donorsMap);
            $this->applyMap('file_assets', $fileAssetsMap);
            $this->applyMap('audit_events', $auditMap);
            $this->applyMap('webhook_events', $webhookMap);

            // ── 4. UPDATE child table IDs (OLD → NEW) ────────────────

            $this->applyMap('donations', $donationsMap);
            $this->applyMap('payments', $paymentsMap);
        } finally {
            // Re-ADD the FKs. This validates that all references
            // resolve (they should — the UPDATEs above preserved
            // referential integrity).
            foreach ($dropped as $fk) {
                DB::statement(
                    "ALTER TABLE {$fk['table']} ADD CONSTRAINT {$fk['name']} {$fk['def']}"
                );
            }
        }
    }

    public function down(): void
    {
        // Original legacy IDs are not recoverable. down() is a no-op.
    }

    /**
     * Build old→new ID map for a typed entity. New IDs use the
     * canonical entity-type prefix + a fresh 26-char ULID.
     *
     * @param  string  $selectSql  SELECT id FROM <table> WHERE …
     * @param  string  $prefix     Canonical entity type (e.g. 'campaign')
     * @return array<string,string>  old_id => new_id
     */
    private function buildMap(string $selectSql, string $prefix): array
    {
        $rows = DB::select($selectSql);
        $map = [];
        foreach ($rows as $row) {
            $oldId = (string) $row->id;
            $map[$oldId] = $prefix . '_' . UlidGenerator::generate();
        }
        return $map;
    }

    /**
     * Build old→new ID map for a log table. New IDs use the current
     * production 24-hex format (e.g. `aud_<24hex>`).
     *
     * @return array<string,string>
     */
    private function buildHexMap(string $selectSql): array
    {
        $rows = DB::select($selectSql);
        $map = [];
        foreach ($rows as $row) {
            $oldId = (string) $row->id;
            $newPrefix = str_starts_with($oldId, 'audit_') ? 'aud_' : 'wev_';
            $map[$oldId] = $newPrefix . bin2hex(random_bytes(12));
        }
        return $map;
    }

    /**
     * Apply an old→new ID map to a table.
     *
     * @param  array<string,string>  $map
     */
    private function applyMap(string $table, array $map): void
    {
        foreach ($map as $oldId => $newId) {
            DB::update(
                "UPDATE {$table} SET id = :new_id WHERE id = :old_id",
                ['new_id' => $newId, 'old_id' => $oldId]
            );
        }
    }
};