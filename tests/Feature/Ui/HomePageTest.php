<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use App\Campaigns\Contracts\CampaignsQueryContract;
use App\Cms\Domain\DTOs\RenderedStaticPage;
use App\Cms\Domain\Entities\HeroBanner;
use App\Cms\Domain\Entities\StaticPage;
use App\Cms\Domain\ValueObjects\PageBody;
use App\Cms\Domain\ValueObjects\PageSlug;
use App\Cms\Domain\ValueObjects\SeoMetadata;
use App\Cms\Services\StaticPageRendererService;
use App\Events\Contracts\EventsQueryContract;
use App\Gallery\Contracts\GalleryQueryContract;
use App\Persistence\ValueObjects\EntityId;
use DateTimeImmutable;
use Inertia\Testing\AssertableInertia;
use Mockery;
use Tests\TestCase;

final class HomePageTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_home_renders_cms_home_component_with_shared_props(): void
    {
        $this->app->instance(CampaignsQueryContract::class, $this->emptyQuery());
        $this->app->instance(EventsQueryContract::class, $this->emptyQuery());
        $this->app->instance(GalleryQueryContract::class, $this->emptyQuery());

        $this->app->instance(StaticPageRendererService::class, Mockery::mock(StaticPageRendererService::class, function ($m) {
            $page = StaticPage::draft(
                slug: new PageSlug('home'),
                title: 'Temple Home',
                metaDescription: 'Welcome',
                body: new PageBody(version: 1, blocks: []),
                seoMetadata: new SeoMetadata(),
                isHomepage: true,
                displayOrder: 0,
                createdBy: 'test',
            );
            $banner = HeroBanner::create(
                title: 'Diwali 2026',
                subtitle: 'Join us',
                ctaLabel: 'Donate',
                ctaUrl: '/donate',
                imageFileId: null,
                mobileImageFileId: null,
                displayOrder: 0,
                startsAt: null,
                endsAt: null,
                createdBy: 'test',
            );
            $m->shouldReceive('renderHomepage')->andReturn(new RenderedStaticPage(
                page: $page,
                heroBanners: [$banner],
                resolvedReferences: [],
                html: '<p>Hello world</p>',
                resolvedAt: new DateTimeImmutable('2026-07-21T00:00:00+00:00'),
            ));
        }));

        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('cms/Home')
            ->has('appName')
            ->has('appUrl')
            ->where('authUser', null)
            ->where('featuredCampaigns', [])
            ->where('featuredEvents', [])
            ->where('featuredGalleries', [])
            ->has('page')
            ->has('heroBanners', 1)
            ->where('html', '<p>Hello world</p>')
            ->etc()
        );
    }

    private function emptyQuery(): Mockery\MockInterface
    {
        return Mockery::mock(CampaignsQueryContract::class, function ($m) {
            $m->shouldReceive('listFeatured')->andReturn([]);
            $m->shouldReceive('listUpcoming')->andReturn([]);
        });
    }
}
