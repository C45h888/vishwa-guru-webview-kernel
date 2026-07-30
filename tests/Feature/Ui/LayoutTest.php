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

final class LayoutTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_root_template_renders_app_shell_with_data_page_payload(): void
    {
        $this->app->instance(CampaignsQueryContract::class, $this->emptyQuery(CampaignsQueryContract::class));
        $this->app->instance(EventsQueryContract::class, $this->emptyQuery(EventsQueryContract::class));
        $this->app->instance(GalleryQueryContract::class, $this->emptyQuery(GalleryQueryContract::class));

        $this->app->instance(StaticPageRendererContract::class, Mockery::mock(StaticPageRendererContract::class, function ($m) {
            $m->shouldReceive('renderBySlug')
                ->with(Mockery::on(static fn (PageSlug $slug): bool => $slug->value() === 'home'))
                ->andReturn(new RenderedStaticPage(
                    page: StaticPage::draft(
                        slug: new PageSlug('home'),
                        title: 'Temple Home',
                        metaDescription: null,
                        body: new PageBody(version: 1, blocks: []),
                        seoMetadata: new SeoMetadata(),
                        isHomepage: true,
                        displayOrder: 0,
                        createdBy: 'test',
                    ),
                    heroBanners: [],
                    resolvedReferences: [],
                    html: '<p>Layout payload</p>',
                    resolvedAt: new DateTimeImmutable('2026-07-21T00:00:00+00:00'),
                    homepageContent: null,
                ));
        }));

        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('cms/Home')
            ->has('appName')
            ->has('appUrl')
            ->has('page')
            ->has('heroBanners')
            ->has('resolvedReferences')
            ->has('html')
            ->has('resolvedAt')
            ->has('homepageContent')
            ->etc()
        );

        $body = (string) $response->getContent();
        $this->assertStringContainsString('data-page=', $body);
        // The data-page JSON has the page-component path with the slash
        // escaped (cms\/Home) per JSON conventions, and Blade's {{ }} html-entity
        // escapes the inner quotes.
        $this->assertStringContainsString('component', $body);
        $this->assertStringContainsString('cms', $body);
        $this->assertStringContainsString('Home', $body);
        $this->assertStringContainsString('appName', $body);
        $this->assertStringContainsString('Temple Trust', $body);
    }

    private function emptyQuery(string $contract): Mockery\MockInterface
    {
        return Mockery::mock($contract, function ($m) {
            $m->shouldReceive('listFeatured')->andReturn([]);
            $m->shouldReceive('listUpcoming')->andReturn([]);
        });
    }
}
