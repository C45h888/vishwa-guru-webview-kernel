<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Align the `receipts` table with the typed Receipt entity.
 *
 * Three columns the entity has always carried were never present in the
 * schema, which meant typed receipt state could not load properly:
 *
 *   - `metadata`          — free-form JSON payload the entity round-trips
 *     (ReceiptRepository::save() already writes it; the SQLite test
 *     mirror never declared it, so every save() in tests failed).
 *   - `delivery_status`   — the receipt_delivery_status lifecycle
 *     (pending|delivered|failed|bounced) tracked separately from
 *     `state` (receipt_state). Receipt::fromRow() has a fallback shim
 *     that fabricates `pending` when the column is absent — with this
 *     migration the shim becomes unnecessary and delivery state finally
 *     loads from the database.
 *   - `delivery_address`  — the delivery target; previously a phantom
 *     property on the entity (read but never persisted).
 *
 * All three are additive and defaulted, so the migration is safe on both
 * PostgreSQL (live Neon branch) and the SQLite test mirror.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table): void {
            $table->text('metadata')->default('{}')->after('delivery_metadata');
            $table->string('delivery_status', 20)->default('pending')->after('state');
            $table->text('delivery_address')->nullable()->after('delivery_channel');
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table): void {
            $table->dropColumn(['metadata', 'delivery_status', 'delivery_address']);
        });
    }
};
