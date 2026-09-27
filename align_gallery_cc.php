<?php

declare(strict_types=1);

/**
 * Gallery realignment — delete Sacred Festivals, seed Community & Cultural.
 *
 * 1. Soft-deletes gal_seed_sf (Sacred Festivals) + all its gallery_images.
 * 2. Replace gal_seed_cc (Community & Cultural) images with 11 new canonical
 *    media assets (display_order 0–10). First 11 existing gallery_images
 *    are updated in-place; the remaining 6 are soft-deleted.
 * 3. Gallery cover_image_file_id set to the first new image.
 *
 * Run:  docker exec temple-trust-app php /app/align_gallery_cc.php
 * Dry:  docker exec temple-trust-app php /app/align_gallery_cc.php --dry-run
 */

$dry = in_array('--dry-run', $argv ?? [], true);

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$env = file('/app/.env');
$url = '';
foreach ($env as $line) {
    $line = trim($line);
    if (str_starts_with($line, 'DATABASE_URL=')) {
        $url = trim(substr($line, 13));
        break;
    }
}
$u = parse_url($url);
$db = ltrim($u['path'] ?? '/neondb', '/');
$sslmode = 'require';
if (isset($u['query'])) {
    parse_str($u['query'], $q);
    if (isset($q['sslmode'])) {
        $sslmode = $q['sslmode'];
    }
}
$dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s;sslmode=%s', $u['host'], $u['port'] ?? 5432, $db, $sslmode);
$pdo = new PDO($dsn, $u['user'], $u['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

const CANON = '/app/storage/app/public/cms-media-upscaled/canonical';
const STORAGE_PREFIX = 'cms-media-upscaled/canonical/';
const CC_GALLERY_ID = 'gal_seed_cc';
const SF_GALLERY_ID = 'gal_seed_sf';

function fileMeta(string $file): array
{
    $path = CANON.'/'.$file;
    if (! is_file($path)) {
        throw new RuntimeException("Missing canonical file: {$file}");
    }
    $dim = @getimagesize($path);
    return [
        'bytes' => (int) filesize($path),
        'mime' => (string) (new finfo(FILEINFO_MIME_TYPE))->file($path),
        'sha' => (string) hash_file('sha256', $path),
        'w' => $dim[0] ?? null,
        'h' => $dim[1] ?? null,
    ];
}

$images = [
    ['gimg_id' => 'gimg_seed_cc_01', 'fa_id' => 'file_asset_0001NBFZV1YBPG5Y6738KH1YNS', 'cma_id' => 'cms_media_0001NBFZV1N6TQB9X67KCD0BRV', 'canonical' => 'children.jpg',                     'alt' => 'Children at the trust\u2019s schools'],
    ['gimg_id' => 'gimg_seed_cc_02', 'fa_id' => 'file_asset_0001NBFZV1JTC28J3NTEHCEC3M', 'cma_id' => 'cms_media_0001NBFZV1WZKCM4SJVJR1115C', 'canonical' => 'dance-girls2.png',                'alt' => 'Girls in traditional dance performance'],
    ['gimg_id' => 'gimg_seed_cc_03', 'fa_id' => 'file_asset_0001NBFZV1QNXTGB6JNYSSVBWS', 'cma_id' => 'cms_media_0001NBFZV1FC9KT1R653ZKW617', 'canonical' => 'dance.png',                       'alt' => 'Bharatanatyam dance recital'],
    ['gimg_id' => 'gimg_seed_cc_04', 'fa_id' => 'file_asset_0001NBFZV1HJJ8HPFR55FAP6DZ', 'cma_id' => 'cms_media_0001NBFZV1PZM93EC7WRNN94BR', 'canonical' => 'edited-photo.jpg.jpg',             'alt' => 'Temple ceremony — edited photograph'],
    ['gimg_id' => 'gimg_seed_cc_05', 'fa_id' => 'file_asset_0001NBFZV1FX4P1GQ271ERVDSX', 'cma_id' => 'cms_media_0001NBFZV1BC6ZWWZZQP98P8JY', 'canonical' => 'edited-photo-20.jpg.jpg',          'alt' => 'Community event — edited photograph'],
    ['gimg_id' => 'gimg_seed_cc_06', 'fa_id' => 'file_asset_0001NBFZV1RR3PZC23BRY6W96V', 'cma_id' => 'cms_media_0001NBFZV1MFH57J5EKNFRRCCV', 'canonical' => 'ChatGPT Image 22 Sept 2026, 17_33_48.png', 'alt' => 'AI-generated mandapa temple scene'],
    ['gimg_id' => 'gimg_seed_cc_07', 'fa_id' => 'file_asset_0001NBFZV1ZEDRAZ3PEXTZBT48', 'cma_id' => 'cms_media_0001NBFZV13G20GATMKY0KNN8J', 'canonical' => 'journal-awards-03-shishya-celebration.jpg', 'alt' => 'Shishya celebration — awards ceremony'],
    ['gimg_id' => 'gimg_seed_cc_08', 'fa_id' => 'file_asset_0001NBFZV19JPJTY9RNM0TWGBE', 'cma_id' => 'cms_media_0001NBFZV1JA6F37S6YDJH0CGT', 'canonical' => 'journal-indoor-01.jpg',               'alt' => 'Indoor gathering — community at the trust'],
    ['gimg_id' => 'gimg_seed_cc_09', 'fa_id' => 'file_asset_0001NBFZV1XCBJ4C7V2C6QMQD4', 'cma_id' => 'cms_media_0001NBFZV18FZDHJPHJ7HBW7BG', 'canonical' => 'kids-event.png',                   'alt' => 'Kids festival — annual event celebration'],
    ['gimg_id' => 'gimg_seed_cc_10', 'fa_id' => 'file_asset_0001NBFZV1FT4TG9DFQT2AM681', 'cma_id' => 'cms_media_0001NBFZV1447KQ17W5PHK98QZ', 'canonical' => 'reupskaled-image.png',              'alt' => 'Upscaled community heritage image'],
    ['gimg_id' => 'gimg_seed_cc_11', 'fa_id' => 'file_asset_0001NBFZV122E2W6JTTD81RJ4C', 'cma_id' => 'cms_media_0001NBFZV1QB14QGTVJ972NQ44', 'canonical' => 'reupscaked-image.png',             'alt' => 'Upscaled temple heritage image'],
];

$delete = ['gimg_seed_cc_12', 'gimg_seed_cc_13', 'gimg_seed_cc_14', 'gimg_seed_cc_15', 'gimg_seed_cc_16', 'gimg_seed_cc_17'];

echo $dry ? "== DRY RUN (no writes) ==\n" : "== APPLYING gallery realignment ==\n";

$pdo->beginTransaction();

try {
    // ── 1. Soft-delete Sacred Festivals ──────────────────────────
    echo "  -- gal_seed_sf (Sacred Festivals): soft-deleting gallery + 14 images\n";
    if (! $dry) {
        $pdo->prepare("UPDATE galleries SET state = 'archived', deleted_at = now(), updated_at = now() WHERE id = :g")->execute(['g' => SF_GALLERY_ID]);
        $pdo->prepare("UPDATE gallery_images SET state = 'archived', deleted_at = now(), updated_at = now() WHERE gallery_id = :g")->execute(['g' => SF_GALLERY_ID]);
    }

    // ── 2. Create file_assets + cms_media_assets for each new image ──
    $upsertFa = $pdo->prepare(
        'INSERT INTO file_assets
            (id, owner_type, owner_id, original_filename, storage_disk, storage_path,
             mime_type, file_size_bytes, file_hash_sha256, purpose, is_public, is_archived,
             metadata, uploaded_at, created_at, updated_at)
         VALUES
            (:id, :owner_type, :owner_id, :orig, :disk, :path,
             :mime, :bytes, :sha, :purpose, true, false,
             :metadata, now(), now(), now())
         ON CONFLICT (id) DO UPDATE SET
            owner_type = EXCLUDED.owner_type,
            owner_id = EXCLUDED.owner_id,
            original_filename = EXCLUDED.original_filename,
            storage_disk = EXCLUDED.storage_disk,
            storage_path = EXCLUDED.storage_path,
            mime_type = EXCLUDED.mime_type,
            file_size_bytes = EXCLUDED.file_size_bytes,
            file_hash_sha256 = EXCLUDED.file_hash_sha256,
            purpose = EXCLUDED.purpose,
            is_public = true,
            is_archived = false,
            updated_at = now()'
    );
    $upsertCma = $pdo->prepare(
        'INSERT INTO cms_media_assets
            (id, file_asset_id, media_type, state, alt_text, width, height,
             published_at, created_at, updated_at)
         VALUES
            (:id, :file_asset_id, :media_type, :state, :alt, :w, :h,
             now(), now(), now())
         ON CONFLICT (id) DO UPDATE SET
            file_asset_id = EXCLUDED.file_asset_id,
            media_type = EXCLUDED.media_type,
            state = EXCLUDED.state,
            alt_text = EXCLUDED.alt_text,
            width = EXCLUDED.width,
            height = EXCLUDED.height,
            published_at = EXCLUDED.published_at,
            updated_at = now()'
    );

    // ── 3. Update gal_seed_cc gallery_rows (first 11) ───────────
    $updateGimg = $pdo->prepare(
        'UPDATE gallery_images
            SET file_asset_id = :cma, alt_text = :alt, display_order = :ord,
                state = :state, published_at = now(), updated_at = now()
          WHERE id = :id'
    );

    foreach ($images as $i => $img) {
        $m = fileMeta($img['canonical']);
        printf("  %-22s order=%-2d %s (%dx%d, %d B)\n", $img['gimg_id'], $i, $img['canonical'], $m['w'] ?? 0, $m['h'] ?? 0, $m['bytes']);
        if ($dry) { continue; }
        $upsertFa->execute([
            'id' => $img['fa_id'],
            'owner_type' => 'gallery_image',
            'owner_id' => CC_GALLERY_ID,
            'orig' => basename($img['canonical']),
            'disk' => 'public',
            'path' => STORAGE_PREFIX.$img['canonical'],
            'mime' => $m['mime'],
            'bytes' => $m['bytes'],
            'sha' => $m['sha'],
            'purpose' => 'gallery_image',
            'metadata' => json_encode(['aligned_by' => 'align_gallery_cc.php'], JSON_THROW_ON_ERROR),
        ]);
        $upsertCma->execute([
            'id' => $img['cma_id'],
            'file_asset_id' => $img['fa_id'],
            'media_type' => 'gallery_image',
            'state' => 'published',
            'alt' => $img['alt'],
            'w' => $m['w'],
            'h' => $m['h'],
        ]);
        $updateGimg->execute([
            'cma' => $img['cma_id'],
            'alt' => $img['alt'],
            'ord' => $i,
            'state' => 'published',
            'id' => $img['gimg_id'],
        ]);
    }

    // ── 4. Soft-delete remaining 6 gal_seed_cc gallery_images ───
    foreach ($delete as $gid) {
        echo "  -- soft-delete $gid\n";
        if (! $dry) {
            $pdo->prepare("UPDATE gallery_images SET state = 'archived', deleted_at = now(), updated_at = now() WHERE id = :i")->execute(['i' => $gid]);
        }
    }

    // ── 5. Update gal_seed_cc cover to first image ────────────────
    $firstCma = $images[0]['cma_id'];
    printf("  gal_seed_cc cover_image_file_id -> %s\n", $firstCma);
    if (! $dry) {
        $pdo->prepare("UPDATE galleries SET cover_image_file_id = :cma, updated_at = now() WHERE id = :g")->execute(['cma' => $firstCma, 'g' => CC_GALLERY_ID]);
    }

    if ($dry) {
        $pdo->rollBack();
        echo "== DRY RUN complete (rolled back) ==\n";
    } else {
        $pdo->commit();
        echo "== COMMIT OK ==\n";
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'FAILED: '.$e->getMessage()."\n");
    exit(1);
}