<?php

declare(strict_types=1);

/**
 * Home-page image DB alignment — align the DB surface to the canonical
 * filesystem state for the home page, page-by-page pass #1.
 *
 * Model: `/media/{id}` resolves `cms_media_assets.id` -> `file_assets`
 * (storage_disk + storage_path). This script makes every home-page
 * consumer's underlying `file_assets.storage_path` point at a real file
 * under `storage/app/public/cms-media-upscaled/canonical/`, and adds the
 * new decoupled `story.pillar_image_file_id` slot.
 *
 * Targets (as specified by the trust):
 *   hero 1              -> canonical/pooje-ai-strict.png
 *   hero 2              -> canonical/dance-girls2.png
 *   hero 3              -> canonical/lens-flare.png
 *   story section       -> canonical/GAUSHALA.jpg
 *   pillar card 1  (NEW) -> canonical/children.jpg
 *   program pooja       -> canonical/journal-community-02-ceremony-with-priest.jpg
 *   program annadanam   -> canonical/journal-community-05-villagers.jpg
 *   program temple_care -> canonical/GAUSHALA.jpg
 *   gallery sr cover    -> canonical/journal-pooja-01-sandhyavandanam.jpg
 *
 * Idempotent: every UPDATE is keyed by explicit id and sets absolute
 * values; the new pillar asset is created only when its id is absent.
 *
 * Run:  docker exec temple-trust-app php /app/align_home_images.php
 * Dry:  docker exec temple-trust-app php /app/align_home_images.php --dry-run
 */

$dry = in_array('--dry-run', $argv ?? [], true);

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Shared\Support\UlidGenerator;

// ── DB: same connection path the other seed scripts use ────────────────
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
const HOME_PAGE_ID = 'static_page_0001N6BR4YWB4RECDQ8H03DTYR';

/** Read canonical metadata (bytes/mime/sha/dims). */
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

// ── 1. Repoint existing slot file_assets to the canonical file ─────────
//
// [file_asset_id, cms_media_id, canonical filename, new alt_text|null]
$repoints = [
    // hero slideshow
    ['file_asset_0001NB5ED7J3SKTN37KP5DK6PH', 'cms_media_0001NB5ED82SVZKHJJ8S2909EZ', 'pooje-ai-strict.png', 'Temple celebration — pooje at the trust'],
    ['file_asset_0001NB5EDA86WHKD6QAYRZNGZ2', 'cms_media_0001NB5EDA8VD7QRA28DK7ZG36', 'dance-girls2.png', 'Children performing Bharatanatyam'],
    ['file_asset_0001NB5EDCDTCXEFACMWMQEQ43', 'cms_media_0001NB5EDCCCCN92SAXYC4R83C', 'lens-flare.png', 'Temple grounds — light and devotion'],
    // trust-story section image (also the pillar fallback)
    ['file_asset_01KYVWCPDJTKFRHZATPE2GD0MK', 'cms_media_01KYVWCPDJ66G1R28DWQNW2A0V', 'GAUSHALA.jpg', 'The proposed Gaushala and temple campus'],
    // programs
    ['file_asset_01KYVWCPDJG0PBYP3550VGT7H2', 'cms_media_01KYVWCPDJXXTK34VDWPSH7E8S', 'journal-community-02-ceremony-with-priest.jpg', 'Free schooling — ceremony with the priest'],
    ['file_asset_01KYSWKY6KS4Q86DCT7KE94CDB', 'cms_media_01KYSWKY6KH6F8P1QQ3VCMCTBQ', 'journal-community-05-villagers.jpg', 'Daily annadanam — community gathering'],
    ['file_asset_01KYVWCPDJKC8ZA7QQP248JMTA', 'cms_media_01KYVWCPDJ9KARGCQTYEM3NMM3', 'GAUSHALA.jpg', 'Land and campus — Gaushala and temple'],
    // gallery cover (pillar card 3)
    ['fa_gr_seed_sr_01', 'cms_gr_seed_sr_01', 'journal-pooja-01-sandhyavandanam.jpg', 'Sandhyavandanam'],
];

// ── 2. New decoupled pillar-card-1 asset (children.jpg) ────────────────
define('PILLAR_CHILD_FILE', 'children.jpg');
const PILLAR_MEDIA_TYPE = 'content_block_image';
const PILLAR_ALT = 'Children at the trust\u{2019}s schools';

// Stable/idempotent pillar ids: reuse the home page's existing children.jpg
// asset if a prior run created it; otherwise mint fresh ULIDs.
$pillarFileAssetId = (string) ($pdo->query(
    "SELECT id FROM file_assets WHERE storage_path = '".STORAGE_PREFIX.PILLAR_CHILD_FILE."'".
    " AND owner_id = '".HOME_PAGE_ID."' LIMIT 1"
)->fetchColumn() ?: '');
if ($pillarFileAssetId === '') {
    $pillarFileAssetId = 'file_asset_'.UlidGenerator::generate();
}

$pillarMediaId = (string) ($pdo->query(
    "SELECT id FROM cms_media_assets WHERE file_asset_id = '".$pillarFileAssetId."' LIMIT 1"
)->fetchColumn() ?: '');
if ($pillarMediaId === '') {
    $pillarMediaId = 'cms_media_'.UlidGenerator::generate();
}

echo $dry ? "== DRY RUN (no writes) ==\n" : "== APPLYING home-page image alignment ==\n";

$pdo->beginTransaction();

