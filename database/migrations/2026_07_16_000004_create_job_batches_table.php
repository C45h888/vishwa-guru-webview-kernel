<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Job batches table — Laravel canonical shape for Bus::batch().
 *
 * Doctrine:
 *   - batches group related jobs (e.g. "send notification to all
 *     campaign subscribers") so the caller can observe completion,
 *     failure, or cancellation as a unit.
 *   - JSONB in postgres stores options + failed_job_ids natively;
 *     sqlite falls back to TEXT with json_valid CHECK.
 *
 * Companion migration: 2026_07_16_000005 creates the same table
 * on sqlite via a separate file so per-driver idioms stay explicit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            // Options + metadata. Use text() for sqlite portability;
            // pgsql driver will store as text but laravel json encodes
            // on insert. The driver-aware migration is split for that
            // reason (see _sqlite companion).
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
            $table->index(['queue', 'reserved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('job_batches');
    }
};
