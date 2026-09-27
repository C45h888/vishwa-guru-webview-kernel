<?php

declare(strict_types=1);

/**
 * Events alignment — thins the events surface to 4 canonical past entries
 * and couples every banner to the DB-canonical serving path.
 *
 * Phase: Pass 7 — events page canonical surface.
 *
 * What it does (one transaction):
 *
 *   1. Soft-deletes the 2 stale placeholder events from the v1 seed
 *      (varma-laxmi-pooje, diwali-2026-test). Audit trail preserved.
 *
 *   1b. HARD-deletes ram-navami-2026. The row had no banner and made no
 *      sense to keep around — once soft-deleted it has been promoted to a
 *      hard delete per explicit instruction.
 *
 *   2. Inserts 4 canonical events with a complete banner chain:
 *        events.banner_file_id  →  cms_media_assets.id
 *                              →  file_assets.storage_path =
 *                                 cms-media-upscaled/canonical/<file>
 *      Every banner resolves through /media/{cms_media_id} which loads
 *      the canonical image bytes off the public disk.
 *
 * Events + canonical image picks (every file verified to exist on disk
 * under storage/app/public/cms-media-upscaled/canonical/):
 *
 *   durga-pooja                          → journal-festivals-03-ganesh-chaturthi.jpg
 *     (large-festival visual; major Hindu celebration context)
 *
 *   journal-awards-ceremony-performances → journal-awards-01-chaganti.jpg
 *     (awards ceremony; first entry of the journal-awards-* canonical set)
 *
 *   orphans-event-school                 → children.jpg
 *     (children at the trust's schools; matches the orphan-day audience)
 *
 *   shami-vriksha-pooja                  → journal-community-05-villagers.jpg
 *     (Banni Pooja is a village ritual; villagers-gathering is the closest
 *      thematic match in the canonical community set)
 *
 * Image picks are placeholders pending the annotation workload. Swap a
 * pick by editing the `canonical` field in this script and re-running —
 * the seeder is idempotent via ON CONFLICT.
 *
 * Idempotent: every write uses fixed ids + absolute-value updates.
 *
 * Run:  docker exec temple-trust-app php /app/align_events.php
 * Dry:  docker exec temple-trust-app php /app/align_events.php --dry-run
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
const ADMIN_USER_ID = '01KZ1KY5XCAX71Q16V86QV2TWE';

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

/**
 * 4 canonical events. ULIDs pre-generated and pinned so re-runs are no-ops.
 * The `starts_at` / `ends_at` dates are plausible past dates (Durga Pooja
 * is October-ish; Shami Vriksha is November-ish) — the user can edit
 * dates via the admin surface or by editing this script.
 */
