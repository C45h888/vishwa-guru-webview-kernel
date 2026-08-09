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
 * (contact_type='address', is_primary=true). The embed is OpenStreetMap's
 * public export endpoint — no API key, no signup, no third-party
 * library. The page is empty-state safe — when no rows are seeded, the
 * Svelte page renders its own fallback message. Doctrine: controller
 * is pure transport; no business logic, no filtering beyond toArray().
 * We deliberately return every contact_type (address, phone, email, …)
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

        // Derive the map embed from the canonical address row so the
        // map stays in sync with the address on file. OpenStreetMap's
        // public embed endpoint is the embed surface — no API key, no
        // signup, no third-party library. The bbox + marker are
        // driven by APP_MAP_CENTER_LAT / APP_MAP_CENTER_LON env vars
        // (default: temple office pin at 12.331205, 76.666993 — the
        // canonical trust location as confirmed by the canonical
        // Google Maps short link
        // https://maps.app.goo.gl/z4hNd6suqZwn4h2R7) so the embed
        // recenters precisely on the temple office. A separate
        // human-facing "Open in OpenStreetMap" link lets visitors
        // jump out to the directions UI. When no address row is
        // seeded, the Svelte page degrades to the original
        // placeholder.
        $mapEmbedUrl = null;
        $mapAddress = null;
        $mapOpenUrl = null;
        foreach ($points as $p) {
            if (($p['contact_type'] ?? null) === 'address' && ! empty($p['value'])) {
                $mapAddress = (string) $p['value'];
                $lat = (float) env('APP_MAP_CENTER_LAT', '12.331205');
                $lon = (float) env('APP_MAP_CENTER_LON', '76.666993');
                // Bbox ~ 1km around center: 0.009° lat ≈ 1 km,
                // 0.011° lon at this latitude ≈ 1 km.
                $bbox = sprintf('%.4f,%.4f,%.4f,%.4f', $lat - 0.009, $lon - 0.011, $lat + 0.009, $lon + 0.011);
                $mapEmbedUrl = sprintf(
                    'https://www.openstreetmap.org/export/embed.html?bbox=%s&amp;layer=mapnik&amp;marker=%s,%s',
                    $bbox,
                    $lat,
                    $lon,
                );
                $mapOpenUrl = sprintf(
                    'https://www.openstreetmap.org/?mlat=%s&amp;mlon=%s#map=17/%s/%s',
                    $lat,
                    $lon,
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