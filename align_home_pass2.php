<?php

declare(strict_types=1);

/**
 * Home-page alignment pass 2 — repoint story image + pillar card 1 & 2.
 *
 * Targets:
 *   story section        -> canonical/kids-event.png
 *   pillar card 1        -> canonical/guru.png
 *   pillar card 2        -> canonical/image-single-girl-dance.png
 *   pillar card 3        -> copy only (image unchanged)
 *
 * Idempotent: fixed file_asset ids + absolute-value updates.
 *
 * Run:  docker exec temple-trust-app php /app/align_home_pass2.php
 * Dry:  docker exec temple-trust-app php /app/align_home_pass2.php --dry-run
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

$repoints = [
    // story section image
    [
        'fa_id'  => 'file_asset_01KYVWCPDJTKFRHZATPE2GD0MK',
        'cma_id' => 'cms_media_01KYVWCPDJ66G1R28DWQNW2A0V',
        'file'   => 'kids-event.png',
        'alt'    => 'Children at the trust\u2019s schools — daily life and care',
    ],
    // pillar card 1
    [
        'fa_id'  => 'file_asset_0001NBFNN94E5X2XK4YT8NH6PW',
        'cma_id' => 'cms_media_0001NBFNN93M06R93C2KX9YGJV',
        'file'   => 'guru.png',
        'alt'    => 'Guru blessing — years of dedication to the right causes',
    ],
    // pillar card 2 / program annadanam
    [
        'fa_id'  => 'file_asset_01KYSWKY6KS4Q86DCT7KE94CDB',
        'cma_id' => 'cms_media_01KYSWKY6KH6F8P1QQ3VCMCTBQ',
        'file'   => 'image-single-girl-dance.png',
        'alt'    => 'Children dancing — harbouring and grace',
    ],
];

echo $dry ? "== DRY RUN (no writes) ==\n" : "== APPLYING home-page pass 2 ==\n";

$pdo->beginTransaction();

try {
    $updateFa = $pdo->prepare(
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
    $updateCma = $pdo->prepare(
        'UPDATE cms_media_assets
            SET width = :w, height = :h, alt_text = :alt, updated_at = now()
          WHERE id = :id'
    );

    foreach ($repoints as $r) {
        $m = fileMeta($r['file']);
        echo sprintf(
            "  %-46s -> %s (%dx%d, %d B)\n",
            $r['fa_id'],
            $r['file'],
            $m['w'] ?? 0,
            $m['h'] ?? 0,
            $m['bytes'],
        );
        if (! $dry) {
            $updateFa->execute([
                'disk' => 'public',
                'path' => STORAGE_PREFIX.$r['file'],
                'mime' => $m['mime'],
                'bytes' => $m['bytes'],
                'sha' => $m['sha'],
                'orig' => $r['file'],
                'id' => $r['fa_id'],
            ]);
            $updateCma->execute([
                'w' => $m['w'],
                'h' => $m['h'],
                'alt' => $r['alt'],
                'id' => $r['cma_id'],
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

// ── Invalidate Redis cache ─────────────────────────────────────────
if (! $dry) {
    try {
        $cache = $app->make(App\Cms\Contracts\ResolvedPageCacheContract::class);
        $cache->invalidate(new App\Cms\Domain\ValueObjects\PageSlug('home'));
        echo "  cache invalidated: cms.page.home.resolved\n";
    } catch (Throwable $e) {
        fwrite(STDERR, 'Cache invalidation skipped: '.$e->getMessage()."\n");
    }
}