$events = [
    [
        'event_id' => '0001NBH1QEABVXJZRX80D87E4B',
        'fa_id'    => 'file_asset_0001NBH1QE4HZ8DDW4XP1C280X',
        'cma_id'   => 'cms_media_0001NBH1QEHQK5GAV98RRQDVD6',
        'slug'     => 'durga-pooja',
        'title'    => 'Durga Pooja',
        'short_description' => 'The annual celebration of Durga Pooja at the temple.',
        'description' => "The trust observes Durga Pooja each year as one of the temple's major community festivals. The celebration gathers devotees across the surrounding villages for nine nights of worship, concluding with the Vijayadashami procession.",
        'venue'    => 'Main Temple',
        'starts_at' => '2025-10-12T09:00:00+05:30',
        'ends_at'   => '2025-10-12T13:00:00+05:30',
        'state'    => 'completed',
        'is_featured' => true,
        'display_order' => 0,
        'canonical' => 'journal-festivals-03-ganesh-chaturthi.jpg',
        'alt'       => 'Durga Pooja — major festival celebration at the temple',
    ],
    [
        'event_id' => '0001NBH1QEE96R8Q5J1MA68258',
        'fa_id'    => 'file_asset_0001NBH1QECP3XPYV099NT9HKN',
        'cma_id'   => 'cms_media_0001NBH1QEJTX6FQ5WVGDVY2BN',
        'slug'     => 'journal-awards-ceremony-performances',
        'title'    => 'Journal Awards Ceremony and Performances',
        'short_description' => 'Awards ceremony and cultural performances hosted by the trust.',
        'description' => "The trust hosts an annual Journal Awards Ceremony that honours the writers, performers, and organisers who sustain the temple's cultural work. The evening includes classical performances and a presentation of awards to the year's recipients.",
        'venue'    => 'Temple Hall',
        'starts_at' => '2025-11-15T18:00:00+05:30',
        'ends_at'   => '2025-11-15T22:00:00+05:30',
        'state'    => 'completed',
        'is_featured' => true,
        'display_order' => 1,
        'canonical' => 'journal-awards-01-chaganti.jpg',
        'alt'       => 'Journal Awards Ceremony — temple honours writers, performers, and organisers',
    ],
    [
        'event_id' => '0001NBH1QE9ZXZ38SEJC4YX550',
        'fa_id'    => 'file_asset_0001NBH1QEPX0CNDQQK350HBJT',
        'cma_id'   => 'cms_media_0001NBH1QEBVW857YM4JKGBR6K',
        'slug'     => 'orphans-event-school',
        'title'    => 'Orphans Event at the School',
        'short_description' => 'A day of hosting and gift-giving for the orphans at the trust’s school.',
        'description' => "An annual day dedicated to the orphans supported by the trust. The school is dressed for the occasion, the children gather for a shared meal, and the trust provides clothes, books, and small gifts for every child.",
        'venue'    => 'Trust School',
        'starts_at' => '2025-12-20T10:00:00+05:30',
        'ends_at'   => '2025-12-20T15:00:00+05:30',
        'state'    => 'completed',
        'is_featured' => false,
        'display_order' => 2,
        'canonical' => 'children.jpg',
        'alt'       => 'Orphans Event at the School — children gathered for the annual celebration',
    ],
    [
        'event_id' => '0001NBH1QEQQ009NR2Y3GPQXCY',
        'fa_id'    => 'file_asset_0001NBH1QE17AMRFSJ478DKGYH',
        'cma_id'   => 'cms_media_0001NBH1QEWQ5DDJM228F3AYHF',
        'slug'     => 'shami-vriksha-pooja',
        'title'    => 'Shami Vriksha Pooja (Banni Pooja)',
        'short_description' => 'The Banni Pooja — worship of the Shami sapling and the village tree.',
        'description' => "The Shami Vriksha Pooja (Banni Pooja) is the village ritual of worshipping the Shami sapling and exchanging leaves as a token of goodwill. The trust hosts the ritual at the temple and welcomes devotees from the surrounding villages.",
        'venue'    => 'Temple Grounds',
        'starts_at' => '2025-11-11T07:00:00+05:30',
        'ends_at'   => '2025-11-11T10:00:00+05:30',
        'state'    => 'completed',
        'is_featured' => false,
        'display_order' => 3,
        'canonical' => 'journal-community-05-villagers.jpg',
        'alt'       => 'Shami Vriksha Pooja (Banni Pooja) — village ritual at the temple',
    ],
];

/**
 * Stale placeholders to soft-delete (audit trail preserved).
 * Note: ram-navami-2026 used to be here. It is now in $hardDeleteSlugs.
 */
$softDeleteSlugs = ['varma-laxmi-pooje', 'diwali-2026-test'];

/**
 * Events to HARD-delete (row removed). Idempotent: only acts on rows
 * that exist. Re-runs after a successful delete are no-ops.
 */
$hardDeleteSlugs = ['ram-navami-2026'];

echo $dry ? "== DRY RUN (no writes) ==\n" : "== APPLYING events alignment ==\n";

$pdo->beginTransaction();

