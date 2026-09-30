<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public\Seo;

use Illuminate\Http\Response;

/**
 * Serves /robots.txt as plain text (not Inertia — crawlers don't do Inertia).
 *
 * Admin/auth surfaces are disallowed; the sitemap URL is generated from
 * the named route so it always carries the canonical APP_URL host.
 */
final class RobotsController
{
    public function __invoke(): Response
    {
        $body = implode("\n", [
            'User-agent: *',
            'Allow: /',
            '',
            'Disallow: /admin/',
            'Disallow: /login',
            'Disallow: /logout',
            '',
            'Sitemap: '.route('seo.sitemap'),
            '',
        ]);

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
