<?php

declare(strict_types=1);

namespace Tests\Feature\Seo;

use App\Campaigns\Contracts\CampaignsQueryContract;
use App\Campaigns\Domain\DTOs\CampaignPagedResultDTO;
use App\Campaigns\Domain\DTOs\CampaignSummaryDTO;
use App\Events\Contracts\EventsQueryContract;
use App\Events\Domain\DTOs\EventPagedResultDTO;
use App\Gallery\Contracts\GalleryQueryContract;
use App\Gallery\Domain\DTOs\GalleryPagedResultDTO;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

/**
 * /sitemap.xml with mocked query contracts — proves routing, content
 * type, and slug inclusion without touching the database.
 */
final class SitemapTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_sitemap_lists_displayable_slugs_and_excludes_private_paths(): void
    {
        Cache::flush();

        $campaignMock = Mockery::mock(CampaignsQueryContract::class);
        $campaignMock->shouldReceive('listDisplayable')
            ->andReturn(new CampaignPagedResultDTO(
                [
                    new CampaignSummaryDTO(
                        id: 'c1',
                        slug: 'test-camp',
                        title: 'Test Camp',
                        shortDescription: null,
                        category: 'health',
                        currencyCode: 'INR',
                        targetAmountMinor: null,
                        isFeatured: false,
                        state: 'active',
                        startsAt: null,
                        endsAt: null,
                        coverImageFileId: null,
                        updatedAt: null,
                    ),
                ],
                1,
                1,
                100,
                false,
            ));
        $this->app->instance(CampaignsQueryContract::class, $campaignMock);

        $eventMock = Mockery::mock(EventsQueryContract::class);
        $eventMock->shouldReceive('listUpcoming')->andReturn([]);
        $eventMock->shouldReceive('listPast')
            ->andReturn(new EventPagedResultDTO([], 0, 1, 100, false));
        $this->app->instance(EventsQueryContract::class, $eventMock);

        $galleryMock = Mockery::mock(GalleryQueryContract::class);
        $galleryMock->shouldReceive('listDisplayable')
            ->andReturn(new GalleryPagedResultDTO([], 0, 1, 100, false));
        $this->app->instance(GalleryQueryContract::class, $galleryMock);

        $response = $this->get('/sitemap.xml');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee('/campaigns/test-camp', false);
        $response->assertSee('<urlset', false);
        $response->assertDontSee('/admin', false);
        $response->assertDontSee('/login', false);
    }
}