try {
    // ── 1. Soft-delete stale placeholders (audit trail preserved) ──
    foreach ($softDeleteSlugs as $slug) {
        echo "  -- soft-delete event slug={$slug}\n";
        if (! $dry) {
            $pdo->prepare(
                "UPDATE events
                    SET deleted_at = now(), updated_at = now()
                  WHERE slug = :slug AND deleted_at IS NULL"
            )->execute(['slug' => $slug]);
        }
    }

    // ── 1b. HARD-delete events that have been promoted to permanent removal.
    //       Soft-delete first (so the partial unique slug index frees up), then
    //       hard-delete so the row is gone for good. Idempotent: re-runs after
    //       a successful hard-delete are no-ops.
    foreach ($hardDeleteSlugs as $slug) {
        echo "  -- hard-delete event slug={$slug}\n";
        if (! $dry) {
            $pdo->prepare(
                "UPDATE events
                    SET deleted_at = now(), updated_at = now()
                  WHERE slug = :slug AND deleted_at IS NULL"
            )->execute(['slug' => $slug]);
            $pdo->prepare("DELETE FROM events WHERE slug = :slug")->execute(['slug' => $slug]);
        }
    }

    // ── 2. Prepare upsert statements ─────────────────────────────────
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
             published_at, created_at, updated_at, created_by, updated_by)
         VALUES
            (:id, :file_asset_id, :media_type, :state, :alt, :w, :h,
             now(), now(), now(), :user, :user)
         ON CONFLICT (id) DO UPDATE SET
            file_asset_id = EXCLUDED.file_asset_id,
            media_type = EXCLUDED.media_type,
            state = EXCLUDED.state,
            alt_text = EXCLUDED.alt_text,
            width = EXCLUDED.width,
            height = EXCLUDED.height,
            published_at = EXCLUDED.published_at,
            updated_at = now(),
            updated_by = EXCLUDED.updated_by'
    );

    $upsertEvent = $pdo->prepare(
        'INSERT INTO events
            (id, slug, title, description, short_description,
             banner_file_id, starts_at, ends_at, timezone, venue, venue_address,
             state, published_at, completed_at, is_featured, display_order,
             metadata, created_at, updated_at, created_by, updated_by)
         VALUES
            (:id, :slug, :title, :description, :short_description,
             :banner_file_id, :starts_at, :ends_at, :timezone, :venue, :venue_address,
             :state, :published_at, :completed_at, :is_featured, :display_order,
             :metadata, now(), now(), :user, :user)
         ON CONFLICT (id) DO UPDATE SET
            slug = EXCLUDED.slug,
            title = EXCLUDED.title,
            description = EXCLUDED.description,
            short_description = EXCLUDED.short_description,
            banner_file_id = EXCLUDED.banner_file_id,
            starts_at = EXCLUDED.starts_at,
            ends_at = EXCLUDED.ends_at,
            timezone = EXCLUDED.timezone,
            venue = EXCLUDED.venue,
            venue_address = EXCLUDED.venue_address,
            state = EXCLUDED.state,
            published_at = EXCLUDED.published_at,
            completed_at = EXCLUDED.completed_at,
            is_featured = EXCLUDED.is_featured,
            display_order = EXCLUDED.display_order,
            metadata = EXCLUDED.metadata,
            updated_at = now(),
            updated_by = EXCLUDED.updated_by'
    );

    foreach ($events as $ev) {
        $m = fileMeta($ev['canonical']);
        printf(
            "  %-40s  order=%-2d  %s (%dx%d, %d B)\n",
            $ev['slug'],
            $ev['display_order'],
            $ev['canonical'],
            $m['w'] ?? 0,
            $m['h'] ?? 0,
            $m['bytes'],
        );

        if ($dry) {
            continue;
        }

        // file_assets row
        $upsertFa->execute([
            'id' => $ev['fa_id'],
            'owner_type' => 'event_banner',
            'owner_id' => $ev['slug'],
            'orig' => $ev['canonical'],
            'disk' => 'public',
            'path' => STORAGE_PREFIX.$ev['canonical'],
            'mime' => $m['mime'],
            'bytes' => $m['bytes'],
            'sha' => $m['sha'],
            'purpose' => 'event_banner',
            'metadata' => json_encode(['aligned_by' => 'align_events.php'], JSON_THROW_ON_ERROR),
        ]);

        // cms_media_assets row
        $upsertCma->execute([
            'id' => $ev['cma_id'],
            'file_asset_id' => $ev['fa_id'],
            'media_type' => 'event_banner',
            'state' => 'published',
            'alt' => $ev['alt'],
            'w' => $m['w'],
            'h' => $m['h'],
            'user' => ADMIN_USER_ID,
        ]);

        // events row
        $publishedAt = '2025-09-01T00:00:00+05:30';
        $completedAt = $ev['starts_at'];
        $upsertEvent->execute([
            'id' => $ev['event_id'],
            'slug' => $ev['slug'],
            'title' => $ev['title'],
            'description' => $ev['description'],
            'short_description' => $ev['short_description'],
            'banner_file_id' => $ev['cma_id'],
            'starts_at' => $ev['starts_at'],
            'ends_at' => $ev['ends_at'],
            'timezone' => 'Asia/Kolkata',
            'venue' => $ev['venue'],
            'venue_address' => null,
            'state' => $ev['state'],
            'published_at' => $publishedAt,
            'completed_at' => $completedAt,
            'is_featured' => $ev['is_featured'] ? 'true' : 'false',
            'display_order' => $ev['display_order'],
            'metadata' => json_encode(['aligned_by' => 'align_events.php'], JSON_THROW_ON_ERROR),
            'user' => ADMIN_USER_ID,
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
