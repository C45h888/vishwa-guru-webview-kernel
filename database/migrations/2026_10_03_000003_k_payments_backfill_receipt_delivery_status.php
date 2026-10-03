<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Backfill receipts.delivery_status for deliveries that completed before
 * the column was persisted by ReceiptRepository.
 *
 * 2026-10-03 wave: delivery_status existed as a column (added by
 * 2026_10_02_000001) but ReceiptRepository::update() never wrote it, so
 * every receipt stayed 'pending' in the DB while audit_events held the
 * real delivery truth. This backfill reconciles the durable state for
 * rows whose delivered_at timestamp proves delivery already happened.
 *
 * Without it, the receipts:reconcile email backfill would re-send emails
 * to donors whose receipts were already delivered.
 *
 * Idempotent by construction (targets only rows still in 'pending').
 * down() is a no-op: reverting would re-introduce the defect this fixes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('receipts')) {
            return;
        }

        if (! Schema::hasColumn('receipts', 'delivery_status')) {
            return;
        }

        DB::table('receipts')
            ->whereNotNull('delivered_at')
            ->where('delivery_status', 'pending')
            ->update([
                'delivery_status' => 'delivered',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Deliberate no-op: the backfilled state is the correct state.
        // There is no meaningful prior state to restore.
    }
};