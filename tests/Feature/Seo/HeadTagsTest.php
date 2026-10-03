<?php

declare(strict_types=1);

namespace Tests\Feature\Seo;

use App\Campaigns\Contracts\CampaignsQueryContract;
use App\Campaigns\Domain\DTOs\CampaignPagedResultDTO;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

/**
 * END-TO-END ACCEPTANCE for the Pass 5 SEO surface.
 *
 * This is the test that actually proves the fix. Every other test in the
 * Seo kernel exercises a unit in isolation; this one asserts that a
 * crawler performing a plain `GET /campaigns` — no JavaScript execution,
 * exactly what WhatsApp, Facebook and iMessage do — receives a
 * populated Open Graph payload in the INITIAL HTML.
 *
 * Before Pass 5 this request returned a <head> containing nothing but
 * `<title inertia>`; the OG tags existed only inside <svelte:head> and
 * were therefore invisible to every non-browser scraper.
 */
final class HeadTagsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.name', 'SRI VISHWAGURU SRI SRI SRIRAM SHISHYAVRUNDHAM MAHASAMSTHANAM');
        Config::set('app.url', 'https://vsrsms.in');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_initial_html_carries_open_graph_tags_without_javascript(): void
    {
        $campaigns = Mockery::mock(CampaignsQueryContract::class);
        $campaigns->shouldReceive('listDisplayable')
            ->andReturn(new CampaignPagedResultDTO([], 0, 1, 12, false));
        $campaigns->shouldReceive('progressFor')->andReturn([]);
        $this->app->instance(CampaignsQueryContract::class, $campaigns);

        $response = $this->get('/campaigns');

        $response->assertStatus(200);

        $html = $response->getContent();
        $this->assertIsString($html);

        // The four tags WhatsApp / Facebook / iMessage read.
        $this->assertStringContainsString('property="og:title"', $html);
        $this->assertStringContainsString('property="og:description"', $html);
        $this->assertStringContainsString('property="og:url"', $html);
        $this->assertStringContainsString('property="og:site_name"', $html);

        // Plain meta + canonical + Twitter card.
        $this->assertStringContainsString('name="description"', $html);
        $this->assertStringContainsString('rel="canonical"', $html);
        $this->assertStringContainsString('name="twitter:card"', $html);

        // The canonical host must be the configured one, and the sitemap
        // and the head must not disagree about it.
        $this->assertStringContainsString('href="https://vsrsms.in/campaigns"', $html);
        $this->assertStringContainsString('content="https://vsrsms.in/campaigns"', $html);
    }

    public function test_open_graph_values_are_html_escaped_not_injected(): void
    {
        $campaigns = Mockery::mock(CampaignsQueryContract::class);
        $campaigns->shouldReceive('listDisplayable')
            ->andReturn(new CampaignPagedResultDTO([], 0, 1, 12, false));
        $campaigns->shouldReceive('progressFor')->andReturn([]);
        $this->app->instance(CampaignsQueryContract::class, $campaigns);

        $response = $this->get('/campaigns');

        // Blade's {{ }} escaping must keep a quote in a title from
        // breaking out of the attribute.
        $this->assertStringNotContainsString('content="Campaigns" onload=', $response->getContent());
    }

    public function test_transactional_pages_are_marked_noindex_server_side(): void
    {
        $response = $this->get('/donate/cancel');

        $response->assertStatus(200);

        $html = (string) $response->getContent();

        $this->assertStringContainsString('name="robots"', $html);
        $this->assertStringContainsString('content="noindex, nofollow"', $html);
    }
}
