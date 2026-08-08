<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Failed jobs table — direct DDL on postgres.
 *
 * Doctrine: schema modifications through Laravel migrations (no raw
 * SQL drift). This migration uses raw DDL because Laravel's
 * Schema::create with ->unique() generates an inline UNIQUE followed
 * by an ALTER TABLE ADD CONSTRAINT on Postgres 16, which double-creates
 * the constraint and aborts the transaction. Direct DDL avoids the
 * grammar's double-create behavior.
 *
 * SQLite testing path uses the canonical Laravel stub via
 * `php artisan queue:failed-table`; not needed here because this
 * migration only runs against the postgres connection.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver !== 'pgsql' && $driver !== 'postgres') {
            // SQLite + others: defer to the standard Laravel schema builder
            // (which works correctly on sqlite). Use the canonical stub.
            \Illuminate\Support\Facades\Schema::create('failed_jobs', function ($table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
            return;
        }

        // Postgres: direct DDL. Wrap in a savepoint so partial failure
        // doesn't poison the outer transaction Laravel wraps each
        // migration in.
        DB::transaction(function () {
            DB::statement('DROP TABLE IF EXISTS failed_jobs');
            DB::statement(<<<'SQL'
                CREATE TABLE failed_jobs (
                    id BIGSERIAL PRIMARY KEY,
                    uuid VARCHAR(255) NOT NULL,
                    connection TEXT NOT NULL,
                    queue TEXT NOT NULL,
                    payload TEXT NOT NULL,
                    exception TEXT NOT NULL,
                    failed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
            SQL);
            DB::statement('CREATE UNIQUE INDEX failed_jobs_uuid_unique ON failed_jobs (uuid)');
        });
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS failed_jobs');
    }
};
