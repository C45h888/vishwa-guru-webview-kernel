<?php

declare(strict_types=1);

/**
 * Campaigns-page image DB alignment.
 *
 * Seeds / aligns the three campaigns-page editorial pillar images as
 * first-class public media: one `file_assets` row (canonical path) plus
 * one published `cms_media_assets` row each, using the fixed ids declared
 * in `config/campaigns.php`. The campaigns IndexController hydrates the
 * page payload from those ids; `/media/{id}` serves the canonical bytes.
 *
 * Targets:
 *   pillar 1 (Sustain the schools: food, water, events)
 *     -> canonical/kids-event.png
 *   pillar 2 (Acquire the land for the new campus)
 *     -> canonical/LAND.jpg
 *   pillar 3 (Build the Gaushala, temple, and cow care)
 *     -> canonical/GAUSHALA.jpg (illustrative future-campus concept)
 *
 * Idempotent: fixed ids + absolute-value updates.
 *
 * Run:  docker exec temple-trust-app php /app/align_campaign_images.php
 * Dry:  docker exec temple-trust-app php /app/align_campaign_images.php --dry-run
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
const OWNER_ID = 'campaigns_pillars';

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

$pillars = config('campaigns.pillars', []);

echo $dry ? "== DRY RUN (no writes) ==\n" : "== APPLYING campaigns-page image alignment ==\n";

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

    foreach ($pillars as $i => $pillar) {
        $m = fileMeta($pillar['canonical']);
        echo sprintf(
            "  pillar %d  %-46s -> %s (%dx%d)\n",
            $i + 1,
            $pillar['cms_media_id'],
            $pillar['canonical'],
            $m['w'] ?? 0,
            $m['h'] ?? 0,
        );
        if (! $dry) {
            $upsertFa->execute([
                'id' => $pillar['file_asset_id'],
                'owner_type' => 'static_page_attachment',
                'owner_id' => OWNER_ID,
                'orig' => $pillar['canonical'],
                'disk' => 'public',
                'path' => STORAGE_PREFIX.$pillar['canonical'],
                'mime' => $m['mime'],
                'bytes' => $m['bytes'],
                'sha' => $m['sha'],
                'purpose' => 'content_block_image',
                'metadata' => json_encode(['aligned_by' => 'align_campaign_images.php'], JSON_THROW_ON_ERROR),
            ]);
            $upsertCma->execute([
                'id' => $pillar['cms_media_id'],
                'file_asset_id' => $pillar['file_asset_id'],
                'media_type' => 'content_block_image',
                'state' => 'published',
                'alt' => $pillar['alt'],
                'w' => $m['w'],
                'h' => $m['h'],
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
