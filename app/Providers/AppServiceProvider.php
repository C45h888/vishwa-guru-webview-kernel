<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Vite is configured (vite.config.ts) to emit the manifest at
        // build/manifest.json. Laravel's default lookup matches that path,
        // so no override is required.

        // ══════════════════════════════════════════════════════════════
        // Canonical host pinning (Pass 5 — SEO Optimisation)
        // ══════════════════════════════════════════════════════════════
        //
        // `route()` and `url()` derive absolute URLs from the host the
        // request ARRIVED ON, not from APP_URL. That produced a real
        // contradiction on the live site: /sitemap.xml advertised
        // `https://www.vsrsms.in/...` (whatever host a crawler hit)
        // while the canonical tags in <head> advertised `https://vsrsms.in`.
        // Search engines resolve that conflict arbitrarily and the loser
        // burns crawl budget.
        //
        // Pinning the root URL makes generated URLs, canonical tags and
        // og:url all derive from one configured value.
        //
        // Guarded on a scheme being present: a bare host or IP in APP_URL
        // (which is how a dev box can be configured) is left alone rather
        // than force-rooted onto an unusable URL.
        $appUrl = trim((string) config('app.url', ''));

        if ($appUrl !== '' && preg_match('#^https?://#i', $appUrl) === 1) {
            URL::forceRootUrl(rtrim($appUrl, '/'));
        }
    }
}