try {
    // 2a. Repoint existing assets.
    $update = $pdo->prepare(
        'UPDATE file_assets
            SET storage_disk = :disk,
                storage_path = :path,
                mime_type = :mime,
                file_size_bytes = :bytes,
                file_hash_sha256 = :sha,
                original_filename = :orig,
                updated_at = now()
          WHERE id = :id'
    );
    $updateMedia = $pdo->prepare(
        'UPDATE cms_media_assets
            SET width = :w, height = :h, alt_text = :alt, updated_at = now()
          WHERE id = :id'
    );

    foreach ($repoints as [$faId, $cmaId, $file, $alt]) {
        $m = fileMeta($file);
        echo sprintf("  repoint %-46s -> %s (%dx%d, %d B)\n", $faId, $file, $m['w'] ?? 0, $m['h'] ?? 0, $m['bytes']);
        if (! $dry) {
            $update->execute([
                'disk' => 'public',
                'path' => STORAGE_PREFIX.$file,
                'mime' => $m['mime'],
                'bytes' => $m['bytes'],
                'sha' => $m['sha'],
                'orig' => $file,
                'id' => $faId,
            ]);
            $updateMedia->execute([
                'w' => $m['w'],
                'h' => $m['h'],
                'alt' => $alt,
                'id' => $cmaId,
            ]);
        }
    }

    // 2b. New pillar asset.
    $pm = fileMeta(PILLAR_CHILD_FILE);
    $existing = $pdo->prepare('SELECT id FROM file_assets WHERE id = :i');
    $existing->execute(['i' => $pillarFileAssetId]);
    $pillarExists = (bool) $existing->fetchColumn();

    if (! $pillarExists) {
        echo sprintf("  create  %-46s -> %s (pillar card 1)\n", $pillarFileAssetId, PILLAR_CHILD_FILE);
        if (! $dry) {
            $insertFa = $pdo->prepare(
                'INSERT INTO file_assets
                    (id, owner_type, owner_id, original_filename, storage_disk, storage_path,
                     mime_type, file_size_bytes, file_hash_sha256, purpose, is_public, is_archived,
                     metadata, uploaded_at, created_at, updated_at)
                 VALUES
                    (:id, :owner_type, :owner_id, :orig, :disk, :path,
                     :mime, :bytes, :sha, :purpose, true, false,
                     :metadata, now(), now(), now())'
            );
            $insertFa->execute([
                'id' => $pillarFileAssetId,
                'owner_type' => 'static_page_attachment',
                'owner_id' => HOME_PAGE_ID,
                'orig' => PILLAR_CHILD_FILE,
                'disk' => 'public',
                'path' => STORAGE_PREFIX.PILLAR_CHILD_FILE,
                'mime' => $pm['mime'],
                'bytes' => $pm['bytes'],
                'sha' => $pm['sha'],
                'purpose' => 'content_block_image',
                'metadata' => json_encode(['aligned_by' => 'align_home_images.php'], JSON_THROW_ON_ERROR),
            ]);

            $insertCma = $pdo->prepare(
                'INSERT INTO cms_media_assets
                    (id, file_asset_id, media_type, state, alt_text, width, height,
                     published_at, created_at, updated_at)
                 VALUES
                    (:id, :file_asset_id, :media_type, :state, :alt, :w, :h,
                     now(), now(), now())'
            );
            $insertCma->execute([
                'id' => $pillarMediaId,
                'file_asset_id' => $pillarFileAssetId,
                'media_type' => PILLAR_MEDIA_TYPE,
                'state' => 'published',
                'alt' => PILLAR_ALT,
                'w' => $pm['w'],
                'h' => $pm['h'],
            ]);
        }
    } else {
        // Asset already exists from a prior run; reuse its media id.
        $found = $pdo->prepare('SELECT id FROM cms_media_assets WHERE file_asset_id = :f ORDER BY id LIMIT 1');
        $found->execute(['f' => $pillarFileAssetId]);
        $pillarMediaId = (string) ($found->fetchColumn() ?: $pillarMediaId);
        echo "  pillar asset already present; reusing media id {$pillarMediaId}\n";
    }

    // 2c. Point homepage_content.story.pillar_image_file_id at it.
    $page = $pdo->prepare('SELECT homepage_content FROM static_pages WHERE id = :i');
    $page->execute(['i' => HOME_PAGE_ID]);
    $raw = $page->fetchColumn();
    $content = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
    $current = $content['story']['pillar_image_file_id'] ?? null;

    echo sprintf("  story.pillar_image_file_id: %s -> %s\n", $current ?? 'null', $pillarMediaId);
    if (! $dry && $current !== $pillarMediaId) {
        $content['story']['pillar_image_file_id'] = $pillarMediaId;
        $st = $pdo->prepare('UPDATE static_pages SET homepage_content = :hc, updated_at = now() WHERE id = :i');
        $st->execute([
            'hc' => json_encode($content, JSON_THROW_ON_ERROR),
            'i' => HOME_PAGE_ID,
        ]);
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

// ── 3. Invalidate the Redis resolved-page cache for `home` ─────────────
if (! $dry) {
    try {
        $cache = $app->make(App\Cms\Contracts\ResolvedPageCacheContract::class);
        $cache->invalidate(new App\Cms\Domain\ValueObjects\PageSlug('home'));
        echo "  cache invalidated: cms.page.home.resolved\n";
    } catch (Throwable $e) {
        fwrite(STDERR, 'Cache invalidation skipped: '.$e->getMessage()."\n");
    }
}
