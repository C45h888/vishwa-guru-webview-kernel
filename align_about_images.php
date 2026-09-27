<?php

declare(strict_types=1);

/**
 * About-page image DB alignment — seed the three About slots the user
 * has approved so the visual plane can be audited.
 *
 * Model: `/media/{id}` resolves `cms_media_assets.id` → `file_assets`
 * (storage_disk + storage_path). This script creates dedicated
 * file_asset + cms_media_asset pairs per slot (per the handoff's
 * "dedicated file_assets per slot avoids coupling" doctrine) and wires
 * the consumer rows to the new cms_media ids.
 *
 * Slots (as specified by the trust):
 *   trustees[0].photo_file_id    ("Sri Ram Ram Das Guruji")
 *     -> canonical/trustee-photo.jpg
 *        -> new content_block_image, purpose: trustee_portrait
 *   story.image_file_id          ("Schools running today, the next chapter
 *                                  being prepared")
 *     -> canonical/journal-community-06-booklets.jpg
 *        -> new content_block_image, purpose: story_section_image
 *   hero banner for /about       ("About the Trust" page H1)
 *     -> canonical/guru.png
 *        -> new hero_desktop on a new hero_banner row attached to the
 *           about static_page via hero_banner_pages
 *
 * Idempotent: every INSERT/UPDATE is keyed by explicit id and sets
 * absolute content; re-running is a no-op once the seed has landed.
 *
 * Run:  docker exec temple-trust-app php /app/align_about_images.php
 * Dry:  docker exec temple-trust-app php /app/align_about_images.php --dry-run
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
const ABOUT_PAGE_ID = 'static_page_0001N6CAYNHGKPWRF7GDZ074RA';

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

/** Read or mint a ULID for a new row. */
function mintIfMissing(string $prefix): string
{
    /** @var PDO $pdo */
    $pdo = $GLOBALS['pdo'];
    return $prefix.UlidGenerator::generate();
}

// ── Hard-coded ULIDs (generated once via UlidGenerator::generate()) ────
// Re-running this script with these same ids is safe — ON CONFLICT-style
// re-points below ensure we mutate in place rather than duplicate.
const TRUSTEE_FILE_ASSET_ID = 'file_asset_0001NBJ5QQ9TJR8RKNGBRXY75F';
const TRUSTEE_MEDIA_ID      = 'cms_media_0001NBJ5QQQXJ2QKF7QFKET7QG';

const STORY_FILE_ASSET_ID   = 'file_asset_0001NBJ5QQ20TRS7AVQNKJKDSH';
const STORY_MEDIA_ID        = 'cms_media_0001NBJ5QQ49R33M3HB012028X';

const HERO_FILE_ASSET_ID    = 'file_asset_0001NBJ5QQJ92B4Q6DXY8816JH';
const HERO_MEDIA_ID         = 'cms_media_0001NBJ5QQEFVGTP67YM4JDGGX';
const HERO_BANNER_ID        = 'hero_banner_0001NBJ5QQG640NP41FB7Z3DPQ';

const VALUES_FILE_ASSET_ID  = 'file_asset_0001NBJ6ZZECAQE91BHSB3ZC4M';
const VALUES_MEDIA_ID       = 'cms_media_0001NBJ6ZZQ10TM5QA1YJMJXEW';

const TRUSTEE_FILE  = 'trustee-photo.jpg';
const STORY_FILE    = 'journal-community-06-booklets.jpg';
const HERO_FILE     = 'guru.png';
const VALUES_FILE   = 'home-page-header.png.jpg';

const TRUSTEE_ALT = 'Sri Ram Ram Das Guruji — Spiritual Leader of VSRSMS';
const STORY_ALT   = 'Children and community members gathered at the temple';
const HERO_ALT    = 'Sri Ram Ram Das Guruji — the trust\u2019s spiritual leader';
const VALUES_ALT  = 'The trust campus at dawn in Mysore';

// ── Seeds definition ───────────────────────────────────────────────────
// Each entry: [file_asset_id, cms_media_id, canonical filename,
//              media_type, owner_type, alt_text, owner_id_for_owner_type]
$seeds = [
    'trustee' => [
        TRUSTEE_FILE_ASSET_ID, TRUSTEE_MEDIA_ID, TRUSTEE_FILE,
        'content_block_image', 'static_page_attachment',
        TRUSTEE_ALT, ABOUT_PAGE_ID,
    ],
    'story' => [
        STORY_FILE_ASSET_ID, STORY_MEDIA_ID, STORY_FILE,
        'content_block_image', 'static_page_attachment',
        STORY_ALT, ABOUT_PAGE_ID,
    ],
    'hero_about' => [
        HERO_FILE_ASSET_ID, HERO_MEDIA_ID, HERO_FILE,
        'hero_desktop', 'static_page_attachment',
        HERO_ALT, ABOUT_PAGE_ID,
    ],
    'values' => [
        VALUES_FILE_ASSET_ID, VALUES_MEDIA_ID, VALUES_FILE,
        'content_block_image', 'static_page_attachment',
        VALUES_ALT, ABOUT_PAGE_ID,
    ],
];

echo $dry
    ? "== DRY RUN (no writes) — about-page image alignment ==\n"
    : "== APPLYING about-page image alignment ==\n";

$pdo->beginTransaction();

