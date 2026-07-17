<?php

declare(strict_types=1);

namespace App\Redis\Contracts;

/**
 * RedisConnectorContract — single entry point for Redis access.
 *
 * Phase 2: contract only.
 * Phase 2 close: LaravelRedisConnector implementation.
 *
 * Doctrine alignment:
 *   - Repositories encapsulate persistence. The Redis equivalent is the
 *     connector — service code does NOT reach for Illuminate\Support\Facades\Redis
 *     directly. It goes through this contract so that:
 *     (a) the connection topology (which DB, which timeout) is decided in
 *         one place, not scattered through the codebase;
 *     (b) tests can substitute an in-memory fake without a Redis server;
 *     (c) the prefix strategy (key naming) lives behind one facade.
 *
 * Service code that needs Redis should depend on RedisConnectorContract,
 * not on the phpredis Redis class or Laravel's Redis facade. This mirrors
 * the PersistenceAdapterContract pattern at the SQL layer.
 */
interface RedisConnectorContract
{
    /**
     * Acquire a connection to the requested logical database.
     *
     * The returned connection is shared — repeated calls within a request
     * lifecycle return the same instance. Caller MUST NOT close it; the
     * container owns lifecycle.
     *
     * Implementations must apply the global REDIS_PREFIX and the
     * per-DB timeout settings from config/database.php.
     *
     * @return object The underlying client (phpredis Redis instance, or
     *                a test double). Returned as object so the contract
     *                stays client-agnostic.
     */
    public function connection(string $name = 'default'): object;

    /**
     * Verify the connection is reachable.
     *
     * PING the server, return true on PONG, false on any failure
     * (timeout, auth failure, connection refused). MUST NOT throw —
     * callers (HealthProbe, /health endpoint, fallback paths) treat
     * false as "Redis is down, fall through to alternative store".
     */
    public function ping(string $name = 'default'): bool;

    /**
     * Which logical databases are configured for use. Returned as a
     * map of connection name → DB number. Empty if Redis is not
     * configured (e.g. REDIS_HOST is unset).
     *
     * @return array<string, int>
     */
    public function configuredDatabases(): array;
}
