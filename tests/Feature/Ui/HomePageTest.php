<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use App\Campaigns\Contracts\CampaignsQueryContract;
use App\Cms\Contracts\StaticPageRendererContract;
use App\Cms\Domain\DTOs\RenderedStaticPage;
use App\Cms\Domain\Entities\StaticPage;
use App\Cms\Domain\ValueObjects\HomepageContent;
use App\Cms\Domain\ValueObjects\HomepageDonateCta;
use App\Cms\Domain\ValueObjects\HomepageMissionQuote;
use App\Cms\Domain\ValueObjects\HomepageProgram;
use App\Cms\Domain\ValueObjects\HomepageStory;
use App\Cms\Domain\ValueObjects\HomepageTrustPanel;
use App\Cms\Domain\ValueObjects\PageBody;
use App\Cms\Domain\ValueObjects\PageSlug;
use App\Cms\Domain\ValueObjects\SeoMetadata;
use App\Events\Contracts\EventsQueryContract;
use App\Gallery\Contracts\GalleryQueryContract;
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
        $this->app->instance(CampaignsQueryContract::class, $this->emptyQuery(CampaignsQueryContract::class));
        $this->app->instance(EventsQueryContract::class, $this->emptyQuery(EventsQueryContract::class));
        $this->app->instance(GalleryQueryContract::class, $this->emptyQuery(GalleryQueryContract::class));

        $content = $this->validHomepageContent();

        $this->app->instance(StaticPageRendererContract::class, Mockery::mock(StaticPageRendererContract::class, function ($m) use ($content) {
            $m->shouldReceive('renderBySlug')
                ->with(Mockery::on(static fn (PageSlug $slug): bool => $slug->value() === 'home'))
                ->andReturn(new RenderedStaticPage(
                    page: StaticPage::draft(
                        slug: new PageSlug('home'),
                        title: 'Temple Home',
                        metaDescription: 'Welcome',
                        body: new PageBody(version: 1, blocks: []),
                        seoMetadata: new SeoMetadata(),
                        isHomepage: true,
                        displayOrder: 0,
                        createdBy: 'test',
                        homepageContent: $content,
                    ),
                    heroBanners: [],
                    resolvedReferences: [],
                    html: '<p>Hello world</p>',
                    resolvedAt: new DateTimeImmutable('2026-07-21T00:00:00+00:00'),
                    homepageContent: $content,
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
            ->has('heroBanners', 0)
            ->where('html', '<p>Hello world</p>')
            ->where('homepageContent.version', 1)
            ->where('homepageContent.story.title', 'A trust sustained by seva')
            ->has('homepageContent.programs', 3)
            ->where('homepageContent.programs.0.key', 'pooja')
            ->where('homepageContent.programs.1.key', 'annadanam')
            ->where('homepageContent.programs.2.key', 'temple_care')
            ->where('homepageContent.trust_panel.registration', 'Formally registered trust.')
            ->where('homepageContent.donate_cta.cta_url', '/donate')
            ->etc()
        );
    }

    public function test_home_falls_back_when_no_published_homepage_row(): void
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
            ->where('homepageContent', null)
            ->where('heroBanners', [])
            ->where('featuredCampaigns', [])
            ->where('featuredEvents', [])
            ->where('featuredGalleries', [])
            ->has('appName')
            ->has('appUrl')
            ->etc()
        );
    }

    private function validHomepageContent(): HomepageContent
    {
        return new HomepageContent(
            version: 1,
            story: new HomepageStory(
                eyebrow: 'Our Story',
                title: 'A trust sustained by seva',
                body: 'Temple Trust is a registered charitable trust.',
                ctaLabel: 'Read more about the trust',
                ctaUrl: '/about',
                imageFileId: null,
                altText: 'Temple and sacred grounds',
            ),
            missionQuote: new HomepageMissionQuote(
                eyebrow: 'Our Mission',
                quote: 'To preserve the sacred traditions of daily pooja.',
                attribution: null,
            ),
            programs: [
                new HomepageProgram(
                    key: HomepageProgram::KEY_POOJA,
                    eyebrow: 'Practice',
                    title: 'Daily Pooja',
                    body: 'The rhythm of pooja at sunrise, noon, and sunset.',
                    imageFileId: null,
                    altText: 'Daily pooja',
                ),
                new HomepageProgram(
                    key: HomepageProgram::KEY_ANNADANAM,
                    eyebrow: 'Service',
                    title: 'Annadanam',
                    body: 'Free meals served daily to all who visit.',
                    imageFileId: null,
                    altText: 'Annadanam',
                ),
                new HomepageProgram(
                    key: HomepageProgram::KEY_TEMPLE_CARE,
                    eyebrow: 'Stewardship',
                    title: 'Temple Care',
                    body: 'The temple structure and the surrounding grounds.',
                    imageFileId: null,
                    altText: 'Temple care',
                ),
            ],
            trustPanel: new HomepageTrustPanel(
                eyebrow: 'Trust & Accountability',
                title: 'Stewardship you can rely on',
                registration: 'Formally registered trust.',
                taxStatus: 'Eligible donations receive applicable tax documentation.',
                operatingPrinciples: ['Service before convenience'],
                vows: ['Preserve tradition'],
            ),
            donateCta: new HomepageDonateCta(
                eyebrow: 'Offer Your Seva',
                title: 'Help sustain the temple’s daily work',
                body: 'Every offering supports daily worship.',
                ctaLabel: 'Donate Now',
                ctaUrl: '/donate',
            ),
            existingMediaAssetIds: [],
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
