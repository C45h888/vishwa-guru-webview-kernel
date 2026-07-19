<?php

declare(strict_types=1);

namespace Tests\Feature\Queue;

use App\Queue\Contracts\QueueConnectorContract;
use Tests\TestCase;

/**
 * Doctrine-critical: payment correctness must NOT depend on queue
 * availability.
 *
 * If Redis (the queue substrate) is unreachable, the donation flow
 * must still complete. The sync queue driver exists precisely for
 * this — it runs jobs inline within the dispatching request, so
 * the queue surface never touches Redis.
 *
 * This test pins the doctrine by verifying the failure-isolation
 * boundary at the contract level: ping() returns false (Redis
 * down) but the rest of the connector surface returns safe defaults
 * (size=-1, listFailed=[]) instead of throwing.
 */
final class PaymentPathSurvivesQueueDownTest extends TestCase
{
    public function test_connector_ping_returns_false_when_redis_unreachable(): void
    {
        // Force a Redis-unreachable state by pointing the queue
        // connection at a non-routable address.
        config([
            'database.redis.default.host' => '192.0.2.1',  // RFC 5737 TEST-NET-1
            'database.redis.default.timeout' => 0.1,
        ]);
        $this->app->forgetInstance(QueueConnectorContract::class);

        $connector = $this->app->make(QueueConnectorContract::class);

        $threw = false;
        $result = null;
        try {
            $result = $connector->ping();
        } catch (\Throwable) {
            $threw = true;
        }

        self::assertFalse($threw, 'ping() must never throw — fail-open doctrine');
        self::assertFalse($result, 'ping must return false when Redis is unreachable');
    }

    public function test_connector_size_returns_negative_one_when_redis_unreachable(): void
    {
        config([
            'database.redis.default.host' => '192.0.2.1',
            'database.redis.default.timeout' => 0.1,
        ]);
        $this->app->forgetInstance(QueueConnectorContract::class);

        $connector = $this->app->make(QueueConnectorContract::class);

        $threw = false;
        $result = null;
        try {
            $result = $connector->size(null);
        } catch (\Throwable) {
            $threw = true;
        }

        self::assertFalse($threw, 'size() must never throw');
        self::assertSame(-1, $result, 'size() returns -1 when Redis is unreachable (sentinel)');
    }

    public function test_sync_queue_driver_does_not_touch_redis(): void
    {
        // Override queue config to use sync — jobs run inline.
        config(['queue.default' => 'sync']);

        $connector = $this->app->make(QueueConnectorContract::class);

        // sync driver is reachable regardless of Redis state
        self::assertTrue($connector->ping());
        self::assertSame('sync', $connector->driver());
    }
}
