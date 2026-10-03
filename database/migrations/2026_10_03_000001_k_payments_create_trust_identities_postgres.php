<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Canonical trust (donee) identity — DB plane.
 *
 * Creates `trust_identities` (single-row aggregate, key `canonical`) and
 * seeds the statutory credentials the receipt plane must print:
 *
 *   - TAN  BLRS60956A
 *   - 80G  F.No.S-504/80G/CIT/MYS/2011-12
 *   - 12A  S-504/12AA/CIT/MYs/2010-11
 *
 * Doctrine: the DB row is authoritative; config/receipts.php (+ TRUST_*
 * env) is ONLY the fallback for unseeded environments (fresh test DBs).
 * The receipts pipeline reads this row via
 * TrustIdentityRepositoryContract — transported by DataWorker, typed by
 * TypesWorker — so transport and generation stay semantically separated.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS trust_identities (
                key VARCHAR(32) PRIMARY KEY,
                name TEXT NULL,
                address TEXT NULL,
                email TEXT NULL,
                phone TEXT NULL,
                pan VARCHAR(10) NULL,
                tan VARCHAR(10) NULL,
                eighty_g_number TEXT NULL,
                twelve_a_number TEXT NULL,
                created_at TIMESTAMPTZ NULL,
                updated_at TIMESTAMPTZ NULL
            )
        SQL);

        DB::statement(<<<'SQL'
            COMMENT ON TABLE trust_identities IS
            'Canonical donee (trust) identity. Single-row aggregate (key=canonical) carrying the statutory credentials every receipt surface must print (TAN, PAN, 80G, 12A) plus presentation identity. Authoritative over config/receipts.php env fallback.'
        SQL);

        DB::table('trust_identities')->updateOrInsert(
            ['key' => 'canonical'],
            [
                'name' => 'SRI VISHWAGURU SRI SRI SRIRAM SHISHYAVRUNDHAM MAHASAMSTHANAM',
                'address' => null,
                'email' => null,
                'phone' => null,
                'pan' => null,
                'tan' => 'BLRS60956A',
                'eighty_g_number' => 'F.No.S-504/80G/CIT/MYS/2011-12',
                'twelve_a_number' => 'S-504/12AA/CIT/MYs/2010-11',
                'created_at' => DB::raw('NOW()'),
                'updated_at' => DB::raw('NOW()'),
            ],
        );
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::table('trust_identities')->where('key', 'canonical')->delete();
        DB::statement('DROP TABLE IF EXISTS trust_identities');
    }
};
