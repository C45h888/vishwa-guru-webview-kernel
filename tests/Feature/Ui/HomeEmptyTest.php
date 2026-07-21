<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use App\Campaigns\Contracts\CampaignsQueryContract;
use App\Cms\Contracts\StaticPageRendererContract;
use App\Cms\Domain\Exceptions\StaticPageNotFoundException;
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
        $this->app->instance(CampaignsQueryContract::class, $this->emptyQuery(CampaignsQueryContract::class));
        $this->app->instance(EventsQueryContract::class, $this->emptyQuery(EventsQueryContract::class));
        $this->app->instance(GalleryQueryContract::class, $this->emptyQuery(GalleryQueryContract::class));

        $this->app->instance(StaticPageRendererContract::class, Mockery::mock(StaticPageRendererContract::class, function ($m) {
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

    private function emptyQuery(string $contract): Mockery\MockInterface
    {
        return Mockery::mock($contract, function ($m) {
            $m->shouldReceive('listFeatured')->andReturn([]);
            $m->shouldReceive('listUpcoming')->andReturn([]);
        });
    }
}
