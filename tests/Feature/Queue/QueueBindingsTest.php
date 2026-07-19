<?php

declare(strict_types=1);

namespace Tests\Feature\Queue;

use App\Queue\Contracts\QueueConnectorContract;
use App\Queue\Infrastructure\LaravelQueueConnector;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Tests\TestCase;

/**
 * Verifies QueueConnectorContract is wired correctly through the
 * container and resolves to the Laravel-backed implementation.
 *
 * Doctrine: business services depend on the contract, not on the
 * concrete impl. This test pins that boundary — if a future agent
 * accidentally couples service code to LaravelQueueConnector or
 * Queue:: facade, the doctrine-comment grep will catch it.
 */
final class QueueBindingsTest extends TestCase
{
    public function test_queue_connector_contract_resolves(): void
    {
        $connector = $this->app->make(QueueConnectorContract::class);

        self::assertInstanceOf(
            LaravelQueueConnector::class,
            $connector,
            'QueueConnectorContract must resolve to LaravelQueueConnector',
        );
    }

    public function test_connector_is_a_singleton(): void
    {
        $a = $this->app->make(QueueConnectorContract::class);
        $b = $this->app->make(QueueConnectorContract::class);

        self::assertSame($a, $b, 'QueueConnectorContract must be a singleton');
    }

    public function test_queue_factory_is_wired(): void
    {
        $factory = $this->app->make(QueueFactory::class);
        self::assertInstanceOf(\Illuminate\Queue\QueueManager::class, $factory);
    }

    public function test_driver_returns_a_string(): void
    {
        $connector = $this->app->make(QueueConnectorContract::class);
        self::assertIsString($connector->driver());
    }

    public function test_ping_returns_boolean_not_throwing(): void
    {
        $connector = $this->app->make(QueueConnectorContract::class);

        $result = null;
        $threw = false;
        try {
            $result = $connector->ping();
        } catch (\Throwable) {
            $threw = true;
        }

        self::assertFalse($threw, 'ping() must not throw — doctrine fail-open');
        self::assertIsBool($result);
    }

    public function test_size_returns_int_not_throwing(): void
    {
        $connector = $this->app->make(QueueConnectorContract::class);

        $result = null;
        $threw = false;
        try {
            $result = $connector->size(null);
        } catch (\Throwable) {
            $threw = true;
        }

        self::assertFalse($threw, 'size() must not throw');
        self::assertIsInt($result);
    }

    public function test_retry_policy_blocks_in_config(): void
    {
        // Doctrine: retry policy is explicit, env-driven, observable.
        // Pin the assumption so a future agent removing these triggers CI failure.
        $general   = config('queue.general');
        $financial = config('queue.financial');
        $prune     = config('queue.prune');

        self::assertIsArray($general);
        self::assertIsArray($financial);
        self::assertIsArray($prune);

        // Doctrine: financial path is fail-fast
        self::assertSame(1, $financial['tries']);
    }

    public function test_failed_jobs_table_name_is_configurable(): void
    {
        $table = config('queue.failed.table', 'failed_jobs');
        self::assertIsString($table);
    }
}
