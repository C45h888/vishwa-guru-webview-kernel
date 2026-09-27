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
 * (contact_type='address'). The map uses Google's supported Embed API
 * when a restricted embed key is configured; otherwise it embeds
 * OpenStreetMap. The directions link always opens Google Maps and does
 * not require an API key.
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

        // Build a bounded OSM iframe and universal Google Maps URL from
        // the configured office pin. Google Maps URLs need no API key.
        $mapEmbedUrl = null;
        $mapAddress = null;
        $mapOpenUrl = null;
        foreach ($points as $p) {
            if (($p['contact_type'] ?? null) === 'address' && ! empty($p['value'])) {
                $mapAddress = (string) $p['value'];
                $lat = (float) env('APP_MAP_CENTER_LAT', '12.331205');
                $lon = (float) env('APP_MAP_CENTER_LON', '76.666993');
                $bbox = implode(',', [
                    number_format($lon - 0.012, 6, '.', ''),
                    number_format($lat - 0.008, 6, '.', ''),
                    number_format($lon + 0.012, 6, '.', ''),
                    number_format($lat + 0.008, 6, '.', ''),
                ]);
                $marker = number_format($lat, 6, '.', '').','
                    .number_format($lon, 6, '.', '');
                $googleEmbedKey = config('services.google_maps.embed_api_key');
                if (is_string($googleEmbedKey) && $googleEmbedKey !== '') {
                    $mapEmbedUrl = 'https://www.google.com/maps/embed/v1/place?'
                        .http_build_query([
                            'key' => $googleEmbedKey,
                            'q' => $marker,
                        ], '', '&', PHP_QUERY_RFC3986);
                } else {
                    $mapEmbedUrl = 'https://www.openstreetmap.org/export/embed.html?bbox='
                        .rawurlencode($bbox).'&layer=mapnik&marker='.rawurlencode($marker);
                }
                $mapOpenUrl = 'https://www.google.com/maps/dir/?api=1&destination='
                    .rawurlencode($marker);
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
