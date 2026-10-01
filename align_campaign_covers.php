<?php

declare(strict_types=1);

/**
 * Campaign cover-image DB alignment.
 *
 * Gives each DB campaign its own distinct canonical cover image:
 *
 *   campaign_01M3V9QP311Z564SCJ9GW47HE7  (Schools and Campus Fund)
 *     -> canonical/a-guru-blessing.png
 *   campaign_0001N6BR8BAAYG80DYW8ABHWX8  (Shiva Temple Construction)
 *     -> canonical/WELFARE.jpg
 *   campaign_01KY7J2AZ60HXMGQCVF5GERGEG  (Gaushala Cow Care Fund)
 *     -> canonical/GAUSHALA.jpg
 *
 * Each gets a dedicated file_asset + published cms_media_asset row.
 * The campaign row's cover_image_file_id is set to the cms_media id.
 *
 * Idempotent: fixed ids + ON CONFLICT / absolute-value UPDATE.
 *
 * Run:  docker exec temple-trust-app php /app/align_campaign_covers.php
 * Dry:  docker exec temple-trust-app php /app/align_campaign_covers.php --dry-run
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

$covers = [
    [
        'campaign_id'    => 'campaign_01M3V9QP311Z564SCJ9GW47HE7',
        'file_asset_id'  => 'file_asset_0001NBFVMN66ZSK0QWSKC9ZRP8',
        'cms_media_id'   => 'cms_media_0001NBFVMNKCJHR69FG4H1492F',
        'canonical'      => 'a-guru-blessing.png',
        'alt'            => 'Guru blessing — the spiritual foundation of the trust',
    ],
    [
        'campaign_id'    => 'campaign_0001N6BR8BAAYG80DYW8ABHWX8',
        'file_asset_id'  => 'file_asset_0001NBFVMNNB3B0Y14EY4HR5GW',
        'cms_media_id'   => 'cms_media_0001NBFVMNFNQ0WENTFNSK5Q3Y',
        'canonical'      => 'WELFARE.jpg',
        'alt'            => 'Community welfare — the trust\u2019s outreach and care',
    ],
    [
        'campaign_id'    => 'campaign_01KY7J2AZ60HXMGQCVF5GERGEG',
        'file_asset_id'  => 'file_asset_0001NBFVMNA471PJFB9A9Q27HQ',
        'cms_media_id'   => 'cms_media_0001NBFVMN3Z8BX8J8J43NDKF2',
        'canonical'      => 'GAUSHALA.jpg',
        'alt'            => 'Gaushala — the proposed cow care campus',
    ],
];

echo $dry ? "== DRY RUN (no writes) ==\n" : "== APPLYING campaign cover images ==\n";

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

    $updateCampaign = $pdo->prepare(
        'UPDATE campaigns SET cover_image_file_id = :cma, updated_at = now() WHERE id = :id'
    );

    foreach ($covers as $c) {
        $m = fileMeta($c['canonical']);
        echo sprintf(
            "  %-44s <- %s (%dx%d, %d B)\n",
            $c['campaign_id'],
            $c['canonical'],
            $m['w'] ?? 0,
            $m['h'] ?? 0,
            $m['bytes'],
        );
        if (! $dry) {
            $upsertFa->execute([
                'id' => $c['file_asset_id'],
                'owner_type' => 'campaign_cover',
                'owner_id' => $c['campaign_id'],
                'orig' => $c['canonical'],
                'disk' => 'public',
                'path' => STORAGE_PREFIX.$c['canonical'],
                'mime' => $m['mime'],
                'bytes' => $m['bytes'],
                'sha' => $m['sha'],
                'purpose' => 'campaign_cover',
                'metadata' => json_encode(['aligned_by' => 'align_campaign_covers.php'], JSON_THROW_ON_ERROR),
            ]);
            $upsertCma->execute([
                'id' => $c['cms_media_id'],
                'file_asset_id' => $c['file_asset_id'],
                'media_type' => 'campaign_cover',
                'state' => 'published',
                'alt' => $c['alt'],
                'w' => $m['w'],
                'h' => $m['h'],
            ]);
            $updateCampaign->execute([
                'cma' => $c['cms_media_id'],
                'id' => $c['campaign_id'],
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