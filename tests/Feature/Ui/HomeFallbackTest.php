<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use App\Campaigns\Contracts\CampaignsQueryContract;
use App\Cms\Contracts\StaticPageRendererContract;
use App\Cms\Domain\DTOs\RenderedStaticPage;
use App\Cms\Domain\Entities\StaticPage;
use App\Cms\Domain\ValueObjects\PageBody;
use App\Cms\Domain\ValueObjects\PageSlug;
use App\Cms\Domain\ValueObjects\SeoMetadata;
use App\Events\Contracts\EventsQueryContract;
use App\Gallery\Contracts\GalleryQueryContract;
use DateTimeImmutable;
use Inertia\Testing\AssertableInertia;
use Mockery;
use Tests\TestCase;

/**
 * Two-state fallback coverage for the canonical home page.
 *
 * Case 1: no published home row → controller returns cms/Home with a
 *         server-side fallback page object and homepageContent=null.
 * Case 2: published home row with homepage_content=NULL → controller
 *         returns cms/Home with the real page object and homepageContent
 *         still null. The Svelte layer fills in the FALLBACK_HOMEPAGE_CONTENT
 *         constant locally; this test only verifies the controller's
 *         null-discipline.
 */
final class HomeFallbackTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_no_published_home_row_renders_fallback_page(): void
    {
        $this->app->instance(CampaignsQueryContract::class, $this->emptyQuery(CampaignsQueryContract::class));
        $this->app->instance(EventsQueryContract::class, $this->emptyQuery(EventsQueryContract::class));
        $this->app->instance(GalleryQueryContract::class, $this->emptyQuery(GalleryQueryContract::class));

        $this->app->instance(StaticPageRendererContract::class, Mockery::mock(StaticPageRendererContract::class, function ($m) {
            $m->shouldReceive('renderBySlug')->andReturn(null);
        }));

        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('cms/Home')
            ->where('page.state', 'fallback')
            ->where('page.is_homepage', true)
            ->where('homepageContent', null)
            ->where('heroBanners', [])
            ->where('featuredCampaigns', [])
            ->where('featuredEvents', [])
            ->where('featuredGalleries', [])
            ->has('appName')
            ->has('appUrl')
            ->has('html')
            ->has('resolvedAt')
            ->etc()
        );
    }

    public function test_published_row_with_null_homepage_content_renders_null_payload(): void
    {
        $this->app->instance(CampaignsQueryContract::class, $this->emptyQuery(CampaignsQueryContract::class));
        $this->app->instance(EventsQueryContract::class, $this->emptyQuery(EventsQueryContract::class));
        $this->app->instance(GalleryQueryContract::class, $this->emptyQuery(GalleryQueryContract::class));

        $page = StaticPage::draft(
            slug: new PageSlug('home'),
            title: 'Temple Home',
            metaDescription: 'Welcome to the temple trust.',
            body: new PageBody(version: 1, blocks: []),
            seoMetadata: new SeoMetadata(),
            isHomepage: true,
            displayOrder: 0,
            createdBy: 'test',
        );

        $this->app->instance(StaticPageRendererContract::class, Mockery::mock(StaticPageRendererContract::class, function ($m) use ($page) {
            $m->shouldReceive('renderBySlug')
                ->with(Mockery::on(static fn (PageSlug $slug): bool => $slug->value() === 'home'))
                ->andReturn(new RenderedStaticPage(
                    page: $page,
                    heroBanners: [],
                    resolvedReferences: [],
                    html: '',
                    resolvedAt: new DateTimeImmutable('2026-07-21T00:00:00+00:00'),
                    homepageContent: null,
                ));
        }));

        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('cms/Home')
            ->where('page.title', 'Temple Home')
            ->where('homepageContent', null)
            ->has('appName')
            ->has('appUrl')
            ->etc()
        );
    }

    private function emptyQuery(string $contract): Mockery\MockInterface
    {
        return Mockery::mock($contract, function ($m) {
            $m->shouldReceive('listFeatured')->andReturn([]);
            $m->shouldReceive('listUpcoming')->andReturn([]);
        });
    }
}
