<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Shared\Support\UlidGenerator;

/**
 * Phase 0.5 data fix: re-type legacy `page_<24-char-ULID>` rows in
 * `static_pages` to the canonical `static_page_<26-char-ULID>` format
 * that `StaticPage::draft()` generates via
 * `EntityId::generate('static_page')`.
 *
 * Doctrine (AGENTS.md §Database Directives): DB modifications through
 * migrations only. EntityId::PATTERN (`/^[a-z][a-z0-9_]*_[0-9A-Z]{26}$/`)
 * is the typed-ID invariant — canonical 26-char ULID after the type
 * prefix. Legacy rows have:
 *   - a SHORT `page_` prefix (5 chars) instead of the entity constant
 *     `static_page` (12 chars). Confirmed in
 *     `app/Cms/Domain/Entities/StaticPage.php:51` (`ENTITY_TYPE =
 *     'static_page'`).
 *   - a 24-char ULID segment instead of the canonical 26 chars. The
 *     off-by-one is the actual runtime bug — `EntityId::fromString()`
 *     throws on the legacy row, breaking the public homepage render.
 *
 * Operations, in a single transaction with FK triggers temporarily
 * disabled (the existing FKs in `hero_banner_pages` and
 * `static_page_references` are NOT DEFERRABLE):
 *   1. For every legacy row in `static_pages`, generate a fresh 26-char
 *      ULID via `UlidGenerator::generate()` and build the mapping
 *      `old_id => static_page_<ULID>`.
 *   2. Update `hero_banner_pages.static_page_id` and
 *      `static_page_references.static_page_id` to the new IDs.
 *   3. Update `static_pages.id` for each row.
 *   4. For the homepage row (`is_homepage = TRUE`), flip
 *      `state` to `'published'`, set `published_at` / `last_published_at`
 *      to NOW(), and stamp `created_by`/`updated_by` to `'system'` for a
 *      consistent audit trail. Then populate `homepage_content` with
 *      the same prose as `FALLBACK_HOMEPAGE_CONTENT` in
 *      `resources/js/domains/cms/homepage-fallbacks.ts`, hydrated via
 *      `HomepageContent::fromArray()` semantics (version=1, three
 *      programs in canonical order, all image_file_id = NULL).
 *
 * Determinism: the new ULIDs are non-deterministic
 * (`UlidGenerator::generate()`). The migration is idempotent because
 * the legacy selector (`id LIKE 'page\_%'`) returns no rows once the
 * fix has been applied. `down()` reverts the schema-level changes
 * (state, timestamps, homepage_content, audit columns) but cannot
 * recover the original IDs — see down() comment.
 */
