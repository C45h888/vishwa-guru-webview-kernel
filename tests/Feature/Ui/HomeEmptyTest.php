<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use App\Campaigns\Contracts\CampaignsQueryContract;
use App\Cms\Domain\Exceptions\StaticPageNotFoundException;
use App\Cms\Services\StaticPageRendererService;
use App\Events\Contracts\EventsQueryContract;
use App\Gallery\Contracts\GalleryQueryContract;
use Inertia\Testing\AssertableInertia;
use Mockery;
use Tests\TestCase;

final class HomeEmptyTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_missing_homepage_falls_back_to_home_empty(): void
    {
        $this->app->instance(CampaignsQueryContract::class, $this->emptyQuery());
        $this->app->instance(EventsQueryContract::class, $this->emptyQuery());
        $this->app->instance(GalleryQueryContract::class, $this->emptyQuery());

        $this->app->instance(StaticPageRendererService::class, Mockery::mock(StaticPageRendererService::class, function ($m) {
            $m->shouldReceive('renderHomepage')
                ->andThrow(StaticPageNotFoundException::bySlug('homepage'));
        }));

        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('cms/HomeEmpty')
            ->where('featuredCampaigns', [])
            ->where('featuredEvents', [])
            ->where('featuredGalleries', [])
            ->missing('page')
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
