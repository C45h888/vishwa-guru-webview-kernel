<?php
/**
 * Canonical image DB reconciler (idempotent).
 *
 * Repoints every cms-media file_asset at the canonical image state produced by
 * tools/canonical_media.py, so the DB becomes the source of truth for image
 * metadata and every reference resolves to a live file.
 *
 * Deterministic rule: file_asset -> canonical file via cleanName(original_filename),
 * resolved through storage/app/public/cms-media-upscaled/_logs/seed_map.json
 * (the audit manifest emitted by the materializer). No fuzzy matching.
 *
 * What it does, in one transaction:
 *   1. For each file_asset under cms-media/, look up its canonical target by
 *      original_filename and rewrite storage_path / mime_type / file_size_bytes /
 *      file_hash_sha256 to the canonical file's real values.
 *   2. Sync each cms_media_assets width/height to the canonical dimensions.
 *   3. Re-skin the 3 hero_banners onto 3 distinct generated hero images.
 *
 * Safe to re-run: every UPDATE is keyed by id and sets absolute values.
 *
 * Run:  docker exec temple-trust-worker php /app/seed_canonical_images.php
 * Dry:  docker exec temple-trust-worker php /app/seed_canonical_images.php --dry-run
 */

$dry = in_array('--dry-run', $argv ?? [], true);

