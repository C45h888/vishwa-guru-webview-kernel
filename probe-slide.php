<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Persistence\ValueObjects\EntityId;
use Illuminate\Support\Facades\DB;

/**
 * Hero slideshow — three banners for the home page.
 *
 * Database state at the start of this script:
 *   - cms_media_assets has three short seed IDs (cms_gr_seed_cc_02/14/12)
 *     wrapping the three target file_assets. These cannot be stored
 *     directly in hero_banners.image_file_id because HeroBanner::fromRow
 *     pipes the value through EntityId::fromString, which enforces the
 *     ULID regex pattern. So we rename the three rows to ULID EntityIds
 *     and rewrite the gallery_images.file_asset_id references.
 *   - hero_banners has one row (originally hero_01KYSWKY6...) with
 *     image_file_id = NULL (the previous reference was a stale
 *     cms_media_assets id whose file_assets row was deleted; the slot
 *     rendered as a broken image). We point it at the new Vivekananda
 *     ULID and add two new banners for the other two images.
 *   - hero_banner_pages is empty for the home page (we detached the
 *     broken row in the earlier cleanup). We reattach all three.
 *
 * Atomicity: everything runs in a single transaction. On any failure
 * the whole rename + banner attach is rolled back.
 */

$homePageId = 'static_page_0001N6BR4YWB4RECDQ8H03DTYR';
$now = new DateTimeImmutable();
$nowSql = $now->format('Y-m-d H:i:s.uP');

$renameMap = [
    'cms_gr_seed_cc_02' => 'vivekananda',
    'cms_gr_seed_cc_14' => 'bharatanatyam',
    'cms_gr_seed_cc_12' => 'booklets',
];

$rowsData = [
    'vivekananda' => [
        'title' => 'Help Build the Gaushala and the Shiva Temple',
        'subtitle' => 'Eighteen years of seva in Mysore. Now we are raising funds to acquire the land on which the Gaushala and the Shiva temple will stand — and asking for your seva.',
    ],
    'bharatanatyam' => [
        'title' => 'A Lineage of Bharatanatyam',
        'subtitle' => 'Between 2007 and 2022 our children learned Bharatanatyam from the guru and performed across south India. That chapter is complete — the discipline continues.',
    ],
    'booklets' => [
        'title' => 'Seva in the Community',
        'subtitle' => 'Teaching booklets distributed across the village and ashram — one of the small everyday acts of service that have shaped the trust since 2007.',
    ],
];

$newIds = [];
$oldIds = array_keys($renameMap);

