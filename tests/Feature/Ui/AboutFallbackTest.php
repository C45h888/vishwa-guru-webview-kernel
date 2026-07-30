<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use App\Cms\Contracts\StaticPageRendererContract;
use App\Cms\Domain\DTOs\RenderedStaticPage;
use App\Cms\Domain\Entities\StaticPage;
use App\Cms\Domain\ValueObjects\PageBody;
use App\Cms\Domain\ValueObjects\PageSlug;
use App\Cms\Domain\ValueObjects\SeoMetadata;
use DateTimeImmutable;
use Inertia\Testing\AssertableInertia;
use Mockery;
use Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Two-state fallback coverage for the canonical about page.
 *
 * Case 1: no published about row → controller throws 404 (no fallback
 *         page object — the about page does not have a synthetic
 *         placeholder like the home page's `state: 'fallback'`).
 * Case 2: published about row with about_page_content=NULL → controller
 *         returns cms/About with the real page object and aboutContent
 *         still null. The Svelte layer fills in FALLBACK_ABOUT_PAGE_CONTENT
 *         locally; this test only verifies the controller's null-discipline.
 */
final class AboutFallbackTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_no_published_about_row_throws_404(): void
    {
        $this->app->instance(StaticPageRendererContract::class, Mockery::mock(StaticPageRendererContract::class, function ($m) {
            $m->shouldReceive('renderBySlug')->andReturn(null);
        }));

        $this->withoutExceptionHandling();

        $this->expectException(NotFoundHttpException::class);
        $this->get('/about');
    }

    public function test_published_row_with_null_about_content_renders_null_payload(): void
    {
        $page = StaticPage::draft(
            slug: new PageSlug('about'),
            title: 'About the Trust',
            metaDescription: 'A registered charitable trust.',
            body: new PageBody(version: 1, blocks: []),
            seoMetadata: new SeoMetadata(),
            isHomepage: false,
            displayOrder: 10,
            createdBy: 'test',
        );

        $this->app->instance(StaticPageRendererContract::class, Mockery::mock(StaticPageRendererContract::class, function ($m) use ($page) {
            $m->shouldReceive('renderBySlug')
                ->with(Mockery::on(static fn (PageSlug $slug): bool => $slug->value() === 'about'))
                ->andReturn(new RenderedStaticPage(
                    page: $page,
                    heroBanners: [],
                    resolvedReferences: [],
                    html: '',
                    resolvedAt: new DateTimeImmutable('2026-07-26T00:00:00+00:00'),
                    aboutPageContent: null,
                ));
        }));

        $response = $this->get('/about');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('cms/About')
            ->where('page.title', 'About the Trust')
            ->where('aboutContent', null)
            ->has('appName')
            ->has('appUrl')
            ->etc()
        );
    }
}
