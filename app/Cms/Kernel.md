# Cms Kernel

> One-screen navigation map for the `Cms` kernel.
> Read order: Boundaries → Contracts → Providers → FSMs → Module class → Tests.

## Boundaries

**Owns:** public-content orchestration. Six V1 entities: `StaticPage`,
`HeroBanner`, `StaticPageReference`, `ContactInformation`,
`CmsMediaAsset`, `LegalPageContent` (typed aggregate). Owns the
rendering pipeline (block registry + `StaticPageBodyRenderer`) and
the Redis-backed resolved-page cache. The CMS kernel is the only
kernel the frontend (Phase 3 Svelte/Inertia UI) consumes directly.

**Does NOT own:** user accounts (`Auth` — Phase 4 Admin kernel), donations
or receipts (`Payments`), campaigns themselves (`Campaigns`). Cross-kernel
data is resolved exclusively through the Module's declared dependencies —
never by importing another kernel's implementation code.

**Outbound edges:**
- Into Payments via `App\Payments\Contracts\CampaignQueryContract` (Shape A
  bridge — Payments owns the contract because the producing kernel declares
  the boundary).
- Into Shared for identifier generation and configuration.

## Contracts

| Contract | FQCN | Direction |
|---|---|---|
| BlockRendererContract | `App\Cms\Contracts\BlockRendererContract` | Marker for block-renderer implementations |
| ImageUrlResolverContract | `App\Cms\Contracts\ImageUrlResolverContract` | Resolves file-asset id → URL (Phase 4 replaces stub with real storage) |
| PublicMediaQueryContract | `App\Cms\Contracts\PublicMediaQueryContract` | Public read of Cms media |
| ResolvedPageCacheContract | `App\Cms\Contracts\ResolvedPageCacheContract` | Resolved-page cache (Redis-backed) |
| StaticPageRendererContract | `App\Cms\Contracts\StaticPageRendererContract` | Public render entry point |

