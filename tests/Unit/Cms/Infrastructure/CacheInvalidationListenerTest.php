<?php

declare(strict_types=1);

namespace Tests\Unit\Cms\Infrastructure;

use App\Cms\Contracts\ResolvedPageCacheContract;
use App\Cms\Domain\Repositories\HeroBannerRepositoryContract;
use App\Cms\Domain\Repositories\StaticPageReferenceRepositoryContract;
use App\Cms\Domain\Repositories\StaticPageRepositoryContract;
use App\Cms\Domain\ValueObjects\PageSlug;
use App\Cms\Infrastructure\Caching\CacheInvalidationListener;
use App\Cms\Infrastructure\Events\CmsDomainEvents;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * CacheInvalidationListener — pure-unit tests with mocked cache +
 * repository contracts. The contracts are bound in CmsServiceProvider
 * but never resolved here; we exercise the listener in isolation to
 * verify its event → cache action mapping (per cms-architecture.md §5.3.3).
 *
 * Doctrine note: cache invalidation must NEVER fail a business operation.
 * The listener swallows exceptions; stale cache TTLs out within 1 hour.
 */
final class CacheInvalidationListenerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private ResolvedPageCacheContract|MockInterface $cache;
    private StaticPageRepositoryContract|MockInterface $pages;
    private HeroBannerRepositoryContract|MockInterface $banners;
    private StaticPageReferenceRepositoryContract|MockInterface $references;
    private LoggerInterface|MockInterface $logger;

    protected function setUp(): void
    {
        $this->cache = Mockery::mock(ResolvedPageCacheContract::class);
        $this->pages = Mockery::mock(StaticPageRepositoryContract::class);
        $this->banners = Mockery::mock(HeroBannerRepositoryContract::class);
        $this->references = Mockery::mock(StaticPageReferenceRepositoryContract::class);
        $this->logger = Mockery::mock(LoggerInterface::class);

        // References repo isn't used by these specific tests but the
        // constructor requires it; allow no calls so missing-expectation
        // is not flagged.
        $this->references->shouldReceive('listByReference')->zeroOrMoreTimes();
    }

    public function testStaticPagePublishedInvalidatesSlug(): void
    {
        $this->cache->shouldReceive('invalidate')
            ->once()
            ->with(Mockery::on(static fn (PageSlug $slug) => $slug->value() === 'home'));

        $listener = new CacheInvalidationListener(
            $this->cache,
            $this->pages,
            $this->banners,
            $this->references,
            $this->logger,
        );
        $listener->handle(CmsDomainEvents::STATIC_PAGE_PUBLISHED, 'home');
    }

    public function testHomepageChangedInvalidatesBothSlugs(): void
    {
        // The listener's invalidateHomepageChange takes payload[0]=oldSlug,
        // payload[1]=newSlug. We assert both invalidate() calls landed,
        // any order, with the right PageSlug values.
        $invalidated = [];
        $this->cache->shouldReceive('invalidate')
            ->twice()
            ->with(Mockery::on(static function (PageSlug $slug) use (&$invalidated) {
                $invalidated[] = $slug->value();

                return true;
            }));

        $listener = new CacheInvalidationListener(
            $this->cache, $this->pages, $this->banners, $this->references, $this->logger,
        );
        $listener->handle(CmsDomainEvents::HOMEPAGE_CHANGED, 'about', 'home');

        $this->assertEqualsCanonicalizing(['about', 'home'], $invalidated);
    }

    public function testReferenceDetachedInvalidatesAll(): void
    {
        $this->cache->shouldReceive('invalidateAll')
            ->once();

        $listener = new CacheInvalidationListener(
            $this->cache, $this->pages, $this->banners, $this->references, $this->logger,
        );
        // Payload layout for REFERENCE_DETACHED:
        // listen dispatch sends [pageId] (or empty array).
        $listener->handle(CmsDomainEvents::REFERENCE_DETACHED, 'page_01HZ...');
    }

    public function testSwallowsCacheExceptionToKeepDispatcherAlive(): void
    {
        $this->cache->shouldReceive('invalidate')
            ->once()
            ->andThrow(new \RuntimeException('Redis unreachable'));

        // Logger must NOT be called — the listener's logger is optional
        // and is currently null. We pass null here to assert that
        // branch. If the impl ever wires the logger, this test will
        // need to be updated to expect an error log call.
        $listener = new CacheInvalidationListener(
            $this->cache, $this->pages, $this->banners, $this->references, null,
        );

        // No exception bubbles to caller.
        $listener->handle(CmsDomainEvents::STATIC_PAGE_PUBLISHED, 'home');
        $this->addToAssertionCount(1);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
