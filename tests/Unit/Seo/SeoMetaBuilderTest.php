<?php

declare(strict_types=1);

namespace Tests\Unit\Seo;

use App\Seo\Services\SeoMetaBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Unit coverage for the SEO metadata builder.
 *
 * These tests pin the tag SHAPE, not page content. The guarantee that
 * matters for the public webview is the Open Graph quartet
 * (og:title / og:description / og:url / og:image) because WhatsApp,
 * Facebook and iMessage all read exactly those four server-side and
 * none of them execute JavaScript.
 */
final class SeoMetaBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.name', 'SRI VISHWAGURU SRI SRI SRIRAM SHISHYAVRUNDHAM MAHASAMSTHANAM');
        Config::set('app.url', 'https://vsrsms.in');
    }

    private function builder(string $uri = '/'): SeoMetaBuilder
    {
        $request = Request::create($uri);

        $this->app->instance('request', $request);

        return new SeoMetaBuilder($request);
    }

    /**
     * @param  list<array{tag: string, attrs: array<string, string>}>  $tags
     * @return array<string, string>
     */
    private function indexBy(array $tags, string $kind, string $key): array
    {
        $out = [];

        foreach ($tags as $tag) {
            if ($tag['tag'] !== $kind) {
                continue;
            }
            $out[$tag['attrs'][$key] ?? ''] = $tag['attrs']['content']
                ?? $tag['attrs']['href']
                ?? '';
        }

        return $out;
    }

    public function test_emits_the_open_graph_quartet_all_three_scrapers_read(): void
    {
        $payload = $this->builder('/about')->forPage(
            title: 'About',
            description: 'About the trust.',
        );

        $og = $this->indexBy($payload['tags'], 'meta', 'property');

        $this->assertSame('About — SRI VISHWAGURU SRI SRI SRIRAM SHISHYAVRUNDHAM MAHASAMSTHANAM', $og['og:title']);
        $this->assertSame('About the trust.', $og['og:description']);
        $this->assertSame('https://vsrsms.in/about', $og['og:url']);
        $this->assertArrayHasKey('og:site_name', $og);
        $this->assertArrayHasKey('og:type', $og);
    }

    public function test_canonical_and_og_url_never_disagree(): void
    {
        $payload = $this->builder('/campaigns/gaushala')->forPage(title: 'Gaushala');

        $links = $this->indexBy($payload['tags'], 'link', 'rel');
        $og = $this->indexBy($payload['tags'], 'meta', 'property');

        $this->assertSame('https://vsrsms.in/campaigns/gaushala', $links['canonical']);
        $this->assertSame($links['canonical'], $og['og:url']);
    }

    public function test_absolute_share_image_is_preserved_and_alt_is_emitted(): void
    {
        $payload = $this->builder()->forPage(
            title: 'Home',
            imageUrl: 'https://vsrsms.in/media/cms_media_123',
            imageAlt: 'Temple celebration — pooje at the trust',
        );

        $og = $this->indexBy($payload['tags'], 'meta', 'property');

        $this->assertSame('https://vsrsms.in/media/cms_media_123', $og['og:image']);
        $this->assertSame('Temple celebration — pooje at the trust', $og['og:image:alt']);
    }

    public function test_relative_share_image_is_promoted_to_absolute(): void
    {
        $payload = $this->builder()->forPage(
            title: 'Home',
            imageUrl: '/media/cms_media_123',
        );

        $og = $this->indexBy($payload['tags'], 'meta', 'property');

        $this->assertSame('https://vsrsms.in/media/cms_media_123', $og['og:image']);
    }

    public function test_noindex_is_emitted_only_when_requested(): void
    {
        $indexable = $this->builder('/donate')->forPage(title: 'Donate');
        $robots = $this->indexBy($indexable['tags'], 'meta', 'name');

        $this->assertArrayNotHasKey('robots', $robots);

        $noindex = $this->builder('/donate/receipt')->forPage(
            title: 'Receipt',
            noindex: true,
        );
        $robots = $this->indexBy($noindex['tags'], 'meta', 'name');

        $this->assertSame('noindex, nofollow', $robots['robots']);
    }

    public function test_homepage_title_is_the_trust_name_without_a_suffix(): void
    {
        $payload = $this->builder('/')->forPage(
            title: 'SRI VISHWAGURU SRI SRI SRIRAM SHISHYAVRUNDHAM MAHASAMSTHANAM',
            appendTrustName: false,
        );

        $this->assertSame(
            'SRI VISHWAGURU SRI SRI SRIRAM SHISHYAVRUNDHAM MAHASAMSTHANAM',
            $payload['title'],
        );
    }

    public function test_json_ld_is_pre_encoded_for_identical_server_and_client_output(): void
    {
        $builder = $this->builder();
        $payload = $builder->forPage(title: 'Home', jsonLd: $builder->trustGraph());

        $this->assertSame('HinduTemple', $payload['jsonLd']['@type'] ?? null);
        $this->assertSame('https://vsrsms.in', $payload['jsonLd']['url'] ?? null);
        $this->assertSame('https://vsrsms.in/icon-512.png', $payload['jsonLd']['logo'] ?? null);

        $this->assertIsString($payload['jsonLdString']);
        $this->assertJson($payload['jsonLdString']);
    }

    public function test_no_canonical_is_emitted_when_no_public_host_is_configured(): void
    {
        Config::set('app.url', 'http://localhost');

        $payload = $this->builder('/about')->forPage(title: 'About');

        $links = $this->indexBy($payload['tags'], 'link', 'rel');
        $og = $this->indexBy($payload['tags'], 'meta', 'property');

        $this->assertArrayNotHasKey('canonical', $links);
        $this->assertArrayNotHasKey('og:url', $og);
    }

    /**
     * Regression guard.
     *
     * A bare host or IP in APP_URL (no scheme) previously produced
     * `href="86.107.77.79/campaigns"` — a malformed canonical that asks a
     * search engine to consolidate real pages to a URL it cannot fetch.
     * Caught by rendering the real app under local Docker.
     */
    public function test_scheme_less_app_url_never_yields_a_malformed_canonical(): void
    {
        Config::set('app.url', '86.107.77.79');

        $payload = $this->builder('/about')->forPage(title: 'About');

        $links = $this->indexBy($payload['tags'], 'link', 'rel');
        $og = $this->indexBy($payload['tags'], 'meta', 'property');

        $this->assertArrayNotHasKey('canonical', $links);
        $this->assertArrayNotHasKey('og:url', $og);

        // No emitted URL may be a bare host. `content` is not always a
        // URL (og:site_name is plain text), so the check targets href and
        // the URL-bearing tags rather than every attribute value.
        foreach ($payload['tags'] as $tag) {
            $href = $tag['attrs']['href'] ?? null;
            $this->assertNotSame('86.107.77.79/', $href);

            $key = $tag['attrs']['property'] ?? $tag['attrs']['name'] ?? '';
            $isUrlTag = in_array(
                $key,
                ['og:url', 'og:image', 'twitter:image'],
                true,
            );

            if (! $isUrlTag) {
                continue;
            }

            $this->assertDoesNotMatchRegularExpression(
                '#^(?![a-z][a-z0-9+.-]*://)#i',
                $tag['attrs']['content'] ?? '',
                "Tag {$key} emitted a scheme-less URL.",
            );
        }
    }

    public function test_trust_graph_omits_url_and_logo_without_a_valid_host(): void
    {
        Config::set('app.url', '86.107.77.79');

        $graph = $this->builder()->trustGraph();

        $this->assertSame('HinduTemple', $graph['@type']);
        $this->assertArrayNotHasKey('url', $graph);
        $this->assertArrayNotHasKey('logo', $graph);
    }
}
