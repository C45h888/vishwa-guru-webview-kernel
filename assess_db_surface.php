<?php
/**
 * READ-ONLY assessment of the DB image surface vs the canonical image state.
 *
 * This is the "assess against the DB surface" step of the independent pass.
 * It does NOT write anything. It reports:
 *
 *   1. file_assets rows  — path, hash, size, and whether the target exists on disk.
 *   2. canonical files   — on disk, and whether any file_asset row points at them.
 *   3. cms_media_assets  — the typed CMS surface + which file_asset each maps to.
 *   4. Consumers         — hero_banners / campaigns / events / galleries / gallery_images
 *                          and whether their image FK resolves to a live asset.
 *   5. A rename+seed plan sketch: which canonical file should back which consumer row.
 *
 * Run:  docker exec temple-trust-worker php /app/assess_db_surface.php
 */

$env = file('/app/.env');
$url = '';
foreach ($env as $l) { $l = trim($l); if (str_starts_with($l, 'DATABASE_URL=')) { $url = trim(substr($l, 13)); break; } }
$u = parse_url($url);
$db = ltrim($u['path'] ?? '/neondb', '/');
$sslmode = 'require';
if (isset($u['query'])) { parse_str($u['query'], $q); if (isset($q['sslmode'])) $sslmode = $q['sslmode']; }
$dsn = sprintf("pgsql:host=%s;port=%d;dbname=%s;sslmode=%s", $u['host'], $u['port'] ?? 5432, $db, $sslmode);
$pdo = new PDO($dsn, $u['user'], $u['pass']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function q(PDO $pdo, string $s, array $p = []): array {
    $st = $pdo->prepare($s); $st->execute($p); return $st->fetchAll(PDO::FETCH_ASSOC);
}

$PUB = '/app/storage/app/public';
$CAN = "$PUB/cms-media";              // the canonical serving dir (output of cleanName)
$SRC = "$PUB/cms-media-upscaled/canonical";  // the pre-rename source (audit only)

function imgsize(string $path): ?array {
    if (!file_exists($path)) return null;
    $s = @getimagesize($path);
    return $s ? ['w' => $s[0], 'h' => $s[1]] : null;
}

echo "═══════════════════════════════════════════════════════════\n";
echo " 1. FILE_ASSETS  (DB rows pointing at stored files)\n";
echo "═══════════════════════════════════════════════════════════\n";
$fa = q($pdo, "SELECT id, owner_type, original_filename, storage_disk, storage_path,
                       mime_type, file_size_bytes, file_hash_sha256
                FROM file_assets ORDER BY storage_path");
echo "total file_assets rows: " . count($fa) . "\n\n";

$onDisk = 0; $missing = 0; $missingRows = [];
$byPrefix = [];
foreach ($fa as $f) {
    $full = "$PUB/" . $f['storage_path'];
    $exists = file_exists($full);
    $exists ? $onDisk++ : $missing++;
    if (!$exists) $missingRows[] = $f;
    $prefix = explode('/', $f['storage_path'])[0];
    $byPrefix[$prefix] = ($byPrefix[$prefix] ?? 0) + 1;
}
echo "  target EXISTS on disk : $onDisk\n";
echo "  target MISSING on disk: $missing\n";
echo "\n  by storage_path prefix:\n";
foreach ($byPrefix as $p => $c) echo "    - $p/  ... $c rows\n";

if ($missingRows) {
    echo "\n  MISSING targets (broken refs):\n";
    foreach ($missingRows as $m) printf("    ‼ %-24s %s\n", $m['id'], $m['storage_path']);
}

echo "\n═══════════════════════════════════════════════════════════\n";
echo " 2. CANONICAL FILES on disk  (the renamed image state)\n";
echo "═══════════════════════════════════════════════════════════\n";
$canon = [];
foreach (glob("$CAN/*") as $cf) $canon[basename($cf)] = $cf;
echo "canonical files: " . count($canon) . "\n";

// Which DB paths resolve to a canonical file (by basename match)?
$dbBasenames = [];
foreach ($fa as $f) $dbBasenames[strtolower(basename($f['storage_path']))] = 1;

$referenced = []; $orphan = [];
foreach ($canon as $name => $path) {
    $hit = isset($dbBasenames[strtolower($name)]);
    // also: DB may name .webp where canonical is .jpg
    if (!$hit) {
        $alt = preg_replace('/\.(jpg|jpeg|png|webp)$/i', '', strtolower($name));
        foreach ($dbBasenames as $dbb => $_) {
            $dbStem = preg_replace('/\.(jpg|jpeg|png|webp)$/i', '', $dbb);
            if ($dbStem === $alt) { $hit = true; break; }
        }
    }
    $hit ? $referenced[] = $name : $orphan[] = $name;
}
echo "  referenced by a file_asset row : " . count($referenced) . "\n";
echo "  ORPHAN (no file_asset points at it): " . count($orphan) . "\n";

echo "\n═══════════════════════════════════════════════════════════\n";
echo " 3. CMS_MEDIA_ASSETS  (typed CMS surface)\n";
echo "═══════════════════════════════════════════════════════════\n";
$cma = q($pdo, "SELECT cma.id, cma.media_type, cma.state, cma.width, cma.height,
                       cma.file_asset_id, fa.storage_path
                FROM cms_media_assets cma
                LEFT JOIN file_assets fa ON fa.id = cma.file_asset_id
                ORDER BY cma.media_type, fa.storage_path");
echo "total cms_media_assets rows: " . count($cma) . "\n";
$byType = [];
foreach ($cma as $c) $byType[$c['media_type']] = ($byType[$c['media_type']] ?? 0) + 1;
foreach ($byType as $t => $n) echo "    - $t : $n\n";

echo "\n  each cms_media_asset → file target state:\n";
foreach ($cma as $c) {
    $full = $c['storage_path'] ? "$PUB/" . $c['storage_path'] : null;
    $st = $full === null ? 'NO-FILE-ASSET' : (file_exists($full) ? 'OK' : 'MISSING');
    printf("    [%s] %-16s %-10s %s\n", $st, $c['media_type'], $c['state'], $c['storage_path'] ?? '(null)');
}

echo "\n═══════════════════════════════════════════════════════════\n";
echo " 4. CONSUMERS  (image FK resolution)\n";
echo "═══════════════════════════════════════════════════════════\n";
$consumers = [
    ['hero_banners',   'image_file_id',        'title'],
    ['hero_banners',   'mobile_image_file_id', 'title'],
    ['campaigns',      'cover_image_file_id',  'title'],
    ['events',         'banner_file_id',       'title'],
    ['galleries',      'cover_image_file_id',  'title'],
    ['gallery_images', 'file_asset_id',        'title'],
];
foreach ($consumers as [$table, $col, $label]) {
    $rows = q($pdo, "SELECT t.$label AS lbl, t.$col AS cid,
                            cma.state AS cma_state, fa.storage_path AS fa_path
                     FROM $table t
                     LEFT JOIN cms_media_assets cma ON cma.id = t.$col
                     LEFT JOIN file_assets fa ON fa.id = cma.file_asset_id
                     WHERE t.$col IS NOT NULL");
    $total = q($pdo, "SELECT count(*) c FROM $table")[0]['c'];
    $live = 0; $broken = 0;
    foreach ($rows as $r) {
        if ($r['fa_path'] && file_exists("$PUB/" . $r['fa_path'])) $live++; else $broken++;
    }
    printf("  %-14s via %-22s rows=%-3s linked=%-3s live-file=%s broken=%s\n",
        $table, $col, $total, count($rows), $live, $broken);
}

echo "\n═══════════════════════════════════════════════════════════\n";
echo " 5. ORPHAN canonical files  (need a DB row after rename+seed)\n";
echo "═══════════════════════════════════════════════════════════\n";
foreach ($orphan as $o) {
    $sz = imgsize($canon[$o]);
    $bytes = filesize($canon[$o]);
    printf("    %-52s %sx%s  %d bytes\n", $o, $sz['w'] ?? '?', $sz['h'] ?? '?', $bytes);
}

echo "\nDONE\n";
