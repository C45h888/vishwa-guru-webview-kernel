<?php

declare(strict_types=1);

namespace App\Runtime\Diagnostics;

use App\Shared\Contracts\ConfigurationContract;
use App\Shared\Contracts\EnvironmentContract;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Application;
use Illuminate\Queue\QueueManager;

/**
 * Wraps KernelSnapshot::capture() with explicit constructor injection
 * of framework facades.
 *
 * Why a factory rather than calling KernelSnapshot::capture() directly:
 *   - BootProbe depends on this factory, not on the facades — keeps
 *     BootProbe constructor simple and unit-testable.
 *   - Container resolves the dependencies once at construction; capture()
 *     itself stays a pure read.
 *
 * Wired as a singleton in RuntimeServiceProvider.
 */
final class KernelSnapshotFactory
{
    public function __construct(
        private readonly ConfigurationContract $config,
        private readonly EnvironmentContract $env,
        private readonly ConnectionInterface $connection,
        private readonly CacheRepository $cache,
        private readonly QueueManager $queue,
        private readonly Application $app,
    ) {}

    public function capture(): KernelSnapshot
    {
        return KernelSnapshot::capture(
            $this->config,
            $this->env,
            $this->connection,
            $this->cache,
            $this->queue,
            $this->app->version(),
        );
    }
}