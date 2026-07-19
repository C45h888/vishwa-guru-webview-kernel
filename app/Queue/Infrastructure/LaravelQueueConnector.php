<?php

declare(strict_types=1);

namespace App\Queue\Infrastructure;

use App\Queue\Contracts\QueueConnectorContract;
use App\Queue\ValueObjects\QueuedJob;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Support\Facades\Queue;
use Throwable;

/**
 * LaravelQueueConnector — the canonical QueueConnectorContract impl.
 *
 * Doctrine: business logic depends on contracts, not on framework
 * facades. This is the same pattern as LaravelDbAdapter (Persistence)
 * and LaravelRedisConnector (Redis). Service code depends on
 * QueueConnectorContract; this adapter is the only place that
 * touches the Queue facade.
 *
 * Failure semantics:
 *   - ping() swallows Throwable; returns false (doctrine: fail-open
 *     for cache/queue paths, never block on queue health).
 *   - dispatch() does NOT swallow: dispatch failures bubble up
 *     because the caller needs to know the job didn't enqueue.
 *   - size(), failedCount(), listFailed(), retryFailed() may throw
 *     if the underlying DB/Redis is unreachable; callers handle.
 */
final class LaravelQueueConnector implements QueueConnectorContract
{
    public function __construct(
        private readonly QueueFactory $factory,
    ) {}

    public function dispatch(QueuedJob $job): string
    {
        $payload = $job->payload;

        // We push the job class via the queue manager. The job class
        // extends AbstractQueuedJob and implements ShouldQueue; the
        // queue manager handles serialization, retry, and backoff via
        // the job class's own properties.
        //
        // We dispatch through the Queue facade here because the
        // QueueFactory contract doesn't expose dispatch(ShouldQueue).
        // The contract is the *boundary* — service code calls dispatch
        // on the contract and gets a JobId back; the implementation
        // is free to use whatever Laravel primitive it wants.
        Queue::push($job->jobClass, $payload, $job->queue);

        // Laravel's Queue::push doesn't return a JobId directly. We
        // generate one as a tracking identifier the caller can use.
        return $job->trackingId()->value();
    }

    public function size(?string $queue = null): int
    {
        try {
            return $this->factory->connection()->size($queue);
        } catch (Throwable) {
            return -1;
        }
    }

    public function failedCount(): int
    {
        try {
            return $this->factory->connection()->getDatabase()->table(config('queue.failed.table', 'failed_jobs'))->count();
        } catch (Throwable) {
            return -1;
        }
    }

    public function listFailed(int $limit = 50): array
    {
        try {
            $rows = $this->factory->connection()
                ->getDatabase()
                ->table(config('queue.failed.table', 'failed_jobs'))
                ->orderBy('failed_at', 'desc')
                ->limit($limit)
                ->get(['uuid', 'queue', 'connection', 'failed_at', 'exception']);

            return $rows->map(fn ($row) => [
                'uuid'              => $row->uuid,
                'queue'             => $row->queue,
                'connection'        => $row->connection,
                'failed_at'         => $row->failed_at,
                'exception_preview' => substr((string) $row->exception, 0, 200),
            ])->all();
        } catch (Throwable) {
            return [];
        }
    }

    public function retryFailed(string $uuid): bool
    {
        try {
            // Laravel's artisan command `queue:retry` is the canonical
            // path. We shell out via the queue manager's retry command
            // helper to avoid re-implementing the payload-restoration
            // logic that queue:retry already has.
            \Illuminate\Support\Facades\Artisan::call('queue:retry', ['id' => $uuid]);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function ping(): bool
    {
        try {
            $size = $this->size();

            return $size >= 0;
        } catch (Throwable) {
            return false;
        }
    }

    public function driver(): string
    {
        return (string) config('queue.default', 'sync');
    }

    public function reserveIdempotencyKey(string $key, int $ttlSeconds): bool
    {
        // Doctrine: never throw from a queue health/path operation.
        // Failure is reported via false; the caller (AbstractQueuedJob::execute)
        // decides whether to abort or run-with-dedupe-off (fail-open).
        try {
            $connection = $this->factory->connection();

            // Sync driver: there's nothing to dedupe against — the job runs
            // immediately and finishes before this method returns. Return
            // false so AbstractQueuedJob::execute() skips the SETEX path
            // and runs handle() unconditionally.
            $driver = $this->driver();
            if ($driver === 'sync') {
                return false;
            }

            // Redis driver: SET key value NX EX ttl — atomic primitive.
            // Returns true if the key was set (we own this slot), false
            // if it already existed (duplicate).
            if ($driver === 'redis') {
                /** @var \Illuminate\Queue\Queue $connection */
                $client = $connection->client();
                // phpredis set(...) with NX + EX returns true on success,
                // false on duplicate. The exception type is \RedisException
                // (caught by the outer Throwable).
                $result = $client->set($key, '1', ['NX', 'EX' => $ttlSeconds]);
                return $result === true;
            }

            // Database driver: rely on the idempotency_keys table's UNIQUE
            // constraint (already in the V1 schema). INSERT ... ON CONFLICT
            // is the atomic primitive; a duplicate raises a unique-violation
            // that we catch and return false.
            if ($driver === 'database') {
                /** @var \Illuminate\Database\DatabaseManager $db */
                $db = $connection->getDatabase();
                try {
                    $rows = $db->table('idempotency_keys')
                        ->insertOrIgnore([
                            'key'         => $key,
                            'scope'       => 'queue',
                            'request_fingerprint' => substr(hash('sha256', $key), 0, 64),
                            'expires_at'  => now()->addSeconds($ttlSeconds),
                            'created_at'  => now(),
                        ]);
                    // insertOrIgnore returns the number of inserted rows.
                    // 1 = we own this slot; 0 = duplicate.
                    return $rows === 1;
                } catch (Throwable) {
                    return false;
                }
            }

            // Unknown driver — be conservative; refuse to reserve.
            return false;
        } catch (Throwable) {
            return false;
        }
    }
}