DB::transaction(function () use ($renameMap, $rowsData, $homePageId, $nowSql, &$newIds, $oldIds) {
    // Step 1: generate ULID EntityIds for each cms_media_assets row.
    foreach ($renameMap as $oldId => $key) {
        $newIds[$key] = EntityId::generate('cms_media')->value();
    }

    // Step 2: temporarily drop the FK from gallery_images so we can
    // rename cms_media_assets rows safely. Restore at the end.
    DB::statement('ALTER TABLE gallery_images DROP CONSTRAINT gallery_images_cms_boundary_fk');
    echo '  dropped FK gallery_images_cms_boundary_fk' . PHP_EOL;

    // Step 3: rename cms_media_assets rows.
    foreach ($renameMap as $oldId => $key) {
        DB::statement('UPDATE cms_media_assets SET id = ? WHERE id = ?', [$newIds[$key], $oldId]);
    }
    echo '  renamed 3 cms_media_assets rows to ULIDs' . PHP_EOL;

    // Step 4: rewrite gallery_images.file_asset_id references.
    foreach ($renameMap as $oldId => $key) {
        $updated = DB::table('gallery_images')->where('file_asset_id', $oldId)->update(['file_asset_id' => $newIds[$key]]);
        echo "  rewrote gallery_images.file_asset_id $oldId -> {$newIds[$key]} ($updated rows)" . PHP_EOL;
    }

    // Step 5: re-add the FK.
    DB::statement('ALTER TABLE gallery_images ADD CONSTRAINT gallery_images_cms_boundary_fk FOREIGN KEY (file_asset_id) REFERENCES cms_media_assets(id) ON DELETE RESTRICT');
    echo '  re-added FK gallery_images_cms_boundary_fk' . PHP_EOL;

    // Step 4: restore the original hero_banner's image_file_id to the new
    // Vivekananda ULID. Keep its original title/subtitle (the Gaushala
    // narrative is the strongest fundraiser framing).
    $original = DB::table('hero_banners')
        ->where('id', 'hero_01KYSWKY6MS6EJ4HR3JSQ1G6YX')
        ->whereNull('deleted_at')
        ->first();
    if ($original) {
        DB::table('hero_banners')
            ->where('id', 'hero_01KYSWKY6MS6EJ4HR3JSQ1G6YX')
            ->update([
                'image_file_id' => $newIds['vivekananda'],
                'state' => 'published',
                'updated_at' => $nowSql,
            ]);
        echo '  restored hero_01KYSWKY6MS6EJ4HR3JSQ1G6YX -> ' . $newIds['vivekananda'] . PHP_EOL;
    } else {
        // Should not happen — but if the original row was deleted, re-insert.
        DB::table('hero_banners')->insert([
            'id' => 'hero_01KYSWKY6MS6EJ4HR3JSQ1G6YX',
            'title' => $rowsData['vivekananda']['title'],
            'subtitle' => $rowsData['vivekananda']['subtitle'],
            'cta_label' => null,
            'cta_url' => null,
            'image_file_id' => $newIds['vivekananda'],
            'mobile_image_file_id' => null,
            'state' => 'published',
            'display_order' => 1,
            'starts_at' => $nowSql,
            'ends_at' => null,
            'created_at' => $nowSql,
            'updated_at' => $nowSql,
            'deleted_at' => null,
            'created_by' => 'system',
            'updated_by' => 'system',
        ]);
        echo '  re-inserted hero_01KYSWKY6MS6EJ4HR3JSQ1G6YX' . PHP_EOL;
    }

    // Step 5: insert the two new hero_banners (Bharatanatyam, Booklets).
    $bharatanatyamId = 'hero_01KYSWKY6MS6EJ4HR3JSQ1G6BHRTN';
    $bookletsId = 'hero_01KYSWKY6MS6EJ4HR3JSQ1G6BKLTS';

    // Idempotency: only insert if a banner with this id doesn't already exist.
    foreach ([
        [
            'id' => $bharatanatyamId,
            'title' => $rowsData['bharatanatyam']['title'],
            'subtitle' => $rowsData['bharatanatyam']['subtitle'],
            'image_file_id' => $newIds['bharatanatyam'],
            'display_order' => 2,
        ],
        [
            'id' => $bookletsId,
            'title' => $rowsData['booklets']['title'],
            'subtitle' => $rowsData['booklets']['subtitle'],
            'image_file_id' => $newIds['booklets'],
            'display_order' => 3,
        ],
    ] as $row) {
        if (! DB::table('hero_banners')->where('id', $row['id'])->exists()) {
            DB::table('hero_banners')->insert([
                'id' => $row['id'],
                'title' => $row['title'],
                'subtitle' => $row['subtitle'],
                'cta_label' => null,
                'cta_url' => null,
                'image_file_id' => $row['image_file_id'],
                'mobile_image_file_id' => null,
                'state' => 'published',
                'display_order' => $row['display_order'],
                'starts_at' => $nowSql,
                'ends_at' => null,
                'created_at' => $nowSql,
                'updated_at' => $nowSql,
                'deleted_at' => null,
                'created_by' => 'system',
                'updated_by' => 'system',
            ]);
            echo '  inserted ' . $row['id'] . PHP_EOL;
        }
    }

    // Step 6: attach all three banners to the home page in display_order.
    $attach = [
        ['hero_01KYSWKY6MS6EJ4HR3JSQ1G6YX', 1],
        [$bharatanatyamId, 2],
        [$bookletsId, 3],
    ];
    foreach ($attach as [$bid, $ord]) {
        DB::statement(
            'INSERT INTO hero_banner_pages (hero_banner_id, static_page_id, display_order, created_at)
             VALUES (:bid, :pid, :ord, :now)
             ON CONFLICT (hero_banner_id, static_page_id)
             DO UPDATE SET display_order = EXCLUDED.display_order',
            ['bid' => $bid, 'pid' => $homePageId, 'ord' => $ord, 'now' => $nowSql],
        );
    }
});

// Read-back.
echo PHP_EOL . "=== final hero_banners (alive) ===" . PHP_EOL;
foreach (DB::table('hero_banners')->whereNull('deleted_at')->orderBy('display_order')->get() as $r) {
    echo '  ' . $r->id . ' | order=' . $r->display_order . ' | img=' . $r->image_file_id . ' | ' . substr((string) $r->title, 0, 50) . PHP_EOL;
}
echo PHP_EOL . "=== hero_banner_pages for home ===" . PHP_EOL;
foreach (DB::table('hero_banner_pages')->where('static_page_id', $homePageId)->orderBy('display_order')->get() as $r) {
    echo '  ' . $r->hero_banner_id . ' | order=' . $r->display_order . PHP_EOL;
}
echo PHP_EOL . "=== cms_media_assets for the three target files ===" . PHP_EOL;
foreach (DB::table('cms_media_assets')->whereIn('file_asset_id', ['fa_gr_seed_cc_02', 'fa_gr_seed_cc_14', 'fa_gr_seed_cc_12'])->get() as $r) {
    echo '  ' . $r->id . ' | file=' . $r->file_asset_id . ' | alt=' . $r->alt_text . ' | created=' . $r->created_at . PHP_EOL;
}

echo PHP_EOL . "=== exercise StaticPageRenderer ===" . PHP_EOL;
$svc = app(\App\Cms\Contracts\StaticPageRendererContract::class);
$rendered = $svc->renderBySlug(new \App\Cms\Domain\ValueObjects\PageSlug('home'));
echo 'heroBanners count from renderer: ' . count($rendered->heroBanners) . PHP_EOL;
foreach ($rendered->heroBanners as $b) {
    echo '  ' . $b->id()->value() . ' | img=' . ($b->imageFileId()?->value() ?? 'null') . ' | ' . substr((string) $b->title(), 0, 50) . PHP_EOL;
}

echo PHP_EOL . "DONE." . PHP_EOL;
