<?php
/**
 * Repair the malformed hero IDs created by the earlier seed run.
 *
 * EntityId format:  {entity_type}_{26-char UPPERCASE ULID}
 *   e.g.  file_asset_01ARZ3NDEKTSV4RRFFQ69G5FAV
 *         cms_media_01ARZ3NDEKTSV4RRFFQ69G5FAV
 * The prior run wrote 'cms_hero_6e28153d9aae' / 'fa_hero_...' which fail
 * EntityId::fromString(). This rewrites those file_asset + cms_media_asset rows
 * to valid IDs (via App\Shared\Support\UlidGenerator) and re-points every
 * consumer (hero_banners, galleries, gallery_images, campaigns, events).
 *
 * The cms_media_assets.file_asset_id -> file_assets.id FK is made DEFERRABLE so
 * the in-place PK rewrites validate atomically at COMMIT (no transient dangling
 * reference). Idempotent: no-op if no malformed rows exist.
 *
 * Run: docker exec temple-trust-worker php /app/fix_hero_ids.php
 */

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Shared\Support\UlidGenerator;

$env = file('/app/.env'); $url = '';
foreach ($env as $l) { $l = trim($l); if (str_starts_with($l, 'DATABASE_URL=')) { $url = trim(substr($l, 13)); break; } }
$u = parse_url($url);
$db = ltrim($u['path'] ?? '/neondb', '/');
$sslmode = 'require';
if (isset($u['query'])) { parse_str($u['query'], $q); if (isset($q['sslmode'])) $sslmode = $q['sslmode']; }
$dsn = sprintf("pgsql:host=%s;port=%d;dbname=%s;sslmode=%s", $u['host'], $u['port'] ?? 5432, $db, $sslmode);
$pdo = new PDO($dsn, $u['user'], $u['pass']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$bad = $pdo->query("SELECT id, original_filename FROM file_assets WHERE id LIKE 'fa\\_hero\\_%' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
if (!$bad) {
    echo "No malformed hero rows found — nothing to do.\n";
    exit(0);
}

// Make the self-referential FK deferrable so PK rewrites validate at COMMIT.
$pdo->exec('ALTER TABLE cms_media_assets ALTER CONSTRAINT cms_media_assets_file_asset_id_fkey DEFERRABLE INITIALLY DEFERRED');

echo 'Repairing ' . count($bad) . " malformed hero file_asset id(s)...\n";
$pdo->beginTransaction();
$pdo->exec('SET CONSTRAINTS ALL DEFERRED');

foreach ($bad as $row) {
    $oldFa = $row['id'];
    $newFa = 'file_asset_' . UlidGenerator::generate();

    $cma = $pdo->prepare('SELECT id FROM cms_media_assets WHERE file_asset_id = ?');
    $cma->execute([$oldFa]);
    $cmaRow = $cma->fetch(PDO::FETCH_ASSOC);
    $oldCma = $cmaRow['id'] ?? null;
    $newCma = $oldCma !== null ? 'cms_media_' . UlidGenerator::generate() : null;

    if ($oldCma !== null) {
        $cap = function (string $sql, array $p) use ($pdo): array {
            $st = $pdo->prepare($sql); $st->execute($p); return $st->fetchAll(PDO::FETCH_ASSOC);
        };
        $heroRows    = $cap('SELECT id FROM hero_banners WHERE image_file_id = ?', [$oldCma]);
        $heroMobRows = $cap('SELECT id FROM hero_banners WHERE mobile_image_file_id = ?', [$oldCma]);
        $campRows    = $cap('SELECT id FROM campaigns WHERE cover_image_file_id = ?', [$oldCma]);
        $eventRows   = $cap('SELECT id FROM events WHERE banner_file_id = ?', [$oldCma]);
        $galRows     = $cap('SELECT id FROM galleries WHERE cover_image_file_id = ?', [$oldCma]);
        $giRows      = $cap('SELECT id FROM gallery_images WHERE file_asset_id = ?', [$oldCma]);

        foreach ([
            ['UPDATE hero_banners SET image_file_id = NULL WHERE id = ?', $heroRows],
            ['UPDATE hero_banners SET mobile_image_file_id = NULL WHERE id = ?', $heroMobRows],
            ['UPDATE campaigns SET cover_image_file_id = NULL WHERE id = ?', $campRows],
            ['UPDATE events SET banner_file_id = NULL WHERE id = ?', $eventRows],
            ['UPDATE galleries SET cover_image_file_id = NULL WHERE id = ?', $galRows],
            ['UPDATE gallery_images SET file_asset_id = NULL WHERE id = ?', $giRows],
        ] as [$sql, $rows]) {
            foreach ($rows as $r) { $pdo->prepare($sql)->execute([$r['id']]); }
        }
    }

    // in-place PK rewrites (deferred FK -> validated at COMMIT)
    $pdo->prepare('UPDATE file_assets SET id = ? WHERE id = ?')->execute([$newFa, $oldFa]);
    if ($oldCma !== null) {
        $pdo->prepare('UPDATE cms_media_assets SET file_asset_id = ? WHERE id = ?')->execute([$newFa, $oldCma]);
        $pdo->prepare('UPDATE cms_media_assets SET id = ? WHERE id = ?')->execute([$newCma, $oldCma]);

        foreach ($heroRows as $r)    { $pdo->prepare('UPDATE hero_banners SET image_file_id = ? WHERE id = ?')->execute([$newCma, $r['id']]); }
        foreach ($heroMobRows as $r) { $pdo->prepare('UPDATE hero_banners SET mobile_image_file_id = ? WHERE id = ?')->execute([$newCma, $r['id']]); }
        foreach ($campRows as $r)    { $pdo->prepare('UPDATE campaigns SET cover_image_file_id = ? WHERE id = ?')->execute([$newCma, $r['id']]); }
        foreach ($eventRows as $r)   { $pdo->prepare('UPDATE events SET banner_file_id = ? WHERE id = ?')->execute([$newCma, $r['id']]); }
        foreach ($galRows as $r)     { $pdo->prepare('UPDATE galleries SET cover_image_file_id = ? WHERE id = ?')->execute([$newCma, $r['id']]); }
        foreach ($giRows as $r)      { $pdo->prepare('UPDATE gallery_images SET file_asset_id = ? WHERE id = ?')->execute([$newCma, $r['id']]); }

        printf("  %s -> %s\n", $oldFa, $newFa);
        printf("  %s -> %s\n", $oldCma, $newCma);
    }
}
$pdo->commit();

echo "\nDONE — hero ids rewritten to valid EntityId format.\n";
