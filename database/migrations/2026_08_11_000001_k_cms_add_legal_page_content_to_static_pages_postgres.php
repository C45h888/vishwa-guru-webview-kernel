<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            'ALTER TABLE static_pages ADD COLUMN IF NOT EXISTS legal_page_content JSONB NULL'
        );

        DB::statement(<<<'SQL'
            COMMENT ON COLUMN static_pages.legal_page_content IS
            'Versioned Legal-page content (intro + certificates[]). The certificates array carries the public regulatory registrations (80G, 12A, Power of Attorney, TAN). Image references are validated by the CMS value-object layer because JSON paths cannot carry foreign keys.'
        SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            'ALTER TABLE static_pages DROP COLUMN IF EXISTS legal_page_content'
        );
    }
};
