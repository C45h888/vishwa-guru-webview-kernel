<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Cms\Services\ContactInformationService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Contact page (GET /contact).
 *
 * Reads all non-archived contact points from ContactInformationService
 * and derives the canonical map embed URL from the primary address row
 * (contact_type='address'). The embed is Microsoft Bing Maps'
 * public `/maps/embed` endpoint — no API key, no signup, no billing
 * account, free at low volume. The Microsoft chrome (search bar, zoom
 * controls, Bing attribution) is more polished / trusted than a raw
 * OpenStreetMap iframe and avoids the OSM tile-usage policy that
 * commercial sites would otherwise violate. Tiles for India are
 * sourced from OSM + TomTom under Microsoft's overlay.
 *
 * Center is driven by APP_MAP_CENTER_LAT / APP_MAP_CENTER_LON env vars
 * (default: 12.331205, 76.666993 — the canonical temple office pin
 * confirmed via the trust's Google Maps short link
 * https://maps.app.goo.gl/z4hNd6suqZwn4h2R7). When no address row is
 * seeded, the Svelte page degrades to the original placeholder.
 *
 * The page is empty-state safe — when no rows are seeded, the Svelte
 * page renders its own fallback message. Doctrine: controller is pure
 * transport; no business logic, no filtering beyond toArray(). We
 * deliberately return every contact_type (address, phone, email, …)
 * and let the Svelte page group them — that way a single non-primary
 * phone row (the trust's second mobile) is visible alongside the
 * primary one.
 */
final class ContactController
{
    public function __invoke(ContactInformationService $contacts): Response
    {
        $points = array_map(
            static fn ($entity) => $entity->toArray(),
            $contacts->listAll(),
        );

        // Derive the Bing Maps embed URL from the canonical address
        // row so the map stays in sync with the address on file. The
        // Bing `/maps/embed` endpoint is the public iframe contract:
        // cp is "lat~lon" (tilde, not comma), lvl is the zoom level,
        // w/h MUST match the iframe container's pixel size exactly,
        // type=road picks the cleaner cartography. No API key
        // required. Bing internally uses MapLibre GL and defaults to
        // a 100x100 canvas when w/h are omitted — that leaves the
        // iframe's full area as the parent background color with
        // only the chrome (zoom, attribution) anchored in the
        // top-left. Pinning w/h forces Bing's internal canvas to the
        // exact container size so the map fills the iframe
        // completely. The Svelte page pairs the iframe with
        // `aspect-[3/2]` to match Bing's natural embed ratio, and
        // the CSS pins the iframe to `h-full w-full` so the
        // container always renders at the w/h we tell Bing about.
        // A separate human-facing "Open in Bing Maps" link points
        // to the consumer site (which is X-Frame-Options locked,
        // but works fine when opened in a new tab).
        $mapEmbedUrl = null;
        $mapAddress = null;
        $mapOpenUrl = null;
        foreach ($points as $p) {
            if (($p['contact_type'] ?? null) === 'address' && ! empty($p['value'])) {
                $mapAddress = (string) $p['value'];
                $lat = (float) env('APP_MAP_CENTER_LAT', '12.331205');
                $lon = (float) env('APP_MAP_CENTER_LON', '76.666993');
                $mapEmbedUrl = sprintf(
                    'https://www.bing.com/maps/embed?cp=%s~%s&amp;lvl=16&amp;w=600&amp;h=400&amp;type=road',
                    $lat,
                    $lon,
                );
                $mapOpenUrl = sprintf(
                    'https://www.bing.com/maps?cp=%s~%s&amp;lvl=16',
                    $lat,
                    $lon,
                );
                break;
            }
        }

        return Inertia::render('cms/Contact', [
            'contactPoints' => $points,
            'mapEmbedUrl' => $mapEmbedUrl,
            'mapAddress' => $mapAddress,
            'mapOpenUrl' => $mapOpenUrl,
            'appName' => (string) config('app.name', 'Temple Trust'),
            'appUrl' => (string) config('app.url'),
        ]);
    }
}