$env = file('/app/.env'); $url = '';
foreach ($env as $l) { $l = trim($l); if (str_starts_with($l, 'DATABASE_URL=')) { $url = trim(substr($l, 13)); break; } }
$u = parse_url($url);
$db = ltrim($u['path'] ?? '/neondb', '/');
$sslmode = 'require';
if (isset($u['query'])) { parse_str($u['query'], $q); if (isset($q['sslmode'])) $sslmode = $q['sslmode']; }
$dsn = sprintf("pgsql:host=%s;port=%d;dbname=%s;sslmode=%s", $u['host'], $u['port'] ?? 5432, $db, $sslmode);
$pdo = new PDO($dsn, $u['user'], $u['pass']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$map = json_decode(file_get_contents('/app/storage/app/public/cms-media-upscaled/_logs/seed_map.json'), true);
$sourceToCanonical = $map['source_to_canonical'];
$canonicalMeta     = $map['canonical_meta'];

// original basename (lowercased) -> canonical filename. Multiple sources may map
// to one canonical (byte-identical merges); they share the same target.
$byBasename = [];
foreach ($sourceToCanonical as $src => $tgt) {
    $byBasename[strtolower($src)] = $tgt;
}

// Deterministic FALLBACKS: DB rows whose source image did not survive into the
// canonical set. Keyed by the DB original basename -> the closest surviving
// canonical sibling (same subject / crop family). Only these 3 need it; every
// other row resolves exactly.
$fallbacks = [
    'edited-photo-03.jpg'             => 'edited-photo-03-1x1.jpg',   // base lost, 1x1 sibling survives
    'edited-photo-03.jpg.3x4.webp'    => 'edited-photo-03-1x1.jpg',   // 3x4 crop lost, 1x1 sibling survives
    'journal-community-01-village-group.webp' => 'journal-community-05-villagers.jpg', // source lost; nearest community subject
];

function resolveCanonical(string $originalFilename, string $storagePath, array $byBasename, array $fallbacks): ?string {
    $cands = [strtolower($originalFilename), strtolower(basename($storagePath))];
    foreach ($cands as $c) {
        if (isset($byBasename[$c])) return $byBasename[$c];
    }
    foreach ($cands as $c) {
        if (isset($fallbacks[$c])) return $fallbacks[$c];
        if (isset($fallbacks[$originalFilename])) return $fallbacks[$originalFilename];
    }
    // fall back: strip a trailing image ext and try the doubled-ext source names
    foreach ($cands as $c) {
        foreach ($byBasename as $src => $tgt) {
            if (pathinfo($c, PATHINFO_FILENAME) === pathinfo($src, PATHINFO_FILENAME)) return $tgt;
        }
    }
    return null;
}

echo $dry ? "== DRY RUN (no writes) ==\n" : "== APPLYING canonical image reconcile ==\n";

// Valid EntityId = {entity_type}_{26-char UPPERCASE ULID}. Generate one via the
// app's own UlidGenerator so the runtime's EntityId::fromString() accepts it.
function ulid(): string {
    static $gen = null;
    if ($gen === null) {
        require_once '/app/vendor/autoload.php';
        $a = require '/app/bootstrap/app.php';
        $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    }
    return App\Shared\Support\UlidGenerator::generate();
}

$pdo->beginTransaction();
$updated = 0; $unresolved = []; $dimSynced = 0;

$assets = $pdo->query("SELECT id, original_filename, storage_path, mime_type, file_size_bytes, file_hash_sha256
                       FROM file_assets WHERE storage_path LIKE 'cms-media/%'")->fetchAll(PDO::FETCH_ASSOC);

foreach ($assets as $a) {
    $tgt = resolveCanonical($a['original_filename'], $a['storage_path'], $byBasename, $fallbacks);
    if ($tgt === null) { $unresolved[] = $a['original_filename'] . '  (' . $a['storage_path'] . ')'; continue; }
    $m = $canonicalMeta[$tgt];
    $path = 'cms-media/' . $tgt;
    if ($a['storage_path'] !== $path || $a['mime_type'] !== $m['mime']
        || (int)$a['file_size_bytes'] !== (int)$m['bytes'] || $a['file_hash_sha256'] !== $m['sha256']) {
        if (!$dry) {
            $st = $pdo->prepare("UPDATE file_assets SET storage_path=?, mime_type=?, file_size_bytes=?, file_hash_sha256=? WHERE id=?");
            $st->execute([$path, $m['mime'], $m['bytes'], $m['sha256'], $a['id']]);
        }
        $updated++;
        printf("  %-46s -> %s\n", $a['original_filename'], $path);
    }
}

// 2. sync cms_media_assets dimensions to canonical (resolve by original filename,
//    not storage_path basename, so it works even before the path rewrite).
$cmas = $pdo->query("SELECT cma.id, fa.original_filename, fa.storage_path
                     FROM cms_media_assets cma
                     JOIN file_assets fa ON fa.id = cma.file_asset_id
                     WHERE fa.storage_path LIKE 'cms-media/%'
                        OR fa.storage_path LIKE 'cms-media/%.webp'")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cmas as $c) {
    $tgt = resolveCanonical($c['original_filename'], $c['storage_path'], $byBasename, $fallbacks);
    if ($tgt === null || !isset($canonicalMeta[$tgt])) continue;
    $m = $canonicalMeta[$tgt];
    if (!$dry) {
        $st = $pdo->prepare("UPDATE cms_media_assets SET width=?, height=? WHERE id=?");
        $st->execute([$m['width'], $m['height'], $c['id']]);
    }
    $dimSynced++;
}

// 3. re-skin the 3 hero_banners onto 3 distinct generated hero images.
//    Each hero_banner.image_file_id -> a cms_media_asset whose file_asset is the hero image.
$heroPlan = [
    // hero_banner_id (matched by title) => canonical hero image
    'A Lineage of Bharatanatyam'            => 'hero-lineage.png',
    'Sustain the Schools, Build the Campus' => 'hero-sustain.png',
    'Seva in the Community'                 => 'hero-seva.png',
];
// find the file_asset + cms_media_asset for each hero image (create-if-missing)
$heroLinked = 0;
foreach ($heroPlan as $title => $heroFile) {
    $m = $canonicalMeta[$heroFile] ?? null;
    if (!$m) { echo "  !! hero image not in canonical set: $heroFile\n"; continue; }
    $path = 'cms-media/' . $heroFile;

    // locate (or create) the file_asset for this hero image
    $fa = $pdo->prepare("SELECT id FROM file_assets WHERE storage_path = ?");
    $fa->execute([$path]);
    $faRow = $fa->fetch(PDO::FETCH_ASSOC);
    if (!$faRow) {
        // create a dedicated hero file_asset with a valid EntityId (type_ULID)
        $newFaId = 'file_asset_' . $ulid();
        if (!$dry) {
            $ins = $pdo->prepare("INSERT INTO file_assets (id, owner_type, owner_id, original_filename, storage_disk, storage_path, mime_type, file_size_bytes, file_hash_sha256, is_public, uploaded_at, created_at, updated_at)
                                  VALUES (?, 'hero_banner', ?, ?, 'public', ?, ?, ?, ?, true, now(), now(), now())
                                  ON CONFLICT (id) DO NOTHING");
            $ins->execute([$newFaId, 'hero_seed', $heroFile, $path, $m['mime'], $m['bytes'], $m['sha256']]);
        }
        $faRow = ['id' => $newFaId];
    }

    // locate (or create) the cms_media_asset wrapping this file_asset
    $cma = $pdo->prepare("SELECT id FROM cms_media_assets WHERE file_asset_id = ?");
    $cma->execute([$faRow['id']]);
    $cmaRow = $cma->fetch(PDO::FETCH_ASSOC);
    if (!$cmaRow) {
        $newCmaId = 'cms_media_' . $ulid();
        if (!$dry) {
            $ins = $pdo->prepare("INSERT INTO cms_media_assets (id, file_asset_id, media_type, state, alt_text, width, height, published_at, created_at, updated_at)
                                  VALUES (?, ?, 'hero_desktop', 'published', ?, ?, ?, now(), now(), now())
                                  ON CONFLICT (id) DO NOTHING");
            $ins->execute([$newCmaId, $faRow['id'], 'Temple hero — ' . $title, $m['width'], $m['height']]);
        }
        $cmaRow = ['id' => $newCmaId];
    }

    // point the hero_banner at this cms_media_asset
    if (!$dry) {
        $st = $pdo->prepare("UPDATE hero_banners SET image_file_id = ? WHERE title = ?");
        $st->execute([$cmaRow['id'], $title]);
    }
    $heroLinked++;
    printf("  hero %-40s -> %s (cma %s)\n", $title, $path, $cmaRow['id']);
}

if ($dry) { $pdo->rollBack(); }
else { $pdo->commit(); }

echo "\nfile_assets repointed : $updated\n";
echo "cms_media dim synced  : $dimSynced\n";
echo "hero_banners relinked : $heroLinked\n";
if ($unresolved) {
    echo "UNRESOLVED (" . count($unresolved) . "):\n";
    foreach ($unresolved as $x) echo "  - $x\n";
}
echo $dry ? "\n(dry run — rolled back)\n" : "\nDONE — committed\n";
