<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SQLite test mirror of k_payments trust_identities (+ canonical seed).
 * Mirrors 2026_10_03_000001 (postgres) — same columns, same seeded
 * statutory credentials. Guarded to sqlite only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        if (! Schema::hasTable('trust_identities')) {
            Schema::create('trust_identities', function (Blueprint $table): void {
                $table->string('key', 32)->primary();
                $table->text('name')->nullable();
                $table->text('address')->nullable();
                $table->text('email')->nullable();
                $table->text('phone')->nullable();
                $table->string('pan', 10)->nullable();
                $table->string('tan', 10)->nullable();
                $table->text('eighty_g_number')->nullable();
                $table->text('twelve_a_number')->nullable();
                $table->timestampTz('created_at')->nullable();
                $table->timestampTz('updated_at')->nullable();
            });
        }

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
                'created_at' => (new DateTimeImmutable())->format(DATE_ATOM),
                'updated_at' => (new DateTimeImmutable())->format(DATE_ATOM),
            ],
        );
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        DB::table('trust_identities')->where('key', 'canonical')->delete();
        Schema::dropIfExists('trust_identities');
    }
};
