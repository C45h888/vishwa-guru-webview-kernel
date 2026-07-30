<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use App\Cms\Contracts\StaticPageRendererContract;
use App\Cms\Domain\DTOs\RenderedStaticPage;
use App\Cms\Domain\Entities\StaticPage;
use App\Cms\Domain\ValueObjects\AboutDonateCta;
use App\Cms\Domain\ValueObjects\AboutPageContent;
use App\Cms\Domain\ValueObjects\AboutTimelineEntry;
use App\Cms\Domain\ValueObjects\AboutTrustee;
use App\Cms\Domain\ValueObjects\AboutValue;
use App\Cms\Domain\ValueObjects\PageBody;
use App\Cms\Domain\ValueObjects\PageSlug;
use App\Cms\Domain\ValueObjects\SeoMetadata;
use DateTimeImmutable;
use Inertia\Testing\AssertableInertia;
use Mockery;
use Tests\TestCase;

/**
 * Happy-path coverage for the /about route.
 *
 * Mirrors HomePageTest in structure. The renderer is mocked so the
 * test only exercises the controller's Inertia payload shaping — the
 * renderer's own behavior is covered by the kernel's tests.
 */
final class AboutPageTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_about_renders_cms_about_component_with_structured_sections(): void
    {
        $content = $this->validAboutContent();

        $this->app->instance(StaticPageRendererContract::class, Mockery::mock(StaticPageRendererContract::class, function ($m) use ($content) {
            $m->shouldReceive('renderBySlug')
                ->with(Mockery::on(static fn (PageSlug $slug): bool => $slug->value() === 'about'))
                ->andReturn(new RenderedStaticPage(
                    page: StaticPage::draft(
                        slug: new PageSlug('about'),
                        title: 'About the Trust',
                        metaDescription: 'A registered charitable trust preserving the sacred rhythms of South Indian temple life.',
                        body: new PageBody(version: 1, blocks: []),
                        seoMetadata: new SeoMetadata(),
                        isHomepage: false,
                        displayOrder: 10,
                        createdBy: 'test',
                        aboutPageContent: $content,
                    ),
                    heroBanners: [],
                    resolvedReferences: [],
                    html: '',
                    resolvedAt: new DateTimeImmutable('2026-07-26T00:00:00+00:00'),
                    aboutPageContent: $content,
                ));
        }));

        $response = $this->get('/about');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('cms/About')
            ->has('appName')
            ->has('appUrl')
            ->where('authUser', null)
            ->where('page.title', 'About the Trust')
            ->where('page.slug', 'about')
            ->has('aboutContent')
            ->where('aboutContent.version', 1)
            ->where('aboutContent.values.title', 'Seva, Satya, and Smriti')
            ->where('aboutContent.values.eyebrow', 'Our Values')
            ->has('aboutContent.timeline', 5)
            ->where('aboutContent.timeline.0.year', 1998)
            ->where('aboutContent.timeline.0.title', 'Temple founded')
            ->where('aboutContent.timeline.4.year', 2024)
            ->has('aboutContent.trustees', 3)
            ->where('aboutContent.trustees.0.name', 'Dr. Anjali Rao')
            ->where('aboutContent.trustees.0.role', 'Chair, Board of Trustees')
            ->where('aboutContent.donate_cta.cta_url', '/donate')
            ->where('aboutContent.donate_cta.cta_label', 'Donate Now')
            ->has('heroBanners', 0)
            ->where('html', '')
            ->has('resolvedAt')
            ->etc()
        );
    }

    private function validAboutContent(): AboutPageContent
    {
        return new AboutPageContent(
            version: 1,
            values: new AboutValue(
                eyebrow: 'Our Values',
                title: 'Seva, Satya, and Smriti',
                body: 'The trust is sustained by three commitments: seva, satya, and smriti.',
                imageFileId: null,
                altText: null,
            ),
            timeline: [
                new AboutTimelineEntry(1998, 'Temple founded', 'A small group of devotees established the temple.'),
                new AboutTimelineEntry(2005, 'Annadanam hall opens', 'A dedicated hall for the community meal.'),
                new AboutTimelineEntry(2012, 'Priest training program', 'In-house agamic training.'),
                new AboutTimelineEntry(2019, 'Temple structure restoration', 'Restoration of the vimana and outer prakara.'),
                new AboutTimelineEntry(2024, 'Online donations and receipts', 'Public digital platform launch.'),
            ],
            trustees: [
                new AboutTrustee('Dr. Anjali Rao', 'Chair, Board of Trustees', null, 'A Sanskrit scholar and practising devotee.'),
                new AboutTrustee('Sundaram Iyer', 'Treasurer', null, 'A retired banker.'),
                new AboutTrustee('Lakshmi Narayanan', 'Trustee, Annadanam', null, 'Leads the daily Annadanam programme.'),
            ],
            donateCta: new AboutDonateCta(
                eyebrow: 'Offer Your Seva',
                title: "Help sustain the temple's daily work",
                body: 'Every offering supports daily pooja, Annadanam, and the care of this sacred place.',
                ctaLabel: 'Donate Now',
                ctaUrl: '/donate',
            ),
            existingMediaAssetIds: [],
        );
    }
}
