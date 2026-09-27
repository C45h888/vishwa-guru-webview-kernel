<?php

declare(strict_types=1);

/**
 * About-page "Three windows" gallery alignment.
 *
 * The "Three windows into the trust" section on /about is now driven
 * by `GalleryQueryContract::listFeatured(3)` — the same contract the
 * home page uses for its featured galleries. Adding a row to
 * `galleries` with `is_featured=true` propagates automatically.
 *
 * This script only handles the DB-surface side of that wiring:
 *
 *   1. Un-flag the dead sacred-festivals row (`is_featured=0`,
 *      `state='archived'`, `deleted_at IS NOT NULL`). The previous
 *      hardcoded Svelte referenced its slug, so the section carried
 *      a 404 link; the new dynamic controller will skip it
 *      regardless, but the stale is_featured flag is misleading and
 *      should be corrected as part of this change.
 *
 *   2. Bump `display_order` on the remaining live featured galleries
 *      so the editorial order is stable: sacred-rituals (10),
 *      community-cultural (30). The 20-slot stays open for the next
 *      gallery that fills the "festive" gap.
 *
 * Doctrine (per AGENTS.md):
 *   - Idempotent: ON CONFLICT-style UPDATE-by-id with absolute values.
 *   - No new schema, no new migration.
 *   - Cache invalidation: `cms.page.about.resolved` so the
 *     controller's fresh listFeatured() call is observed.
 *
 * Run:  docker exec temple-trust-app php /app/align_about_galleries.php
 * Dry:  docker exec temple-trust-app php /app/align_about_galleries.php --dry-run
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

// ── Targets (id, slug, desired is_featured, desired display_order) ─────
$targets = [
    ['slug' => 'sacred-rituals',     'is_featured' => 1, 'display_order' => 10],
    ['slug' => 'sacred-festivals',   'is_featured' => 0, 'display_order' => 20],
    ['slug' => 'community-cultural', 'is_featured' => 1, 'display_order' => 30],
];

echo $dry
    ? "== DRY RUN (no writes) — about galleries alignment ==\n"
    : "== APPLYING about galleries alignment ==\n";

$pdo->beginTransaction();

/**
 * Coerce mixed boolean reps (bool / 't' / 'f' / 0 / 1) to a uniform bool.
 */
function asBool(mixed $v): bool
{
    if (is_bool($v)) {
        return $v;
    }
    if ($v === null) {
        return false;
    }
    return $v === 't' || $v === 'true' || (int) $v === 1;
}

try {
    $update = $pdo->prepare(
        'UPDATE galleries
            SET is_featured = :feat,
                display_order = :ord,
                updated_at = now()
          WHERE slug = :slug'
    );

    foreach ($targets as $t) {
        $row = $pdo->prepare('SELECT is_featured, display_order FROM galleries WHERE slug = :s');
        $row->execute(['s' => $t['slug']]);
        $before = $row->fetch(PDO::FETCH_ASSOC);
        if ($before === false) {
            echo sprintf(
                "  skip  %-22s (row missing)\n",
                $t['slug']
            );
            continue;
        }

        $changed = asBool($before['is_featured']) !== asBool($t['is_featured'])
            || (int) $before['display_order'] !== (int) $t['display_order'];

        if (! $changed) {
            echo sprintf(
                "  same  %-22s feat=%d order=%d\n",
                $t['slug'], $t['is_featured'], $t['display_order']
            );
            continue;
        }

        echo sprintf(
            "  set   %-22s feat: %d->%d  order: %d->%d%s\n",
            $t['slug'],
            (int) $before['is_featured'], $t['is_featured'],
            (int) $before['display_order'], $t['display_order'],
            $dry ? ' [dry]' : ''
        );

        if (! $dry) {
            $update->execute([
                'feat' => $t['is_featured'] ? 'true' : 'false',
                'ord' => $t['display_order'],
                'slug' => $t['slug'],
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

// ── Invalidate the Redis resolved-page cache for `about` ──────────────
// The about page is cached under cms.page.about.resolved.v2 (see
// RedisResolvedPageCache §KEY_PREFIX/KEY_SUFFIX/SCHEMA_VERSION).
if (! $dry) {
    try {
        $cache = $app->make(App\Cms\Contracts\ResolvedPageCacheContract::class);
        $cache->invalidate(new App\Cms\Domain\ValueObjects\PageSlug('about'));
        echo "  cache invalidated: cms.page.about.resolved\n";
    } catch (Throwable $e) {
        fwrite(STDERR, 'Cache invalidation skipped: '.$e->getMessage()."\n");
    }
}