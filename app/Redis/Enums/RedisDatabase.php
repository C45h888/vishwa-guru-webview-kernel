<?php

declare(strict_types=1);

namespace App\Redis\Enums;

/**
 * Redis logical database assignment.
 *
 * The Temple Trust runtime separates Redis concerns across logical DBs
 * on a single Redis 7+ instance. Doctrine: domain boundaries must remain
 * explicit. The DB number is a domain boundary that survives key
 * prefixes — even with a global prefix, MONITOR output is cleaner when
 * each subsystem lives on its own DB number.
 *
 * Mapping (mirrors config/database.php):
 *   - Default  (DB 0) — application keys: locks, idempotency fast-path,
 *                       webhook dedupe, anything service code reaches for
 *                       directly via Redis::connection('default')
 *   - Cache    (DB 1) — Laravel Cache::* facade. Backing store for the
 *                       cache repository. TTL-heavy, evictable.
 *   - Queue    (DB 2) — Laravel Queue::* jobs. Durable (AOF on) for
 *                       at-least-once delivery. NOT wired in Phase 2 —
 *                       reserved for Phase 1 closure.
 *   - Session  (DB 3) — Laravel Session::* storage. NOT wired in Phase 2 —
 *                       reserved for Phase 4 admin auth.
 *
 * Adding a new logical DB:
 *   1. Add a case here.
 *   2. Add the corresponding connection block to config/database.php.
 *   3. Add the env var for the DB number to .env.example.
 *   4. If a Laravel facade needs to use it, update the corresponding
 *      config (cache.php, queue.php, session.php) to point at the new
 *      connection name.
 */
enum RedisDatabase: int
{
    case Default = 0;
    case Cache = 1;
    case Queue = 2;
    case Session = 3;

    /**
     * The config key in config/database.php under 'redis' that holds
     * the connection parameters for this database.
     */
    public function connectionName(): string
    {
        return match ($this) {
            self::Default => 'default',
            self::Cache   => 'cache',
            self::Queue   => 'queue',
            self::Session => 'session',
        };
    }

    /**
     * Human-readable label for logs and CLI output.
     */
    public function label(): string
    {
        return match ($this) {
            self::Default => 'app',
            self::Cache   => 'cache',
            self::Queue   => 'queue',
            self::Session => 'session',
        };
    }
}
