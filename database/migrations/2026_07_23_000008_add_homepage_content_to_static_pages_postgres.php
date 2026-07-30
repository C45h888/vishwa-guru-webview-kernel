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
            'ALTER TABLE static_pages ADD COLUMN IF NOT EXISTS homepage_content JSONB NULL'
        );

        DB::statement(<<<'SQL'
            COMMENT ON COLUMN static_pages.homepage_content IS
            'Versioned homepage prose and nullable cms_media_assets IDs. JSON media references are validated by the CMS value-object layer because JSON paths cannot carry foreign keys.'
        SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            'ALTER TABLE static_pages DROP COLUMN IF EXISTS homepage_content'
        );
    }
};
