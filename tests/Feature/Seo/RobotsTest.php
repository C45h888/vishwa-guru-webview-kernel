<?php

declare(strict_types=1);

namespace Tests\Feature\Seo;

use Tests\TestCase;

/**
 * /robots.txt is static text by design — no mocks needed.
 */
final class RobotsTest extends TestCase
{
    public function test_robots_allows_public_disallows_private_and_names_sitemap(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $response->assertSee('Allow: /', false);
        $response->assertSee('Disallow: /admin/', false);
        $response->assertSee('Disallow: /login', false);
        $response->assertSee('Disallow: /logout', false);
        $response->assertSee('Sitemap: ', false);
        $response->assertSee('/sitemap.xml', false);
    }
}
