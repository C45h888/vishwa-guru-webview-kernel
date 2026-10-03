<?php

declare(strict_types=1);

namespace Tests\Unit\Seo;

use App\Campaigns\Contracts\CampaignsQueryContract;
use App\Campaigns\Domain\DTOs\CampaignPagedResultDTO;
use App\Campaigns\Domain\DTOs\CampaignSummaryDTO;
use App\Seo\Services\SitemapBuilder;
use App\Seo\Services\SitemapUrl;
use App\Events\Contracts\EventsQueryContract;
use App\Events\Domain\DTOs\EventPagedResultDTO;
use App\Events\Domain\DTOs\EventSummaryDTO;
use App\Gallery\Contracts\GalleryQueryContract;
use App\Gallery\Domain\DTOs\GalleryPagedResultDTO;
use App\Gallery\Domain\DTOs\GallerySummaryDTO;
use DateTimeImmutable;
use Mockery;
use Tests\TestCase;

/**
 * Unit coverage for the sitemap builder.
 *
 * The three query contracts are mocked: the test proves URL shaping,
 * lastmod rules, and XML escaping — never database content. Contract
 * behavior itself is covered by each kernel's own suite.
 */
final class SitemapBuilderTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_build_contains_home_with_top_priority(): void
    {
        $builder = $this->builderWith([], [], [], []);

        $urls = $builder->build();
        $byLoc = $this->indexByLoc($urls);

        $home = route('home');
        $this->assertArrayHasKey($home, $byLoc);
        $this->assertSame('1.0', $byLoc[$home]->priority);
        $this->assertSame('daily', $byLoc[$home]->changefreq);
    }

    public function test_build_maps_displayable_slugs_with_lastmod(): void
    {
        $campaign = new CampaignSummaryDTO(
            id: 'c1',
            slug: 'clean-water',
            title: 'Clean Water',
            shortDescription: null,
            category: 'health',
            currencyCode: 'INR',
            targetAmountMinor: null,
            isFeatured: false,
            state: 'active',
            startsAt: null,
            endsAt: null,
            coverImageFileId: null,
            updatedAt: new DateTimeImmutable('2026-09-20 10:00:00'),
        );

        $builder = $this->builderWith([$campaign], [], [], []);

        $byLoc = $this->indexByLoc($builder->build());

        $expected = route('campaigns.show', ['slug' => 'clean-water']);
        $this->assertArrayHasKey($expected, $byLoc);
        $this->assertSame('2026-09-20', $byLoc[$expected]->lastmod);
    }

    public function test_build_includes_gallery_published_date_and_cms_pages(): void
    {
        $gallery = new GallerySummaryDTO(
            id: 'g1',
            slug: 'diwali-2025',
            title: 'Diwali 2025',
            shortDescription: null,
            coverImageFileId: null,
            imageCount: 4,
            isFeatured: false,
            publishedAt: new DateTimeImmutable('2025-10-20 00:00:00'),
            state: 'published',
        );

        $builder = $this->builderWith([], [], [], [$gallery]);

        $byLoc = $this->indexByLoc($builder->build());

        $expected = route('gallery.show', ['slug' => 'diwali-2025']);
        $this->assertArrayHasKey($expected, $byLoc);
        $this->assertSame('2025-10-20', $byLoc[$expected]->lastmod);

        $this->assertArrayHasKey(route('cms.public-page.show', ['slug' => 'privacy']), $byLoc);
    }

    public function test_to_xml_omits_lastmod_when_null_and_escapes(): void
    {
        $builder = $this->builderWith([], [], [], []);

        $xml = $builder->toXml([
            new SitemapUrl('https://vsrsms.in/campaigns/a&b', null),
            new SitemapUrl('https://vsrsms.in/about', '2026-01-02'),
        ]);

        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringEndsWith("</urlset>\n", $xml);
        $this->assertStringContainsString('<loc>https://vsrsms.in/campaigns/a&amp;b</loc>', $xml);
        $this->assertSame(1, substr_count($xml, '<lastmod>'));
        $this->assertStringContainsString('<lastmod>2026-01-02</lastmod>', $xml);

        // Well-formed XML.
        $this->assertNotFalse(simplexml_load_string($xml));
    }

    /**
     * @param list<CampaignSummaryDTO> $campaigns
     * @param list<EventSummaryDTO>    $upcoming
     * @param list<EventSummaryDTO>    $past
     * @param list<GallerySummaryDTO>  $galleries
     */
    private function builderWith(array $campaigns, array $upcoming, array $past, array $galleries): SitemapBuilder
    {
        $campaignMock = Mockery::mock(CampaignsQueryContract::class);
        $campaignMock->shouldReceive('listDisplayable')
            ->zeroOrMoreTimes()
            ->andReturn(new CampaignPagedResultDTO($campaigns, count($campaigns), 1, 100, false));

        $eventMock = Mockery::mock(EventsQueryContract::class);
        $eventMock->shouldReceive('listUpcoming')
            ->zeroOrMoreTimes()
            ->andReturn($upcoming);
        $eventMock->shouldReceive('listPast')
            ->zeroOrMoreTimes()
            ->andReturn(new EventPagedResultDTO($past, count($past), 1, 100, false));

        $galleryMock = Mockery::mock(GalleryQueryContract::class);
        $galleryMock->shouldReceive('listDisplayable')
            ->zeroOrMoreTimes()
            ->andReturn(new GalleryPagedResultDTO($galleries, count($galleries), 1, 100, false));

        return new SitemapBuilder($campaignMock, $eventMock, $galleryMock);
    }

    /**
     * @param list<SitemapUrl> $urls
     * @return array<string, SitemapUrl>
     */
    private function indexByLoc(array $urls): array
    {
        $out = [];

        foreach ($urls as $url) {
            $out[$url->loc] = $url;
        }

        return $out;
    }
}