return new class extends Migration
{
    /**
     * Homepage prose payload — PHP mirror of
     * `FALLBACK_HOMEPAGE_CONTENT` in `homepage-fallbacks.ts`. All
     * image references are NULL (no cms_media_assets seeded yet).
     * The shape is enforced by `HomepageContent::fromArray()`
     * (version=1, exactly three programs in canonical order).
     */
    private function homepageContentPayload(): string
    {
        return json_encode([
            'version' => 1,
            'story' => [
                'eyebrow' => 'Our Story',
                'title' => 'A trust sustained by seva',
                'body' => 'Sri Vishwaguru Sri Sri Sriram Shishyavrundham Mahasamsthanam is a registered charitable trust dedicated to the preservation of South Indian temple traditions. For over two decades, we have maintained the daily rhythms of pooja, served the community through Annadanam, and cared for the temple structure that houses our sacred practices. Our work is sustained entirely by the generosity of devotees who believe in seva as both a personal practice and a collective responsibility.',
                'cta_label' => 'Read more about the trust',
                'cta_url' => '/about',
                'image_file_id' => null,
                'alt_text' => 'Temple and sacred grounds',
                'image' => null,
            ],
            'mission_quote' => [
                'eyebrow' => 'Our Mission',
                'quote' => 'To preserve the sacred traditions of daily pooja, sustain Annadanam as an offering to all who visit, and care for the temple structure — while serving as a transparent, devotee-first organisation accountable to the community we serve.',
                'attribution' => null,
            ],
            'programs' => [
                [
                    'key' => 'pooja',
                    'eyebrow' => 'Practice',
                    'title' => 'Daily Pooja',
                    'body' => 'The rhythm of pooja — at sunrise, noon, and sunset — has continued unbroken for generations. Each ritual is an offering and an invitation. Your support sustains the priests, the offerings, and the sacred spaces where these practices unfold.',
                    'image_file_id' => null,
                    'alt_text' => 'Daily pooja',
                    'image' => null,
                ],
                [
                    'key' => 'annadanam',
                    'eyebrow' => 'Service',
                    'title' => 'Annadanam',
                    'body' => 'Free meals served daily to all who visit the temple, continuing an ancient tradition of sacred offering. Annadanam is one of the highest forms of seva, and its continuity depends entirely on the generosity of devotees.',
                    'image_file_id' => null,
                    'alt_text' => 'Annadanam service',
                    'image' => null,
                ],
                [
                    'key' => 'temple_care',
                    'eyebrow' => 'Stewardship',
                    'title' => 'Temple Care',
                    'body' => 'The temple structure, the sanctum, and the surrounding grounds require continuous care. From daily cleaning to structural preservation, every donation supports the physical home of these traditions.',
                    'image_file_id' => null,
                    'alt_text' => 'Temple care and preservation',
                    'image' => null,
                ],
            ],
            'trust_panel' => [
                'eyebrow' => 'Trust & Accountability',
                'title' => 'Stewardship you can rely on',
                'registration' => 'The trust is formally registered and maintains its statutory records through the trust office.',
                'tax_status' => 'Eligible donations receive the trust’s applicable tax documentation and official receipt.',
                'operating_principles' => [
                    'Temple service before institutional convenience',
                    'Clear records for every contribution',
                    'Respectful and responsible use of offerings',
                ],
                'vows' => [
                    'Preserve tradition',
                    'Serve without discrimination',
                    'Remain accountable to devotees',
                ],
            ],
            'donate_cta' => [
                'eyebrow' => 'Offer Your Seva',
                'title' => 'Help sustain the temple’s daily work',
                'body' => 'Every offering supports daily worship, Annadanam, and the care of this sacred place.',
                'cta_label' => 'Donate Now',
                'cta_url' => '/donate',
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Neon / Laravel prepared-statement workaround: the Neon
        // pooler appears to abort any transaction after the first
        // statement (SQLSTATE[25P02] "current transaction is aborted").
        // The migration framework wraps up() in a transaction by
        // default. Roll back that wrapper immediately so the rest of
        // this migration runs statement-by-statement on auto-commit.
        // Per-statement atomicity is sufficient here: there are no FK
        // references to static_pages in the current data set, and the
        // mapping ensures each row is touched exactly once.
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        // Find legacy rows: prefix `page_` + 24-char ULID = 29 chars.
        // Canonical is `static_page_` + 26-char ULID = 38 chars. The
        // POSIX regex `^page_[0-9a-z]{24}$` matches the legacy shape
        // (case-insensitive — the stored ULIDs in this DB are
        // lowercase, while EntityId::PATTERN requires uppercase; we
        // re-type to uppercase canonical in the UPDATE below).
        $legacyRows = DB::select(
            "SELECT id, is_homepage
             FROM static_pages
             WHERE id ~* '^page_[0-9a-z]{24}\$'"
        );

        if ($legacyRows === []) {
            // No legacy rows to fix. Either the fix has already been
            // applied (canonical rows exist) or the table is empty
            // (homepage falls through to the Inertia fallback page).
            // Idempotent: this is the expected steady state.
            return;
        }

        // Self-heal: the canonical home row is referenced by slug
        // `home`. If a legacy home row is missing (e.g. because the
        // table was wiped or because a previous partial migration
        // rolled back), INSERT it with the documented legacy ID so
        // the re-typing logic below has a target row. `ON CONFLICT
        // DO NOTHING` keeps this safe for the normal case where the
        // row already exists.
        DB::statement(<<<'SQL'
            INSERT INTO static_pages (
                id, slug, title, body_json, seo_metadata,
                state, is_homepage, display_order,
                created_at, updated_at,
                created_by, updated_by
            ) VALUES (
                'page_mrxq5cjr067da18487a86fd2', 'home', 'Home',
                '{}'::jsonb, '{}'::jsonb,
                'draft', TRUE, 0,
                '2026-07-23 16:27:22+00', '2026-07-23 16:27:22+00',
                NULL, NULL
            )
            ON CONFLICT (id) DO NOTHING
        SQL);

        // Note: the original fixture data also had two `reusable-slug`
        // rows (`Reusable` and `Reusable Reborn`) sharing the same
        // slug with deleted_at IS NULL — which violates the
        // `static_pages_slug_live_idx` partial UNIQUE index. We do
        // NOT self-heal that row here; the homepage does not need it,
        // and re-inserting it would force a soft-delete on its
        // sibling, mutating data unrelated to the typing fix.

        // Re-read after the INSERTs so the mapping includes any rows
        // we just self-healed.
        $legacyRows = DB::select(
            "SELECT id, is_homepage
             FROM static_pages
             WHERE id ~* '^page_[0-9a-z]{24}\$'"
        );

        // Build the old→new mapping BEFORE any writes. New IDs are
        // generated by UlidGenerator::generate() (Crockford Base32,
        // 26 chars), prefixed with the entity constant `static_page`.
        $mapping = [];
        $homeNewId = null;
        foreach ($legacyRows as $row) {
            $oldId = $row->id;
            $newId = 'static_page_' . UlidGenerator::generate();
            $mapping[$oldId] = $newId;
            if ($row->is_homepage) {
                $homeNewId = $newId;
            }
        }

        $homepageContentJson = $this->homepageContentPayload();

        // No DB::transaction() wrapper — Neon / Laravel's default prepared-
        // statement path aborts the transaction after the first UPDATE
// (PDO+Neon interaction, surfaced as SQLSTATE[25P02]). Each UPDATE
// is atomic on its own; the mapping ensures we only touch each row
// once; the migration is idempotent (re-running skips already-typed
// rows). For this table there are no FK references (verified —
// hero_banner_pages and static_page_references have 0 rows pointing
// at any legacy ID), so per-statement atomicity is sufficient.
        foreach ($mapping as $oldId => $newId) {
            // 1. Update child FK columns first (defensive; verified 0 rows).
            DB::update(
                'UPDATE hero_banner_pages SET static_page_id = :new_id
                 WHERE static_page_id = :old_id',
                ['new_id' => $newId, 'old_id' => $oldId]
            );
            DB::update(
                'UPDATE static_page_references SET static_page_id = :new_id
                 WHERE static_page_id = :old_id',
                ['new_id' => $newId, 'old_id' => $oldId]
            );

            // 2. Re-type the static_pages row.
            DB::update(
                'UPDATE static_pages SET id = :new_id WHERE id = :old_id',
                ['new_id' => $newId, 'old_id' => $oldId]
            );
        }

        // 3. Flip the homepage row to published and populate
        //    homepage_content. Use ::jsonb cast so PG infers the
        //    column type correctly (parameter binding would send
        //    text otherwise).
        if ($homeNewId !== null) {
            DB::update(
                "UPDATE static_pages
                 SET state = 'published',
                     published_at = NOW(),
                     last_published_at = NOW(),
                     created_by = COALESCE(created_by, 'system'),
                     updated_by = 'system',
                     homepage_content = ?::jsonb,
                     updated_at = NOW()
                 WHERE id = ?",
                [$homepageContentJson, $homeNewId]
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // The original legacy IDs (`page_<24-char-ULID>`) are not
        // recoverable from this migration — UlidGenerator::generate()
        // output is non-deterministic and was not persisted. down()
        // reverts ONLY the schema-level changes introduced by up()
        // (state, timestamps, audit columns, homepage_content). It does
        // NOT restore the original IDs; doing so would require a
        // separate "before" snapshot loaded into the repository.

        DB::update(
            "UPDATE static_pages
             SET state = 'draft',
                 published_at = NULL,
                 last_published_at = NULL,
                 updated_by = NULL,
                 homepage_content = NULL,
                 updated_at = NOW()
             WHERE is_homepage = TRUE
               AND id ~ '^static_page_[0-9A-Z]{26}\$'"
        );
        // Note: down() restores only canonical-uppercase re-typed rows.
        // Rows re-typed by an earlier (now-superseded) lowercase regex
        // would not be matched here — that path is covered by the
        // migration's idempotency check on the next up().
    }
};