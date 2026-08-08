<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        if (! Schema::hasColumn('static_pages', 'homepage_content')) {
            Schema::table('static_pages', function (Blueprint $table): void {
                $table->text('homepage_content')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        if (Schema::hasColumn('static_pages', 'homepage_content')) {
            Schema::table('static_pages', function (Blueprint $table): void {
                $table->dropColumn('homepage_content');
            });
        }
    }
};
