<?php

declare(strict_types=1);

/**
 * Home-page alignment pass 4 — decouple programs[1] (Annadanam) image
 * from pillar card 2 image.
 *
 * Mirrors the Pass 1 `story.pillar_image_file_id` decoupling:
 *   programs[1].image_file_id        = new villagers.jpg asset
 *                                      (Annadanam program card)
 *   programs[1].pillar_image_file_id = existing dedicated pillar
 *                                      card 2 asset (image-single-
 *                                      girl-dance.png from Pass 3)
 *
 * Targets:
 *   programs[1] (annadanam) -> canonical/journal-community-05-villagers.jpg
 *   pillar card 2            -> canonical/image-single-girl-dance.png
 *                              (unchanged from Pass 3, just re-wired
 *                               through the new pillar_image slot)
 *
 * Architecture (Kernel.md update):
 *   Cms/Kernel.md §"Home-page image slots (decoupled)" now also
 *   covers programs[i].pillar_image_file_id, not just story.
 *
 * Idempotent: ON CONFLICT DO UPDATE on file_asset + cms_media_asset,
 * then absolute-value UPDATE on static_pages.homepage_content.
 *
 * Run:  docker exec temple-trust-app php /app/align_home_pass4.php
 * Dry:  docker exec temple-trust-app php /app/align_home_pass4.php --dry-run
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

// ── Hard-coded ULIDs ───────────────────────────────────────────────────
const VILLAGERS_FILE_ASSET_ID = 'file_asset_0001NBJA0HY739ZKFYT6HMHJHS';
const VILLAGERS_MEDIA_ID      = 'cms_media_0001NBJA0HB82E27VD66ZVDJ6H';

// Existing pillar-card-2 asset (created in Pass 3 / pillar2 ensure).
// We re-reference it (not re-create it) for programs[1].pillar_image_file_id.
const PILLAR2_MEDIA_ID = 'cms_media_0001NBJ9EXXEPWDA7WN5YXNDH6';

const VILLAGERS_FILE = 'journal-community-05-villagers.jpg';
const VILLAGERS_ALT  = 'Daily annadanam — community gathering with villagers';

echo $dry
    ? "== DRY RUN (no writes) — programs[1] ↔ pillar 2 decoupling ==\n"
    : "== APPLYING programs[1] ↔ pillar 2 decoupling ==\n";

$pdo->beginTransaction();

try {
    // 1. Create new file_asset + cms_media_asset for villagers.jpg
    //    (the Annadanam program card image).
    $m = fileMeta(VILLAGERS_FILE);

    echo sprintf(
        "  seed  villagers  fa=%s  cms=%s  -> %s (%dx%d, %dB)\n",
        VILLAGERS_FILE_ASSET_ID,
        VILLAGERS_MEDIA_ID,
        VILLAGERS_FILE,
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
            'id' => VILLAGERS_FILE_ASSET_ID,
            'owner_type' => 'static_page_attachment',
            'owner_id' => HOME_PAGE_ID,
            'orig' => VILLAGERS_FILE,
            'disk' => 'public',
            'path' => STORAGE_PREFIX.VILLAGERS_FILE,
            'mime' => $m['mime'],
            'bytes' => $m['bytes'],
            'sha' => $m['sha'],
            'purpose' => 'content_block_image',
            'metadata' => json_encode(
                ['aligned_by' => 'align_home_pass4.php', 'slot' => 'programs_annadanam'],
                JSON_THROW_ON_ERROR
            ),
        ]);

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
            'id' => VILLAGERS_MEDIA_ID,
            'file_asset_id' => VILLAGERS_FILE_ASSET_ID,
            'media_type' => 'content_block_image',
            'state' => 'published',
            'alt' => VILLAGERS_ALT,
            'w' => $m['w'],
            'h' => $m['h'],
        ]);
    }

    // 2. Update programs[1] in homepage_content:
    //    - image_file_id        -> villagers asset
    //    - pillar_image_file_id -> pillar card 2 asset
    $pageRow = $pdo->prepare(
        'SELECT homepage_content FROM static_pages WHERE id = :i'
    );
    $pageRow->execute(['i' => HOME_PAGE_ID]);
    $raw = $pageRow->fetchColumn();
    $content = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);

    if (! is_array($content) || ! isset($content['programs'][1])) {
        throw new RuntimeException(
            'homepage_content.programs[1] missing or invalid'
        );
    }

    $imageBefore = $content['programs'][1]['image_file_id'] ?? null;
    $content['programs'][1]['image_file_id'] = VILLAGERS_MEDIA_ID;
    echo sprintf(
        "  programs[1].image_file_id: %s -> %s\n",
        $imageBefore ?? 'null', VILLAGERS_MEDIA_ID
    );

    $pillarBefore = $content['programs'][1]['pillar_image_file_id'] ?? null;
    $content['programs'][1]['pillar_image_file_id'] = PILLAR2_MEDIA_ID;
    echo sprintf(
        "  programs[1].pillar_image_file_id: %s -> %s\n",
        $pillarBefore ?? 'null', PILLAR2_MEDIA_ID
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

// ── Invalidate Redis cache ─────────────────────────────────────────────
if (! $dry) {
    try {
        $cache = $app->make(App\Cms\Contracts\ResolvedPageCacheContract::class);
        $cache->invalidate(new App\Cms\Domain\ValueObjects\PageSlug('home'));
        echo "  cache invalidated: cms.page.home.resolved\n";
    } catch (Throwable $e) {
        fwrite(STDERR, 'Cache invalidation skipped: '.$e->getMessage()."\n");
    }
}