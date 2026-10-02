<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Persist required policy acknowledgements and the independent optional
     * marketing-email choice on each donation checkout record.
     */
    public function up(): void
    {
        if (! Schema::hasTable('donations')) {
            throw new RuntimeException('Checkout policy evidence requires the donations table.');
        }

        Schema::table('donations', function (Blueprint $table): void {
            if (! Schema::hasColumn('donations', 'terms_version')) {
                $table->string('terms_version', 32)->nullable();
            }
            if (! Schema::hasColumn('donations', 'terms_accepted_at')) {
                $table->timestampTz('terms_accepted_at')->nullable();
            }
            if (! Schema::hasColumn('donations', 'privacy_notice_version')) {
                $table->string('privacy_notice_version', 32)->nullable();
            }
            if (! Schema::hasColumn('donations', 'privacy_notice_acknowledged_at')) {
                $table->timestampTz('privacy_notice_acknowledged_at')->nullable();
            }
            if (! Schema::hasColumn('donations', 'marketing_email_consent_version')) {
                $table->string('marketing_email_consent_version', 48)->nullable();
            }
            if (! Schema::hasColumn('donations', 'marketing_email_consented_at')) {
                $table->timestampTz('marketing_email_consented_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('donations')) {
            return;
        }

        Schema::table('donations', function (Blueprint $table): void {
            $columns = [
                'terms_version',
                'terms_accepted_at',
                'privacy_notice_version',
                'privacy_notice_acknowledged_at',
                'marketing_email_consent_version',
                'marketing_email_consented_at',
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('donations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
