<?php

declare(strict_types=1);

/**
 * Sacred Rituals gallery image alignment.
 *
 * Replaces the 5 gallery_images of `gal_seed_sr` (Sacred Rituals) with
 * new canonical media assets. Order (display_order 0–4) matches the
 * user's specified sequence.
 *
 *   order 0 -> canonical/journal-festivals-03-ganesh-chaturthi.jpg
 *   order 1 -> canonical/journal-kalyanam-03.jpg
 *   order 2 -> canonical/journal-kumbhabhishekam-01.jpg
 *   order 3 -> canonical/pooje-ai-strict.png
 *   order 4 -> canonical/49f8d3d7-1a57-473b-9978-36fda745bdc0.jpg
 *
 * Each gets a dedicated file_asset + published cms_media_asset pointed at
 * the canonical path. Existing gallery_image ids are reused (updated in
 * place) so no orphan rows are left behind.
 *
 * Idempotent: fixed ids + absolute-value updates.
 *
 * Run:  docker exec temple-trust-app php /app/align_gallery_sr.php
 * Dry:  docker exec temple-trust-app php /app/align_gallery_sr.php --dry-run
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
const GALLERY_ID = 'gal_seed_sr';

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
    [
        'gimg_id'   => 'gimg_seed_sr_01',
        'fa_id'     => 'file_asset_0001NBFZ29SBTCDZ8SXNB2TG87',
        'cma_id'    => 'cms_media_0001NBFZ293JG44GTY8SQ6A0Q5',
        'canonical' => 'journal-festivals-03-ganesh-chaturthi.jpg',
        'alt'       => 'Ganesh Chaturthi \u2014 temple festival celebration',
        'order'     => 0,
    ],
    [
        'gimg_id'   => 'gimg_seed_sr_02',
        'fa_id'     => 'file_asset_0001NBFZ290H21B45BFQ5CT4TG',
        'cma_id'    => 'cms_media_0001NBFZ29CVZ5Y3TX1N9TK3TY',
        'canonical' => 'journal-kalyanam-03.jpg',
        'alt'       => 'Kalyanam \u2014 temple wedding rites and ceremony',
        'order'     => 1,
    ],
    [
        'gimg_id'   => 'gimg_seed_sr_03',
        'fa_id'     => 'file_asset_0001NBFZ29QVCHK7MMMAEY0VA4',
        'cma_id'    => 'cms_media_0001NBFZ29YFDNDWHPQ0VZSXS0',
        'canonical' => 'journal-kumbhabhishekam-01.jpg',
        'alt'       => 'Kumbhabhishekam \u2014 sacred consecration of the temple',
        'order'     => 2,
    ],
    [
        'gimg_id'   => 'gimg_seed_sr_04',
        'fa_id'     => 'file_asset_0001NBFZ29400VT77EG71XDK42',
        'cma_id'    => 'cms_media_0001NBFZ298F4P9AKYAFCJ0WVV',
        'canonical' => 'pooje-ai-strict.png',
        'alt'       => 'Pooje \u2014 devotional worship at the temple',
        'order'     => 3,
    ],
    [
        'gimg_id'   => 'gimg_seed_sr_05',
        'fa_id'     => 'file_asset_0001NBFZ29473EW7FXM7GG9S0A',
        'cma_id'    => 'cms_media_0001NBFZ29TK6YCMDWF147N15V',
        'canonical' => '49f8d3d7-1a57-473b-9978-36fda745bdc0.jpg',
        'alt'       => 'Portrait \u2014 temple spiritual portrait',
        'order'     => 4,
    ],
];

echo $dry ? "== DRY RUN (no writes) ==\n" : "== APPLYING Sacred Rituals gallery images ==\n";

$pdo->beginTransaction();

try {
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

    $updateGimg = $pdo->prepare(
        'UPDATE gallery_images
            SET file_asset_id = :cma, alt_text = :alt, display_order = :ord,
                state = :state, published_at = now(), updated_at = now()
          WHERE id = :id'
    );

    foreach ($images as $img) {
        $m = fileMeta($img['canonical']);
        printf(
            "  %-22s %s (%dx%d, %d B)\n",
            $img['gimg_id'],
            $img['canonical'],
            $m['w'] ?? 0,
            $m['h'] ?? 0,
            $m['bytes'],
        );
        if (! $dry) {
            $upsertFa->execute([
                'id' => $img['fa_id'],
                'owner_type' => 'gallery_image',
                'owner_id' => GALLERY_ID,
                'orig' => $img['canonical'],
                'disk' => 'public',
                'path' => STORAGE_PREFIX.$img['canonical'],
                'mime' => $m['mime'],
                'bytes' => $m['bytes'],
                'sha' => $m['sha'],
                'purpose' => 'gallery_image',
                'metadata' => json_encode(['aligned_by' => 'align_gallery_sr.php'], JSON_THROW_ON_ERROR),
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
                'ord' => $img['order'],
                'state' => 'published',
                'id' => $img['gimg_id'],
            ]);
        }
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