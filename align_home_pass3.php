<?php

declare(strict_types=1);

/**
 * Home-page alignment pass 3 — swap pillar card 1 from guru.png to
 * kalyanam .png.
 *
 * Target:
 *   pillar card 1 (story.pillar_image_file_id, "Years of dedication to
 *                  the right causes")
 *     -> canonical/kalyanam .png (note the space in the filename)
 *
 * Card 2 ("Harbouring and grace…") already points at
 * canonical/image-single-girl-dance.png per Pass 2; no change needed.
 *
 * Doctrine (per handoff): "Creating dedicated file_assets per slot
 * avoids coupling." A new file_asset + cms_media_asset pair is created
 * for the new pillar slot rather than reusing the existing guru.png
 * rows (those remain referenced by the about-page hero banner).
 *
 * Idempotent: every INSERT/UPDATE is keyed by explicit id and sets
 * absolute content; re-runs are no-ops once the swap has landed.
 *
 * Run:  docker exec temple-trust-app php /app/align_home_pass3.php
 * Dry:  docker exec temple-trust-app php /app/align_home_pass3.php --dry-run
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
$dsn = sprintf(
    'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s',
    $u['host'], $u['port'] ?? 5432, $db, $sslmode
);
$pdo = new PDO($dsn, $u['user'], $u['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

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

// ── Hard-coded ULIDs (generated via UlidGenerator::generate()) ────────
const PILLAR1_FILE_ASSET_ID = 'file_asset_0001NBJ8YSP49ZA9QZFC77608Q';
const PILLAR1_MEDIA_ID      = 'cms_media_0001NBJ8YSN3J804ZDCH8P9XK0';

const PILLAR1_FILE  = 'kalyanam .png';
const PILLAR1_ALT   = 'Temple wedding — the kalyanam ceremony';

echo $dry
    ? "== DRY RUN (no writes) — home-page pillar 1 swap ==\n"
    : "== APPLYING home-page pillar 1 swap ==\n";

$pdo->beginTransaction();

try {
    // 1. Create new file_asset row pointing at the canonical kalyanam
    //    image. Owner = static_page_attachment, owner_id = home page —
    //    mirrors the pillar-1 row that align_home_images.php created
    //    for children.jpg in Pass 1.
    $m = fileMeta(PILLAR1_FILE);

    echo sprintf(
        "  seed  pillar1  fa=%s  cms=%s  -> %s (%dx%d, %dB)\n",
        PILLAR1_FILE_ASSET_ID,
        PILLAR1_MEDIA_ID,
        PILLAR1_FILE,
        $m['w'] ?? 0,
        $m['h'] ?? 0,
        $m['bytes']
    );

    if (! $dry) {
        $insertFa = $pdo->prepare(
            'INSERT INTO file_assets
                (id, owner_type, owner_id, original_filename, storage_disk, storage_path,
                 mime_type, file_size_bytes, file_hash_sha256, purpose, is_public,
                 is_archived, metadata, uploaded_at, created_at, updated_at)
             VALUES
                (:id, :owner_type, :owner_id, :orig, :disk, :path,
                 :mime, :bytes, :sha, :purpose, true,
                 false, :metadata, now(), now(), now())
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
                is_public = EXCLUDED.is_public,
                is_archived = EXCLUDED.is_archived,
                metadata = EXCLUDED.metadata,
                updated_at = now()'
        );
        $insertFa->execute([
            'id' => PILLAR1_FILE_ASSET_ID,
            'owner_type' => 'static_page_attachment',
            'owner_id' => HOME_PAGE_ID,
            'orig' => PILLAR1_FILE,
            'disk' => 'public',
            'path' => STORAGE_PREFIX.PILLAR1_FILE,
            'mime' => $m['mime'],
            'bytes' => $m['bytes'],
            'sha' => $m['sha'],
            'purpose' => 'content_block_image',
            'metadata' => json_encode(
                ['aligned_by' => 'align_home_pass3.php', 'slot' => 'pillar_card_1'],
                JSON_THROW_ON_ERROR
            ),
        ]);

        // 2. Create new cms_media_asset row pointing at the new
        //    file_asset. media_type = content_block_image (mirrors the
        //    existing pillar-1 row).
        $insertCma = $pdo->prepare(
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
        $insertCma->execute([
            'id' => PILLAR1_MEDIA_ID,
            'file_asset_id' => PILLAR1_FILE_ASSET_ID,
            'media_type' => 'content_block_image',
            'state' => 'published',
            'alt' => PILLAR1_ALT,
            'w' => $m['w'],
            'h' => $m['h'],
        ]);
    }

    // 3. Update homepage_content.story.pillar_image_file_id to the
    //    new cms_media_id.
    $pageRow = $pdo->prepare(
        'SELECT homepage_content FROM static_pages WHERE id = :i'
    );
    $pageRow->execute(['i' => HOME_PAGE_ID]);
    $raw = $pageRow->fetchColumn();
    $content = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);

    if (! is_array($content)) {
        throw new RuntimeException(
            'homepage_content must decode to an array'
        );
    }

    $previous = $content['story']['pillar_image_file_id'] ?? null;
    $content['story']['pillar_image_file_id'] = PILLAR1_MEDIA_ID;
    echo sprintf(
        "  story.pillar_image_file_id: %s -> %s\n",
        $previous ?? 'null', PILLAR1_MEDIA_ID
    );

    if (! $dry) {
        $upd = $pdo->prepare(
            'UPDATE static_pages
                SET homepage_content = :hc, updated_at = now()
              WHERE id = :i'
        );
        $upd->execute([
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

// ── Invalidate the Redis resolved-page cache for `home` ──────────────
// The home page is cached under cms.page.home.resolved.v2 (see
// RedisResolvedPageCache §KEY_PREFIX/KEY_SUFFIX/SCHEMA_VERSION).
if (! $dry) {
    try {
        $cache = $app->make(App\Cms\Contracts\ResolvedPageCacheContract::class);
        $cache->invalidate(new App\Cms\Domain\ValueObjects\PageSlug('home'));
        echo "  cache invalidated: cms.page.home.resolved\n";
    } catch (Throwable $e) {
        fwrite(STDERR, 'Cache invalidation skipped: '.$e->getMessage()."\n");
    }
}