Plus 5 internal repository contracts under
`App\Cms\Domain\Repositories\` (Static/Hero/Reference/Contact/Media).

## Providers

`App\Cms\Providers\CmsServiceProvider` — pinned position in the provider
order: Shared → Persistence → Runtime → Redis → Queue → Payments → **Cms**
→ Campaigns → Gallery → Events. The CMS provider boots after Payments so
it can resolve the Shape A bridge (`CampaignQueryContract`).

Bindings (see `register()` for axis labels A–G):
- 5 repository contracts → Eloquent implementations
- `StaticPageStateMachine` singleton (pure-function FSM)
- `ResolvedPageCacheContract → RedisResolvedPageCache`
- `BlockRendererRegistry` singleton (constructed with 5 block renderers)
- `StaticPageRendererContract → StaticPageRendererService`
- `ImageUrlResolverContract → PublicMediaUrlResolver`
- 10 service singletons (`StaticPageService`, `StaticPageQueryService`,
  `HeroBannerService`, `ContactInformationService`,
  `ReferenceResolutionService`, `PublicMediaPresentationService`,
  `HomepageContentFactory`, `AboutPageContentFactory`,
  `LegalPageContentFactory`, `StaticPageRendererService`)

`boot()` registers 4 entity types against `RepositoryRegistryContract`
and subscribes `CacheInvalidationListener` to 9 Cms domain events
(`CmsDomainEvents`).

## FSMs

`App\Cms\Domain\StateMachines\StaticPageStateMachine` — handwritten FSM
covering the static-page state graph (draft → published → archived).
Allowed transitions + guard conditions live in this file. The machine is
registered as a singleton; the `StaticPageService` invokes it on every
mutation. See `cms-architecture.md` §6 for the full transition matrix.

Tests: `tests/Unit/Cms/StateMachines/`.

## Module class

Present: `App\Cms\CmsModule` (implements `App\Shared\Contracts\ModuleContract`).

`dependencies()`: `App\Shared\Contracts\ModuleContract` +
`App\Payments\Contracts\CampaignQueryContract`. The CMS module does **not**
depend on `App\Payments\Services`, `App\Payments\Infrastructure`, or
`App\Payments\Domain` directly — the contract surface is the only
sanctioned cross-kernel import. See `cms-architecture.md` §7.

## Surfaces

The Cms kernel exposes three canonical public surfaces, each backed by a
dedicated `App\Http\Controllers\Public\` controller, route, and Svelte
component, plus the generic `/{slug}` whitelist for content-only pages:

- `/` (homepage) — `cms.homepage` → `HomeController`
- `/about` — `cms.about` → `AboutController` (carries `about_page_content`)
- `/legal` — `cms.legal` → `LegalController` (carries `legal_page_content`)

### About-page featured galleries (DB-driven)

The "Three windows into the trust" section on `/about` is wired to
`App\Gallery\Contracts\GalleryQueryContract::listFeatured(3)`. The
controller enriches the result via `PublicMediaPresentationService`
and passes it as the `featuredGalleries` Inertia prop. The Svelte
component (`cms/About.svelte`) renders one card per gallery with the
real cover image; the hardcoded slug list that previously lived in
the component (and pointed at the now-archived `sacred-festivals`
row) has been removed. Adding a row to `galleries` with
`is_featured=true` propagates automatically.

### Home-page image slots (decoupled)

The `static_pages.homepage_content` JSONB carries two image references
on both the `story` block and each `programs[]` entry:

- `image_file_id` — the section image (Trust-Story section, Annadanam
  program card, etc.).
- `pillar_image_file_id` — optional dedicated image for the matching
  Pillar-Triad card. For `story`, this is the first pillar card ("The
  schools, running today"). For `programs[i]`, this is the (i+1)th
  pillar card. When null, the presentation layer (`Home.svelte`) falls
  back to `image`. This decoupling lets a program card carry one image
  while the matching pillar card carries another (mirrors the Pass 1
  story↔pillar split, extended to programs in Pass 4).

The Pillar-Triad's three cards derive as:
1. `story.pillar_image_file_id ?? story.image_file_id`
2. `programs[1].pillar_image_file_id ?? programs[1].image_file_id`
3. `featuredGalleries[0].cover_image`

Public media resolves through `/media/{id}`
(`Public\CmsMedia\ShowController` → `PublicMediaQuery`):
`cms_media_assets.id` → `file_assets.storage_disk` +`storage_path`.
Public-disk paths are relative to `storage/app/public/`. The home-page
canonical images are served directly from
`storage/app/public/cms-media-upscaled/canonical/` — the DB surface is
aligned to that filesystem state by the idempotent maintenance script
`align_home_images.php` (supports `--dry-run`).

`/legal` is content-only: no hero banner editor surface exists, so the
page intentionally carries no `heroBanners` payload. The structured
certificate content lives in `static_pages.legal_page_content` (JSONB)
and is hydrated through `LegalPageContentFactory` into a typed
`LegalPageContent` aggregate. The Svelte layer reads the typed
`legalContent` prop or falls back to
`resources/js/domains/cms/legal-fallbacks.ts` when the column is null.

## Tests

- `tests/Unit/Cms/Domain/` — entity + value-object unit tests (incl.
  `ValueObjects/` — blocks, page bodies, SEO metadata).
- `tests/Unit/Cms/Infrastructure/` — repository + rendering pipeline
  unit tests (incl. `Persistence/` mappers).
- `tests/Unit/Cms/StateMachines/` — `StaticPageStateMachine` allowed/
  forbidden transition tests.
- `tests/Feature/Cms/CmsSchemaAndAdapterTest.php` — load-bearing
  verification that the renamed SQLite mirror
  (`2026_07_16_000005_k_cms_create_cms_tables_sqlite.php`) lands every
  expected CMS table.

The `/legal` surface is verified end-to-end through the running
application at `localhost:8000/legal` — no PHPUnit abstraction. The
canonical reference for the public/legal pipeline is the About-page
mirror, which carries the equivalent typed aggregate.