try {
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

    foreach ($seeds as $slot => [$faId, $cmaId, $file, $mediaType, $ownerType, $alt, $ownerId]) {
        $m = fileMeta($file);

        echo sprintf(
            "  seed   %-8s  fa=%-26s  cms=%-26s  -> %s (%dx%d, %dB)\n",
            $slot, $faId, $cmaId, $file,
            $m['w'] ?? 0, $m['h'] ?? 0, $m['bytes']
        );

        if (! $dry) {
            $insertFa->execute([
                'id' => $faId,
                'owner_type' => $ownerType,
                'owner_id' => $ownerId,
                'orig' => $file,
                'disk' => 'public',
                'path' => STORAGE_PREFIX.$file,
                'mime' => $m['mime'],
                'bytes' => $m['bytes'],
                'sha' => $m['sha'],
                'purpose' => $mediaType,
                'metadata' => json_encode(
                    ['aligned_by' => 'align_about_images.php', 'slot' => $slot],
                    JSON_THROW_ON_ERROR
                ),
            ]);
            $insertCma->execute([
                'id' => $cmaId,
                'file_asset_id' => $faId,
                'media_type' => $mediaType,
                'state' => 'published',
                'alt' => $alt,
                'w' => $m['w'],
                'h' => $m['h'],
            ]);
        }
    }

    // ── Hero banner: create new hero_banner for the about page H1 ─────
    if (! $dry) {
        $hbInsert = $pdo->prepare(
            'INSERT INTO hero_banners
                (id, title, subtitle, cta_label, cta_url, image_file_id,
                 mobile_image_file_id, state, display_order, created_at, updated_at)
             VALUES
                (:id, :title, :subtitle, :cta_label, :cta_url, :image_file_id,
                 :mobile_image_file_id, :state, :display_order, now(), now())
             ON CONFLICT (id) DO UPDATE SET
                title = EXCLUDED.title,
                subtitle = EXCLUDED.subtitle,
                cta_label = EXCLUDED.cta_label,
                cta_url = EXCLUDED.cta_url,
                image_file_id = EXCLUDED.image_file_id,
                mobile_image_file_id = EXCLUDED.mobile_image_file_id,
                state = EXCLUDED.state,
                display_order = EXCLUDED.display_order,
                updated_at = now()'
        );
        $hbInsert->execute([
            'id' => HERO_BANNER_ID,
            'title' => 'About the Trust',
            'subtitle' => 'Service, care, devotion, and purpose',
            'cta_label' => null,
            'cta_url' => null,
            'image_file_id' => HERO_MEDIA_ID,
            'mobile_image_file_id' => null,
            'state' => 'published',
            'display_order' => 1,
        ]);

        // Attach the new hero_banner to the about static_page.
        // hero_banner_pages PK is (hero_banner_id, static_page_id).
        $hbpInsert = $pdo->prepare(
            'INSERT INTO hero_banner_pages
                (hero_banner_id, static_page_id, display_order, created_at)
             VALUES
                (:bid, :pid, :order, now())
             ON CONFLICT (hero_banner_id, static_page_id) DO UPDATE SET
                display_order = EXCLUDED.display_order'
        );
        $hbpInsert->execute([
            'bid' => HERO_BANNER_ID,
            'pid' => ABOUT_PAGE_ID,
            'order' => 1,
        ]);

        echo sprintf(
            "  attach hero_banner_pages  %s -> %s (order=1)\n",
            HERO_BANNER_ID, ABOUT_PAGE_ID
        );
    } else {
        echo sprintf(
            "  [dry] would create hero_banner %s and attach to %s (order=1)\n",
            HERO_BANNER_ID, ABOUT_PAGE_ID
        );
    }

    // ── Update static_pages.about_page_content to wire the new slots ──
    $pageRow = $pdo->prepare(
        'SELECT about_page_content FROM static_pages WHERE id = :i'
    );
    $pageRow->execute(['i' => ABOUT_PAGE_ID]);
    $raw = $pageRow->fetchColumn();
    $content = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);

    if (! is_array($content)) {
        throw new RuntimeException(
            'about_page_content must decode to an array'
        );
    }

    // 1. story.image_file_id
    $storyBefore = $content['story']['image_file_id'] ?? null;
    $content['story']['image_file_id'] = STORY_MEDIA_ID;
    echo sprintf(
        "  story.image_file_id: %s -> %s\n",
        $storyBefore ?? 'null', STORY_MEDIA_ID
    );

    // 2. trustees[0].photo_file_id (Sri Ram Ram Das Guruji)
    $trusteeBefore = $content['trustees'][0]['photo_file_id'] ?? null;
    $content['trustees'][0]['photo_file_id'] = TRUSTEE_MEDIA_ID;
    echo sprintf(
        "  trustees[0].photo_file_id: %s -> %s\n",
        $trusteeBefore ?? 'null', TRUSTEE_MEDIA_ID
    );

    // 3. values.image_file_id (Our Values pillar)
    $valuesBefore = $content['values']['image_file_id'] ?? null;
    $content['values']['image_file_id'] = VALUES_MEDIA_ID;
    echo sprintf(
        "  values.image_file_id: %s -> %s\n",
        $valuesBefore ?? 'null', VALUES_MEDIA_ID
    );

    if (! $dry) {
        $updPage = $pdo->prepare(
            'UPDATE static_pages
                SET about_page_content = :apc, updated_at = now()
              WHERE id = :i'
        );
        $updPage->execute([
            'apc' => json_encode($content, JSON_THROW_ON_ERROR),
            'i' => ABOUT_PAGE_ID,
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

// ── Invalidate the Redis resolved-page cache for `about` ──────────────
// The about page is cached under cms.page.about.resolved.v2 — same
// shape as the home page cache, just a different slug.
if (! $dry) {
    try {
        $cache = $app->make(App\Cms\Contracts\ResolvedPageCacheContract::class);
        $cache->invalidate(new App\Cms\Domain\ValueObjects\PageSlug('about'));
        echo "  cache invalidated: cms.page.about.resolved\n";
    } catch (Throwable $e) {
        fwrite(STDERR, 'Cache invalidation skipped: '.$e->getMessage()."\n");
    }
}