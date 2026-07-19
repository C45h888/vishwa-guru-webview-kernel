<?php

declare(strict_types=1);

namespace App\Queue\Contracts;

use App\Queue\ValueObjects\QueuedJob;

/**
 * QueueConnectorContract — single entry point for queue operations.
 *
 * Phase 2: contract only.
 * Phase 2 close: LaravelQueueConnector implementation.
 *
 * Doctrine alignment:
 *   - Repositories encapsulate persistence. The queue equivalent is the
 *     connector — service code does NOT reach for Queue:: facade.
 *     It goes through this contract so that:
 *     (a) the queue topology (which driver, which connection, which
 *         retry policy) is decided in one place, not scattered through
 *         the codebase;
 *     (b) tests can substitute an in-memory fake without booting a
 *         queue worker;
 *     (c) the doctrine (Queue:: facade forbidden in domain code) has
 *         a single boundary to enforce.
 *
 * Service code that needs to dispatch a job depends on this contract,
 * not on Illuminate\Contracts\Bus\Dispatcher or Queue:: facade.
 */
interface QueueConnectorContract
{
    /**
     * Dispatch a job onto the queue.
     *
     * Returns a JobId (string) that uniquely identifies the enqueued
     * job. The caller may use this for tracking, retry, or to check
     * job status.
     *
     * @param  QueuedJob  $job  The job to dispatch, with typed payload.
     * @return string           The JobId (Laravel-generated UUID).
     */
    public function dispatch(QueuedJob $job): string;

    /**
     * Get the depth (number of pending jobs) of a queue.
     *
     * @param  string|null  $queue  Queue name; null = default queue.
     * @return int                   Number of pending jobs.
     */
    public function size(?string $queue = null): int;

    /**
     * Get the count of failed jobs (lifetime total).
     *
     * @return int
     */
    public function failedCount(): int;

    /**
     * List failed jobs, newest first.
     *
     * @return array<int, array{uuid: string, queue: string, connection: string, failed_at: string, exception_preview: string}>
     */
    public function listFailed(int $limit = 50): array;

    /**
     * Retry a failed job by its UUID.
     *
     * @param  string  $uuid
     * @return bool              True on success.
     */
    public function retryFailed(string $uuid): bool;

    /**
     * Verify the queue is reachable.
     *
     * MUST NOT throw — failures are reported, not raised. Same
     * doctrine as RedisConnectorContract::ping().
     *
     * @return bool
     */
    public function ping(): bool;

    /**
     * Get the queue driver name (e.g. "redis", "database", "sync").
     */
    public function driver(): string;

    /**
     * Atomically reserve an idempotency key for job-level dedupe.
     *
     * Returns:
     *   - true  — key was reserved; caller owns this slot, may proceed.
     *   - false — key already existed (duplicate detected) OR the
     *             reservation failed (e.g. backend unreachable).
     *
     * Doctrine:
     *   - MUST use atomic primitives (SET NX EX for Redis,
     *     INSERT ... ON CONFLICT for database). No read-then-write races.
     *   - MUST NOT throw. Failure is reported via false; callers
     *     choose whether to abort or run-with-dedupe-off.
     *   - Caller owns key naming. Convention: `idem:queue:{jobClass}:{uuid}`.
     *
     * Used by AbstractQueuedJob::execute() as the SETEX fast-path
     * gate before dispatching the job's domain handle() logic.
     */
    public function reserveIdempotencyKey(string $key, int $ttlSeconds): bool;
}
