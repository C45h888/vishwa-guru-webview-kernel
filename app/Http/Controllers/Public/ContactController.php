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
 * (contact_type='address'). Mapnik tiles from OpenStreetMap form the
 * inline map; the directions link opens Google Maps.
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

        // Use only the OSM tiles that make up the currently visible map
        // frame. The Google Maps directions URL is a separate client action.
        $mapTiles = [];
        $mapTileOffsetX = 0.0;
        $mapTileOffsetY = 0.0;
        $mapAddress = null;
        $mapOpenUrl = null;
        foreach ($points as $p) {
            if (($p['contact_type'] ?? null) === 'address' && ! empty($p['value'])) {
                $mapAddress = (string) $p['value'];
                $lat = (float) env('APP_MAP_CENTER_LAT', '12.331205');
                $lon = (float) env('APP_MAP_CENTER_LON', '76.666993');
                $zoom = 15;
                $tileCount = 2 ** $zoom;
                $latitudeRadians = deg2rad($lat);
                $worldX = (($lon + 180.0) / 360.0) * $tileCount;
                $worldY = ((1.0 - asinh(tan($latitudeRadians)) / M_PI) / 2.0) * $tileCount;
                $centerTileX = (int) floor($worldX);
                $centerTileY = (int) floor($worldY);
                $mapTileOffsetX = 512.0 + (($worldX - $centerTileX) * 256.0);
                $mapTileOffsetY = 512.0 + (($worldY - $centerTileY) * 256.0);

                for ($row = 0; $row < 5; $row++) {
                    for ($column = 0; $column < 5; $column++) {
                        $tileX = ($centerTileX + $column - 2 + $tileCount) % $tileCount;
                        $tileY = max(0, min($tileCount - 1, $centerTileY + $row - 2));
                        $mapTiles[] = [
                            'url' => "https://tile.openstreetmap.org/{$zoom}/{$tileX}/{$tileY}.png",
                            'row' => $row,
                            'column' => $column,
                        ];
                    }
                }

                $marker = number_format($lat, 6, '.', '').','
                    .number_format($lon, 6, '.', '');
                $mapOpenUrl = 'https://www.google.com/maps/dir/?api=1&destination='
                    .rawurlencode($marker);
                break;
            }
        }

        return Inertia::render('cms/Contact', [
            'contactPoints' => $points,
            'mapTiles' => $mapTiles,
            'mapTileOffsetX' => $mapTileOffsetX,
            'mapTileOffsetY' => $mapTileOffsetY,
            'mapAddress' => $mapAddress,
            'mapOpenUrl' => $mapOpenUrl,
            'appName' => (string) config('app.name', 'Temple Trust'),
            'appUrl' => (string) config('app.url'),
        ]);
    }
}
