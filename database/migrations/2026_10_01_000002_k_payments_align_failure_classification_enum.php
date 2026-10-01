<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Align the `failure_classification` PostgreSQL enum with the
 * FailureClassification domain enum.
 *
 * Drift found 2026-10-01: the live enum only carried two values
 * (`recoverable`, `terminal`), while the PHP enum
 * (App\Payments\Domain\Enums\FailureClassification) — and
 * config/payments.php `retry_policy.classifications` — define four:
 *   recoverable_transient, recoverable_terminal, terminal_invalid,
 *   terminal_fraud.
 *
 * Because FailureStateRepository::save() persists the PHP enum value
 * verbatim, every failure-state write raised
 *   SQLSTATE[22P02] invalid input value for enum failure_classification
 * which in turn made receipt escalation and the retry/failure pipeline
 * unable to record anything.
 *
 * This migration only ADDS the canonical values; it does not remove the
 * legacy `recoverable`/`terminal` labels, so any pre-existing rows remain
 * readable. Idempotent via ADD VALUE IF NOT EXISTS.
 */
return new class extends Migration
{
    /**
     * Canonical values from FailureClassification.
     *
     * @var array<int, string>
     */
    private const CANONICAL = [
        'recoverable_transient',
        'recoverable_terminal',
        'terminal_invalid',
        'terminal_fraud',
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // ALTER TYPE ... ADD VALUE must not sit inside the migration
        // framework's transaction on older Postgres, and Neon's pooler
        // aborts wrapping transactions after the first statement. Run on
        // auto-commit, matching the retype migration's pattern.
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        foreach (self::CANONICAL as $value) {
            DB::statement("ALTER TYPE failure_classification ADD VALUE IF NOT EXISTS '{$value}'");
        }
    }

    public function down(): void
    {
        // PostgreSQL cannot drop enum values. down() is intentionally a no-op.
    }
};
