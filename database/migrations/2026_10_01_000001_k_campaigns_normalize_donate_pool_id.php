<?php

declare(strict_types=1);

use App\Shared\Support\UlidGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Re-type the pooled-fund campaign to a canonical typed ID.
 *
 * Doctrine: the typed-ID invariant is EntityId::PATTERN
 * (`/^[a-z][a-z0-9_]*_[0-9A-Z]{26}$/`) — a 26-char Crockford-Base32
 * ULID after the type prefix. The public Razorpay checkout contract
 * (App\Payments\Http\Requests\RazorpayCheckoutRequest) enforces the same
 * shape with `size:35` + `regex:/^campaign_[0-9A-HJKMNP-TV-Z]{26}$/`, and
 * the controller parses it with EntityId::fromString().
 *
 * The seeded pooled-fund campaign shipped with a hand-written slug-like
 * ID, `campaign_general_fund_2026` (26 chars). That value satisfies
 * neither the 35-char size rule nor the ULID regex, so every donation to
 * the pooled fund failed checkout validation ("The campaign id field must
 * be 35 characters. (and 1 more error)").
 *
 * The earlier retype migration (2026_07_26_000002) only handled
 * `cmp_<24hex>` legacy IDs, so this slug-like ID slipped through.
 *
 * This migration re-types ONLY non-canonical campaign IDs and rewrites the
 * two child FKs that reference campaigns(id): donations.campaign_id and
 * receipts.campaign_id. The pooled fund is pinned to a fixed canonical ID
 * so config/campaigns.php and resources/js/shared/lib/pooled-fund.ts stay
 * in sync; any other non-canonical row gets a freshly generated ULID.
 *
 * Idempotent: the non-canonical selector matches no rows after a run.
 * down() is a no-op — the legacy ID is not recoverable.
 *
 * NOTE FOR FUTURE EDITS: if you change the pooled-fund ID here, update
 *   - config/campaigns.php  ('donation_pool_campaign_id')
 *   - resources/js/shared/lib/pooled-fund.ts  (POOLED_FUND_ID)
 *   - database/seeders/ProductionSeeder.php   (SAMPLE_CAMPAIGN['id'])
 *   - align_campaign_covers.php               (first cover campaign_id)
 */
return new class extends Migration
{
    /**
     * Legacy ID shipped by the original seed.
     */
    private const LEGACY_POOL_ID = 'campaign_general_fund_2026';

    /**
     * Pinned canonical ID for the pooled fund. Keep in sync with the
     * four references listed in the class docblock.
     */
    private const CANONICAL_POOL_ID = 'campaign_01M3V9QP311Z564SCJ9GW47HE7';

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Neon / Laravel prepared-statement workaround: the Neon pooler
        // aborts a transaction after the first statement
        // (SQLSTATE[25P02]). Roll back the framework's wrapper so each
        // statement runs on auto-commit.
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        // 1. Build old → new map for every non-canonical campaign ID.
        $rows = DB::select(
            "SELECT id FROM campaigns WHERE id !~ '^campaign_[0-9A-HJKMNP-TV-Z]{26}$'"
        );

        /** @var array<string, string> $map */
        $map = [];
        foreach ($rows as $row) {
            $oldId = (string) $row->id;
            $map[$oldId] = $oldId === self::LEGACY_POOL_ID
                ? self::CANONICAL_POOL_ID
                : 'campaign_'.UlidGenerator::generate();
        }

        if ($map === []) {
            return;
        }

        // 2. Drop the FKs that block re-typing the parent IDs.
        $fkReaddStatements = [
            ['table' => 'donations', 'name' => 'donations_campaign_fk',
             'def' => 'FOREIGN KEY (campaign_id) REFERENCES campaigns(id)'],
            ['table' => 'receipts', 'name' => 'receipts_campaign_fk',
             'def' => 'FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE RESTRICT'],
        ];

        $dropped = [];
        foreach ($fkReaddStatements as $fk) {
            try {
                DB::statement("ALTER TABLE {$fk['table']} DROP CONSTRAINT IF EXISTS {$fk['name']}");
                $dropped[] = $fk;
            } catch (\Throwable) {
                // FK absent on this DB variant — continue.
            }
        }

        try {
            // 3. Re-point child FKs at the NEW IDs (parents still hold OLD IDs).
            foreach ($map as $oldId => $newId) {
                DB::update(
                    'UPDATE donations SET campaign_id = :new_id WHERE campaign_id = :old_id',
                    ['new_id' => $newId, 'old_id' => $oldId]
                );
                DB::update(
                    'UPDATE receipts SET campaign_id = :new_id WHERE campaign_id = :old_id',
                    ['new_id' => $newId, 'old_id' => $oldId]
                );
            }

            // 4. Re-type the parent rows.
            foreach ($map as $oldId => $newId) {
                DB::update(
                    'UPDATE campaigns SET id = :new_id WHERE id = :old_id',
                    ['new_id' => $newId, 'old_id' => $oldId]
                );
            }
        } finally {
            // 5. Re-add the FKs — this validates referential integrity.
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
};
