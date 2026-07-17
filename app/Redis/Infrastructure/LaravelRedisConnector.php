<?php

declare(strict_types=1);

namespace App\Redis\Infrastructure;

use App\Redis\Contracts\RedisConnectorContract;
use Illuminate\Contracts\Redis\Connection;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Throwable;

/**
 * LaravelRedisConnector — the canonical RedisConnectorContract impl.
 *
 * Wraps Laravel's RedisManager (Illuminate\Contracts\Redis\Factory) so
 * service code depends on a domain contract, not on Laravel's Redis
 * facade. The wrapper:
 *   - resolves connections by configured name (default / cache / queue / session)
 *   - swallows connection errors in ping() — failures are reported, not thrown
 *   - introspects the configured connections without opening them
 *
 * Doctrine: business logic depends on contracts, not on framework facades.
 * This is the same pattern as LaravelDbAdapter wrapping ConnectionInterface
 * for the SQL layer.
 */
final class LaravelRedisConnector implements RedisConnectorContract
{
    public function __construct(
        private readonly RedisFactory $factory,
        /** @var array<string, array<string, mixed>> */
        private readonly array $configuredConnections,
    ) {}

    public function connection(string $name = 'default'): object
    {
        /** @var Connection $conn */
        $conn = $this->factory->connection($name);

        // Return the underlying client so service code can call phpredis
        // methods directly (hSet, setEx, etc.). Laravel's Connection wraps
        // the client but also implements __call which forwards to it —
        // returning the client directly avoids the magic-forwarding.
        return $conn->client();
    }

    public function ping(string $name = 'default'): bool
    {
        try {
            /** @var Connection $conn */
            $conn = $this->factory->connection($name);

            // phpredis Redis::ping() returns true on PONG, '+PONG' on
            // some configs. We accept any truthy non-error response.
            $reply = $conn->command('PING');

            return $reply === true || $reply === 'PONG' || $reply === '+PONG' || $reply === 1;
        } catch (Throwable) {
            return false;
        }
    }

    public function configuredDatabases(): array
    {
        $result = [];
        foreach ($this->configuredConnections as $name => $config) {
            if (! isset($config['database'])) {
                continue;
            }
            $result[$name] = (int) $config['database'];
        }

        return $result;
    }
}
