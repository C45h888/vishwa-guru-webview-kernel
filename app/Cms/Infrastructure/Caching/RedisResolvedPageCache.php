<?php

declare(strict_types=1);

namespace App\Cms\Infrastructure\Caching;

use App\Cms\Contracts\ResolvedPageCacheContract;
use App\Cms\Domain\DTOs\RenderedStaticPage;
use App\Cms\Domain\ValueObjects\PageSlug;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Redis-backed implementation of ResolvedPageCacheContract.
 *
 * Key shape: `cms.page.{slug}.resolved.v{N}` where N is the cache
 * schema version. Bumping N on any DTO/entity change isolates the new
 * serialization from old serialized blobs (the old keys naturally
 * TTL-expire, while writes land under the new key).
 *
 * Serialization: PHP serialize() of the full RenderedStaticPage DTO.
 * TTL: 3600s (1 hour) — safety net; invalidation events fire within
 * milliseconds of state changes.
 *
 * Doctrine: when Redis evicts a key (memory pressure), the next request
 * re-assembles. No application-level reaction needed.
 *
 * @see /Users/kamii/Vishwaguru-webview-kernel/vishwa-guru-webview-kernel/cms-architecture.md §5.3.2
 */
final class RedisResolvedPageCache implements ResolvedPageCacheContract
{
    private const KEY_PREFIX = 'cms.page.';
    private const KEY_SUFFIX = '.resolved';

    /**
     * Cache schema version. Bump when RenderedStaticPage gains or
     * loses a typed property, or when any contained entity/VO changes
     * its serialized shape. Old versions become unreachable and TTL
     * out naturally.
     */
    private const SCHEMA_VERSION = 2;

    public function __construct(
        private readonly CacheRepository $cache,
    ) {
    }

    public function get(PageSlug $slug): ?RenderedStaticPage
    {
        $key = $this->key($slug);
        $value = $this->cache->get($key);
        if ($value === null) {
            return null;
        }

        try {
            $unserialized = unserialize($value);
        } catch (\Throwable) {
            return null;
        }

        return $unserialized instanceof RenderedStaticPage ? $unserialized : null;
    }

    public function put(PageSlug $slug, RenderedStaticPage $page, int $ttlSeconds = 3600): void
    {
        $this->cache->put(
            $this->key($slug),
            serialize($page),
            $ttlSeconds,
        );
    }

    public function invalidate(PageSlug $slug): void
    {
        $this->cache->forget($this->key($slug));
    }

    public function invalidateAll(): void
    {
        // Use the underlying Redis connection to delete by prefix when
        // available; fallback to a no-op marker.
        try {
            $store = $this->cache->getStore();
            if (method_exists($store, 'connection')) {
                /** @var \Illuminate\Redis\Connections\Connection $conn */
                $conn = $store->connection();
                $prefix = config('cache.prefix') ? config('cache.prefix').':' : '';
                $iterator = null;
                do {
                    /** @var array<string, mixed>|null $batch */
                    $batch = $conn->scan($iterator, [
                        'match' => $prefix.self::KEY_PREFIX.'*'.self::KEY_SUFFIX.'.v'.self::SCHEMA_VERSION,
                        'count' => 100,
                    ]);
                    if ($batch !== null && $batch !== [] && $batch !== false) {
                        $conn->del($batch);
                    }
                } while ($iterator !== 0 && $iterator !== null);
            }
        } catch (\Throwable) {
            // Best-effort; if Redis is unreachable or scan is unsupported,
            // individual invalidations still work via invalidate(slug).
        }
    }

    private function key(PageSlug $slug): string
    {
        return self::KEY_PREFIX.$slug->value().self::KEY_SUFFIX.'.v'.self::SCHEMA_VERSION;
    }
}