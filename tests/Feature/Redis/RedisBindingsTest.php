<?php

declare(strict_types=1);

namespace Tests\Feature\Redis;

use App\Redis\Contracts\RedisConnectorContract;
use App\Redis\Enums\RedisDatabase;
use App\Redis\Infrastructure\LaravelRedisConnector;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\RedisManager;
use Tests\TestCase;

/**
 * Verifies that RedisConnectorContract is wired correctly through the
 * container and resolves to the LaravelRedisConnector implementation
 * with all four configured connections (default / cache / queue / session).
 *
 * Doctrine: business services depend on the contract, not on the
 * concrete impl. This test pins that boundary — if a future agent
 * accidentally couples service code to the concrete class, the
 * compile/test error catches it.
 */
final class RedisBindingsTest extends TestCase
{
    public function test_redis_connector_contract_resolves(): void
    {
        $connector = $this->app->make(RedisConnectorContract::class);

        self::assertInstanceOf(
            LaravelRedisConnector::class,
            $connector,
            'RedisConnectorContract must resolve to LaravelRedisConnector',
        );
    }

    public function test_connector_is_a_singleton(): void
    {
        $a = $this->app->make(RedisConnectorContract::class);
        $b = $this->app->make(RedisConnectorContract::class);

        self::assertSame(
            $a,
            $b,
            'RedisConnectorContract must be a singleton — connections are shared',
        );
    }

    public function test_configured_databases_lists_all_four_connections(): void
    {
        $connector = $this->app->make(RedisConnectorContract::class);
        $databases = $connector->configuredDatabases();

        self::assertArrayHasKey('default', $databases, 'default connection must be configured');
        self::assertArrayHasKey('cache',   $databases, 'cache connection must be configured');
        self::assertArrayHasKey('queue',   $databases, 'queue connection must be configured');
        self::assertArrayHasKey('session', $databases, 'session connection must be configured (Phase 4 wiring deferred)');

        self::assertSame(0, $databases['default']);
        self::assertSame(1, $databases['cache']);
        self::assertSame(2, $databases['queue']);
        self::assertSame(3, $databases['session']);
    }

    public function test_redis_database_enum_maps_to_connection_names(): void
    {
        self::assertSame('default', RedisDatabase::Default->connectionName());
        self::assertSame('cache',   RedisDatabase::Cache->connectionName());
        self::assertSame('queue',   RedisDatabase::Queue->connectionName());
        self::assertSame('session', RedisDatabase::Session->connectionName());

        self::assertSame(0, RedisDatabase::Default->value);
        self::assertSame(1, RedisDatabase::Cache->value);
        self::assertSame(2, RedisDatabase::Queue->value);
        self::assertSame(3, RedisDatabase::Session->value);
    }

    public function test_ping_returns_boolean_not_throwing(): void
    {
        $connector = $this->app->make(RedisConnectorContract::class);

        // ping() must NOT throw — it returns false on failure. This is
        // doctrine-critical: HealthProbe / fallback paths treat a false
        // return as "Redis is down, fall through". A thrown exception
        // would propagate and break those callers.
        $result = $connector->ping('default');

        self::assertIsBool($result, 'ping() must return bool, never throw');
    }

    public function test_redis_manager_is_wired(): void
    {
        // The connector depends on RedisFactory; if Laravel didn't wire
        // its Redis provider, this resolution would fail. Pin the
        // assumption.
        $factory = $this->app->make(RedisFactory::class);

        self::assertInstanceOf(RedisManager::class, $factory);
    }

    public function test_timeouts_are_in_config(): void
    {
        // Doctrine: bounded timeouts on every connection. Pin the
        // assumption so a future agent removing them triggers CI failure.
        $default = config('database.redis.default');
        $cache   = config('database.redis.cache');
        $queue   = config('database.redis.queue');

        self::assertArrayHasKey('timeout', $default, 'default connection must have timeout');
        self::assertArrayHasKey('read_timeout', $default, 'default connection must have read_timeout');

        self::assertArrayHasKey('timeout', $cache, 'cache connection must have timeout');
        self::assertArrayHasKey('timeout', $queue, 'queue connection must have timeout');

        // Doctrine: hot-path timeout ≤ 1.5s (matches typical LB upstream)
        self::assertLessThanOrEqual(1.5, (float) $default['timeout']);
        // Doctrine: hot-path read timeout ≤ 0.5s
        self::assertLessThanOrEqual(0.5, (float) $default['read_timeout']);
    }
}
