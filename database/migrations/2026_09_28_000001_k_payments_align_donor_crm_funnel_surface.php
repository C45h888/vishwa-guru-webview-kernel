<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Align donor persistence with the Donor entity/repository and enforce
     * that anonymous donations remain unlinked and contain no donor details.
     * Existing PostgreSQL schemas may have been provisioned externally, so
     * each column addition is guarded.
     */
    public function up(): void
    {
        if (! Schema::hasTable('donors')) {
            throw new RuntimeException('Donor CRM schema migration requires the donors table.');
        }

        Schema::table('donors', function (Blueprint $table): void {
            if (! Schema::hasColumn('donors', 'preferred_currency')) {
                $table->string('preferred_currency', 3)->default('INR');
            }
            if (! Schema::hasColumn('donors', 'anonymized_at')) {
                $table->timestampTz('anonymized_at')->nullable();
            }
            if (! Schema::hasColumn('donors', 'metadata')) {
                $table->json('metadata')->default('{}');
            }
            if (! Schema::hasColumn('donors', 'first_donation_at')) {
                $table->timestampTz('first_donation_at')->nullable();
            }
            if (! Schema::hasColumn('donors', 'last_donation_at')) {
                $table->timestampTz('last_donation_at')->nullable();
            }
            if (! Schema::hasColumn('donors', 'donation_count')) {
                $table->unsignedInteger('donation_count')->default(0);
            }
            if (! Schema::hasColumn('donors', 'lifetime_contribution_minor')) {
                $table->unsignedBigInteger('lifetime_contribution_minor')->default(0);
            }
        });

        if (! Schema::hasTable('donations')) {
            throw new RuntimeException('Donor CRM schema migration requires the donations table.');
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'donations_anonymous_no_pii'
    ) THEN
        ALTER TABLE donations
        ADD CONSTRAINT donations_anonymous_no_pii CHECK (
            NOT is_anonymous OR (
                donor_id IS NULL
                AND donor_name_snapshot IS NULL
                AND donor_email_snapshot IS NULL
                AND donor_phone_snapshot IS NULL
                AND donor_pan_snapshot IS NULL
                AND donor_address_snapshot IS NULL
                AND dedication IS NULL
                AND donor_message IS NULL
            )
        ) NOT VALID;
    END IF;
END
$$;
SQL);
        } elseif (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
CREATE TRIGGER IF NOT EXISTS donations_anonymous_no_pii_insert
BEFORE INSERT ON donations
WHEN NEW.is_anonymous = 1 AND (
    NEW.donor_id IS NOT NULL
    OR NEW.donor_name_snapshot IS NOT NULL
    OR NEW.donor_email_snapshot IS NOT NULL
    OR NEW.donor_phone_snapshot IS NOT NULL
    OR NEW.donor_pan_snapshot IS NOT NULL
    OR NEW.donor_address_snapshot IS NOT NULL
    OR NEW.dedication IS NOT NULL
    OR NEW.donor_message IS NOT NULL
)
BEGIN
    SELECT RAISE(ABORT, 'anonymous donations cannot be linked to donor PII');
END;

CREATE TRIGGER IF NOT EXISTS donations_anonymous_no_pii_update
BEFORE UPDATE ON donations
WHEN NEW.is_anonymous = 1 AND (
    NEW.donor_id IS NOT NULL
    OR NEW.donor_name_snapshot IS NOT NULL
    OR NEW.donor_email_snapshot IS NOT NULL
    OR NEW.donor_phone_snapshot IS NOT NULL
    OR NEW.donor_pan_snapshot IS NOT NULL
    OR NEW.donor_address_snapshot IS NOT NULL
    OR NEW.dedication IS NOT NULL
    OR NEW.donor_message IS NOT NULL
)
BEGIN
    SELECT RAISE(ABORT, 'anonymous donations cannot be linked to donor PII');
END;
SQL);
        }
    }

    public function down(): void
    {
        // Keep additive donor data on rollback; remove only enforcement.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE donations DROP CONSTRAINT IF EXISTS donations_anonymous_no_pii');
        } elseif (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS donations_anonymous_no_pii_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS donations_anonymous_no_pii_update');
        }
    }
};
