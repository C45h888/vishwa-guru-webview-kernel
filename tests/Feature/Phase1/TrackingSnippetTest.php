<?php

declare(strict_types=1);

namespace Tests\Feature\Phase1;

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Support\Facades\Config;
use Tests\Feature\Payments\Infrastructure\InfrastructureTestCase;

final class TrackingSnippetTest extends InfrastructureTestCase
{
    public function test_no_tracking_tags_without_ids(): void
    {
        $html = (string) $this->get('/contact')->assertOk()->getContent();

        $this->assertStringNotContainsString('googletagmanager.com', $html);
        $this->assertStringNotContainsString('fbevents.js', $html);
    }

    public function test_gtm_snippet_and_noscript_when_gtm_id_set(): void
    {
        Config::set('services.gtm.id', 'GTM-TEST123');
        Config::set('services.meta_pixel.id', '999');

        $html = (string) $this->get('/contact')->assertOk()->getContent();

        $this->assertStringContainsString('gtm.js?id=', $html);
        $this->assertStringContainsString('"GTM-TEST123"', $html);
        $this->assertStringContainsString('ns.html?id=GTM-TEST123', $html);
        // GTM wins: the direct Pixel is not double-loaded.
        $this->assertStringNotContainsString('fbevents.js', $html);
    }

    public function test_shared_trust_props_and_ngo_json_ld(): void
    {
        $version = (string) app(HandleInertiaRequests::class)->version(request());
        $json = $this->get('/contact', ['X-Inertia' => 'true', 'X-Inertia-Version' => $version])->assertOk()->json();

        $this->assertSame('sriramguruji@vsrsms.in', $json['props']['trust']['email']);
        $this->assertSame('https://www.instagram.com/vishwagurushishyavrundham.in/', $json['props']['trust']['instagramUrl']);
        $this->assertNotSame('', $json['props']['seo']['title']);
    }
}
