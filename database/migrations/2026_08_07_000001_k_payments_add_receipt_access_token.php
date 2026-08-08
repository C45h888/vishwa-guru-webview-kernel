<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add receipt_access_token column to the `receipts` table.
 *
 * The receipt_number (`TR-2026-000001`, `TR-2026-000002`, …) is a
 * sequentially enumerable FY-scoped counter; before this migration the
 * URL `/receipts/{number}` was the only access token, which made every
 * 80G receipt (containing donor PAN, full address, email, phone) a
 * public IDOR.
 *
 * After this migration every receipt carries a 43-char URL-safe random
 * access_token (Receipt::mintAccessToken) and the public routes require
 * `?t=<token>` matching the column. Existing rows are backfilled with
 * freshly minted tokens.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table): void {
            $table->string('access_token', 64)->nullable()->after('delivery_metadata');
        });

        // Backfill existing rows in PHP so the same generator is used
        // for both backfill and new issues. MySQL/SQLite both honour
        // UPDATE … WHERE id IN (…) with bindings.
        $now = (new DateTimeImmutable())->format(DATE_ATOM);
        $rows = \Illuminate\Support\Facades\DB::table('receipts')
            ->whereNull('access_token')
            ->select('id')
            ->get();
        foreach ($rows as $row) {
            $token = \App\Payments\Domain\Entities\Receipt::mintAccessToken();
            \Illuminate\Support\Facades\DB::table('receipts')
                ->where('id', $row->id)
                ->update([
                    'access_token' => $token,
                    'updated_at' => $now,
                ]);
        }

        // Enforce NOT NULL without doctrine/dbal (the repo deliberately
        // avoids that dependency). PostgreSQL: native ALTER COLUMN SET
        // NOT NULL. SQLite: not supported via ALTER, but the test DB is
        // rebuilt fresh each run so the schema-level unique index below
        // is the operative constraint there; the application layer
        // (Receipt always mints a token) guarantees non-null writes.
        $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            \Illuminate\Support\Facades\DB::statement(
                'ALTER TABLE receipts ALTER COLUMN access_token SET NOT NULL'
            );
        }

        Schema::table('receipts', function (Blueprint $table): void {
            $table->unique('access_token');
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table): void {
            $table->dropUnique(['access_token']);
            $table->dropColumn('access_token');
        });
    }
};
