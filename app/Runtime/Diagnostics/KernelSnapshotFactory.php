<?php

declare(strict_types=1);

namespace App\Runtime\Diagnostics;

use App\Persistence\Contracts\PersistenceAdapterContract;
use App\Shared\Contracts\ConfigurationContract;
use App\Shared\Contracts\EnvironmentContract;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Foundation\Application;
use Illuminate\Queue\QueueManager;

/**
 * Wraps KernelSnapshot::capture() with explicit constructor injection
 * of framework contracts (NOT facades — the doctrine-level rule from
 * architecture.md: kernel code depends on contracts, never on facades).
 *
 * Why a factory rather than calling KernelSnapshot::capture() directly:
 *   - BootProbe depends on this factory, not on the contracts — keeps
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
        private readonly PersistenceAdapterContract $persistence,
        private readonly CacheRepository $cache,
        private readonly QueueManager $queue,
        private readonly Application $app,
    ) {}

    public function capture(): KernelSnapshot
    {
        return KernelSnapshot::capture(
            $this->config,
            $this->env,
            $this->persistence,
            $this->cache,
            $this->queue,
            $this->app->version(),
        );
    }
}