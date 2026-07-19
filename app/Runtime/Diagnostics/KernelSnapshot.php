<?php

declare(strict_types=1);

namespace App\Runtime\Diagnostics;

use App\Persistence\Contracts\PersistenceAdapterContract;
use App\Shared\Contracts\ConfigurationContract;
use App\Shared\Contracts\EnvironmentContract;
use App\Shared\Enums\EnvironmentType;
use DateTimeImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Queue\QueueManager;

/**
 * Immutable snapshot of runtime facts — the application's view of itself
 * at a point in time.
 *
 * Used by:
 *   - BootProbe (assert() verifies framework is alive)
 *   - HealthController (renders runtime section of /health JSON)
 *   - RuntimeStatusCommand (prints table rows)
 *
 * Constructed via KernelSnapshotFactory::capture(); never instantiated
 * directly so all reads happen through the framework's container-bound
 * facades and contracts.
 */
final class KernelSnapshot
{
    public function __construct(
        public readonly string $phpVersion,
        public readonly string $laravelVersion,
        public readonly EnvironmentType $environment,
        public readonly string $dbDriver,
        public readonly string $cacheStore,
        public readonly string $queueDriver,
        public readonly bool $appKeyPresent,
        public readonly int $appKeyLength,
        public readonly DateTimeImmutable $capturedAt,
    ) {}

    /**
     * Named constructor — gather facts from the framework container.
     * Pure read; no side effects. Safe to call repeatedly.
     */
    public static function capture(
        ConfigurationContract $config,
        EnvironmentContract $env,
        PersistenceAdapterContract $persistence,
        CacheRepository $cache,
        QueueManager $queue,
        string $laravelVersion,
    ): self {
        $appKey = (string) $env->get('APP_KEY', '');
        $appKeyTrimmed = trim($appKey);

        return new self(
            phpVersion: PHP_VERSION,
            laravelVersion: $laravelVersion,
            environment: $env->type(),
            dbDriver: $persistence->driver(),
            cacheStore: $cache->getStore() instanceof \Illuminate\Cache\ArrayStore
                ? 'array'
                : (method_exists($cache, 'getDefaultDriver') ? $cache->getDefaultDriver() : 'unknown'),
            queueDriver: $queue->getDefaultDriver(),
            appKeyPresent: $appKeyTrimmed !== '',
            appKeyLength: strlen($appKeyTrimmed),
            capturedAt: new DateTimeImmutable(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'php'           => $this->phpVersion,
            'laravel'       => $this->laravelVersion,
            'env'           => $this->environment->value,
            'db_driver'     => $this->dbDriver,
            'cache_store'   => $this->cacheStore,
            'queue_driver'  => $this->queueDriver,
            'app_key'       => $this->appKeyPresent ? 'present' : 'MISSING',
            'captured_at'   => $this->capturedAt->format(DateTimeImmutable::ATOM),
        ];
    }
}