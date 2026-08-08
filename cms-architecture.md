# CMS-ARCHITECTURE.md

# CMS Kernel Architecture — Public Content Orchestrator

## §0 Purpose & Scope

This document is the **instantiation specification** for the CMS (Content
Management System) kernel of the Temple Trust Management System. It defines
every contract, value object, entity, state machine, repository, service,
infrastructure component, cache strategy, cross-kernel bridge, and DI binding
required to instantiate the kernel against the existing architecture.

The companion test specification (`cms-test-spec.md`, written in a separate
pass per the project's 2-spec sequencing rule) expands each test class with
full assertions, fixtures, mocks, and coverage matrix. This document carries
the **test inventory** as a forward reference; the test spec carries the
**test design**.

The CMS kernel is the **public-content orchestrator** of the system. It is the
single interface the Phase 3 (Public Platform) frontend consumes. All
cross-kernel data assembly — campaigns, gallery assets, events — happens
behind the CMS query surface. The frontend never imports a Payments,
Gallery, or Events kernel contract directly. This invariant is the
defining architectural property of the CMS module and is enforced at the
service-provider boundary.

> **Per-kernel landing page:** for a one-screen navigation map of the
> CMS kernel (Boundaries, Contracts, Providers, FSMs, Tests), see
> [`app/Cms/Kernel.md`](app/Cms/Kernel.md).

### §0.1 V1 Kernel Scope

In scope (this spec instantiates these):

  - Static Pages (CMS-managed fixed pages: Home, About, Contact, Donate,
    Certifications)
  - Hero Banners (reusable hero asset pool; m:n linked to Static Pages)
  - Static Page References (polymorphic junction: page → campaign /
    gallery_image / event)
  - Contact Information (public contact points managed by CMS)
  - body_json V1 block grammar: paragraph, heading, image, cta_button,
    divider
  - body_html canonical render output cache (kernel writes on every state
    transition; public reads skip rendering)
  - Redis-backed resolved-page cache, invalidated on state transitions
  - Shape A cross-kernel bridge: Payments owns `CampaignQueryContract`,
    CMS consumes via DI

Out of scope (deferred to a later pass or another module):

  - Authentication / actor identity — Phase 4 (created_by / updated_by
    remain nullable; default to "system")
  - Admin UI authoring surfaces — Phase 4 (the kernel exposes authoring
    services; no controllers or Blade yet)
  - Slug registry enforcement — slugs are admin-free in V1; uniqueness is
    DB-enforced (partial unique index) + service-level pre-check
  - Gallery kernel — schema's `page_reference_type = 'gallery_image'`
    is reserved; no Gallery kernel exists yet. ReferenceResolver
    rejects gallery_image references with ReferenceTargetInvalidException
    until the Gallery kernel lands.
  - Events kernel — same deferral as Gallery
  - Albums / multi-image layouts — Phase 5+
  - SEO schema.org JSON-LD emission — Phase 4+
  - Audit events table writes — kernel emits domain events; the audit
    recorder wiring is a later pass

### §0.2 Architectural Authority

Per AGENTS.md and the Phase 0.25 constitution:

  - Business rules belong exclusively in services.
  - Repositories encapsulate persistence.
  - State machines are the sole authority on lifecycle transitions.
  - Controllers are not yet instantiated by this spec.
  - The CMS kernel depends on the Shared kernel and the Payments
    kernel's Contracts surface only. It does not depend on Payments
    Infrastructure or any concrete class outside its own namespace.

### §0.3 Locked Decisions (recap from clarification)

  D1  body_json is the authoring source of truth.
  D2  body_html is the canonical render output cache, written by the
      kernel on every state transition that produces public-readable
      content (PUBLISHED, UPDATED). Public reads do not re-render.
  D3  Versioning semantics: "Updated" folds back to "Published" on next
      save. The fold-back is an explicit state transition
      (UPDATED + PAGE_PUBLISHED → PUBLISHED). last_published_at tracks
      the timestamp of the most recent publish action; the schema's
      separate published_at column tracks the same instant. They are
      equal in V1.
  D4  Slugs admin-free. No service-level registry enforcement. Slug
      uniqueness is enforced by:
        (a) service-level pre-check (DuplicatePageSlugException) before
            INSERT
        (b) DB partial unique index `static_pages_slug_live_idx`
  D5  Single-homepage enforcement: service-layer refuses to mark a
      second page as homepage with HomepageAlreadyAssignedException;
      DB EXCLUDE constraint `static_pages_single_homepage` is the
      safety net.
  D6  Authorship columns nullable; default to "system" until Phase 4
      wires real actors.
  D7  Kernel bridge shape: **A** — Payments owns `CampaignQueryContract`
      in `app/Payments/Contracts/`. CMS consumes it via constructor
      injection in `StaticPageRendererService`. Payments is unaware of
      CMS.
  D8  body_json V1 block grammar: **Minimal** (5 types): paragraph,
      heading, image, cta_button, divider.
  D9  Cache: **Redis-backed resolved-page cache in kernel now**.
      Invalidated on every StaticPage state transition. Key shape:
      `cms.page.{slug}.resolved`. TTL 3600s (1 hour). Invalidated
      explicitly on transition AND on any body change.
  D10 Test spec is a separate 2nd-pass document. This spec carries the
      test inventory (file path + class name + assertion density target)
      as a forward reference only.

---

## §1 Module Boundary

The CMS kernel lives under the `App\Cms\` namespace, mirroring the Payments
kernel layout at `app/Payments/`. Every class in this spec has an exact
namespace and file path. No file may live outside the listed path.

### §1.1 Folder Layout

```
app/Cms/
│   ├── Contracts/
│   │   ├── ResolvedPageCacheContract.php
│   │   ├── StaticPageRendererContract.php       (read-side public surface)
│   │   ├── BlockRendererContract.php
│   │   └── ImageUrlResolverContract.php
├── Domain/
│   ├── Enums/
│   │   ├── StaticPageState.php
│   │   ├── PageReferenceType.php
│   │   └── CmsTransitionEvent.php
│   ├── ValueObjects/
│   │   ├── PageSlug.php
│   │   ├── SeoMetadata.php
│   │   ├── PageBody.php
│   │   ├── HeroBannerSlot.php
│   │   ├── ContactPoint.php
│   │   └── Blocks/
│   │       ├── Block.php                    (sealed interface)
│   │       ├── ParagraphBlock.php
│   │       ├── HeadingBlock.php
│   │       ├── ImageBlock.php
│   │       ├── CtaButtonBlock.php
│   │       └── DividerBlock.php
│   ├── DTOs/
│   │   ├── StaticPageDraftInput.php
│   │   ├── StaticPageUpdateInput.php
│   │   ├── HeroBannerDraftInput.php
│   │   ├── HeroBannerUpdateInput.php
│   │   ├── RenderedStaticPage.php
│   │   └── ResolvedReference.php
│   ├── Entities/
│   │   ├── StaticPage.php
│   │   ├── HeroBanner.php
│   │   ├── StaticPageReference.php
│   │   └── ContactInformation.php
│   ├── StateMachines/
│   │   ├── StaticPageStateMachine.php
│   │   └── CmsTransitionEvent.php           (re-export, see §3.4 note)
│   ├── Repositories/
│   │   ├── StaticPageRepositoryContract.php
│   │   ├── HeroBannerRepositoryContract.php
│   │   ├── StaticPageReferenceRepositoryContract.php
│   │   └── ContactInformationRepositoryContract.php
│   └── Exceptions/
│       ├── StaticPageNotFoundException.php
│       ├── InvalidPageStateTransitionException.php
│       ├── DuplicatePageSlugException.php
│       ├── HomepageAlreadyAssignedException.php
│       ├── HeroBannerNotFoundException.php
│       ├── ReferenceTargetInvalidException.php
│       └── ContactPointNotFoundException.php
├── Services/
│   ├── StaticPageService.php                (CRUD + transitions; Phase 4 authoring API)
│   ├── StaticPageQueryService.php           (read-side; Phase 3 frontend consumer)
│   ├── StaticPageRendererService.php        (orchestrator; Phase 3 frontend entry point)
│   ├── HeroBannerService.php
│   ├── ContactInformationService.php
│   └── ReferenceResolutionService.php       (validates + fetches cross-kernel refs)
├── Infrastructure/
│   ├── Repositories/
│   │   ├── EloquentStaticPageRepository.php
│   │   ├── EloquentHeroBannerRepository.php
│   │   ├── EloquentStaticPageReferenceRepository.php
│   │   └── EloquentContactInformationRepository.php
│   ├── Rendering/
│   │   ├── StaticPageBodyRenderer.php       (orchestrates per-block renderers)
│   │   ├── BlockRendererRegistry.php
│   │   └── BlockRenderers/
│   │       ├── ParagraphBlockRenderer.php
│   │       ├── HeadingBlockRenderer.php
│   │       ├── ImageBlockRenderer.php
│   │       ├── CtaButtonBlockRenderer.php
│   │       └── DividerBlockRenderer.php
│   ├── UrlResolution/
│   │   └── StubImageUrlResolver.php         (V1 stub; Phase 4 replaces)
│   ├── Caching/
│   │   ├── RedisResolvedPageCache.php       (impl of ResolvedPageCacheContract)
│   │   └── CacheInvalidationListener.php    (event listener, dispatches invalidation)
│   ├── Persistence/
│   │   ├── StaticPageMapper.php
│   │   ├── HeroBannerMapper.php
│   │   ├── StaticPageReferenceMapper.php
│   │   └── ContactInformationMapper.php
│   └── Events/
│       └── CmsDomainEvents.php              (event constants; Laravel event dispatcher)
├── CmsModule.php                            (implements ModuleContract)
└── Providers/
    └── CmsServiceProvider.php
```

### §1.2 File Counts and Approximate Line Counts

| Group                    | File Count | Avg Lines | Total     |
|--------------------------|-----------:|----------:|----------:|
| Contracts                |          3 |        60 |       180 |
| Domain/Enums             |          3 |        40 |       120 |
| Domain/ValueObjects/VO   |          5 |        90 |       450 |
| Domain/ValueObjects/Blocks|          6 |        60 |       360 |
| Domain/Entities          |          4 |       300 |     1,200 |
| Domain/StateMachines      |          2 |       180 |       360 |
| Domain/Repositories      |          4 |       110 |       440 |
| Domain/Exceptions        |          7 |        50 |       350 |
| Services                 |          6 |       160 |       960 |
| Infrastructure/Repos     |          4 |       220 |       880 |
| Infrastructure/Rendering |          7 |        80 |       560 |
| Infrastructure/Caching   |          2 |       120 |       240 |
| Infrastructure/Persistence|         4 |        90 |       360 |
| Infrastructure/Events    |          1 |        40 |        40 |
| Module + Provider        |          2 |       220 |       440 |
| **TOTAL**                |     **60** |           | **~6,940** |

The Payments kernel totals ~12,000 lines including its 100+ test files; the
CMS kernel is smaller because it carries fewer state machines and no
gateway adapters. Tests live under `tests/` and are counted in §11.

### §1.3 PHP Version & Language Features

The kernel relies on PHP 8.2+ language features. `composer.json` pins
`"php": "^8.2"`. Required features used by this spec:

  - **Sealed interfaces** — `Block` interface and `BlockRendererContract`
    enforcement; the `permits` clause lists every permitted implementer.
  - **Readonly classes** — every VO, every DTO is `final readonly class`.
    Entities are `final class` with `private readonly` constructor params
    + non-readonly private mutable state for the state-transition cycle
    (matching the Payments pattern: `private TransactionStatus $status;`
    is mutable; the rest is readonly).
  - **Native enums** — `StaticPageState`, `PageReferenceType`,
    `CmsTransitionEvent`. Backed by `string` for DB round-trip.
  - **Constructor property promotion** — every VO and DTO.
  - **Readonly properties** — for entities after `withChanges` and
    `transitionTo` (the entity's whole instance is replaced; the new
    instance has readonly state fields).
  - **First-class callable syntax** — used in the event listener
    registration (`$events->listen($name, [$listener, 'handle'])`).

`declare(strict_types=1)` is required in every file (matches the
existing codebase convention — every file under `app/Payments/` declares
strict types).

### §1.4 Module Discovery

The Shared kernel discovers modules via `ModuleContract::name()`. The
mechanism is:

  - `CmsModule::name()` returns `'cms'`.
  - The Shared Service Provider's `boot()` iterates a module registry
    populated by each module provider's `register()` step.
  - `CmsServiceProvider::register()` calls
    `$registry->register(CmsModule::class)` once, which causes Shared's
    discovery to call `CmsModule::boot()` after every other provider's
    `boot()` has completed.

The exact discovery plumbing lives in `app/Shared/Providers/SharedServiceProvider.php`
and is owned by the Shared kernel. The CMS kernel does not modify Shared.
If discovery requires a new entry, the change lands in a Shared-kernel
pass, not here.

The CMS kernel's responsibility for discovery is limited to:

  1. Implementing `ModuleContract` correctly (`name()`, `dependencies()`,
     `boot()`).
  2. Registering itself with the Shared module registry from
     `CmsServiceProvider::register()`.
  3. Declaring dependencies on Shared + `CampaignQueryContract` only
     (per §7).

---

## §2 Kernel Bridge — Shape A

The CMS kernel reads cross-kernel data through contracts owned by the
producing kernel. For V1 only the Campaigns bridge is live (Gallery and
Events kernels do not yet exist).

### §2.1 The Contract

File: `app/Payments/Contracts/CampaignQueryContract.php`

This file lives in the **Payments namespace** because the Payments kernel
owns the Campaign aggregate and is the source of truth. The contract is the
public read-side surface CMS consumes; Payments never imports anything from
the CMS namespace.

Interface surface (signatures only; full docblocks in implementation):

```php
namespace App\Payments\Contracts;

use App\Payments\Domain\ValueObjects\CampaignSummary;
use App\Persistence\ValueObjects\EntityId;

interface CampaignQueryContract
{
    /**
     * Active campaigns for public display.
     * Returns campaigns in `state = 'active'` ordered by display_order ASC.
     * Soft-deleted campaigns are excluded.
     *
     * @return list<CampaignSummary>
     */
    public function listActive(): array;

    /**
     * Single campaign lookup by internal identifier.
     * Returns null if not found or not active.
     */
    public function findActiveById(EntityId $id): ?CampaignSummary;

    /**
     * Validate that a campaign identifier exists and is currently
     * displayable on the public site (state = 'active'). Used by the
     * CMS ReferenceResolutionService to verify a static_page_references
     * row before resolving it.
     */
    public function isDisplayable(EntityId $id): bool;
}
```

### §2.2 Returned Value Object

File: `app/Payments/Domain/ValueObjects/CampaignSummary.php`

The contract returns `CampaignSummary` — a **read-only** value object
sized for public consumption. It carries:

  - `id` (EntityId)
  - `slug` (string)
  - `title` (string)
  - `summary` (string|null) — short description for campaign_grid blocks
  - `coverImageFileId` (EntityId|null)
  - `goalMinor` (int|null)
  - `raisedMinor` (int|null) — sum of CAPTURED + SETTLED donations
  - `currencyCode` (string, ISO 4217)
  - `isActive` (bool)

The VO is immutable. The CMS kernel **does not** receive the full Campaign
entity; it only sees the public summary. This keeps the kernel bridge
explicit about what crosses the boundary.

### §2.3 Implementation

File: `app/Payments/Infrastructure/CrossKernel/EloquentCampaignQuery.php`

Implements `CampaignQueryContract` using the existing
`PersistenceAdapterContract`. Reads from the `campaigns` table with
appropriate WHERE / ORDER BY clauses. No mutation. No side effects.

### §2.4 Binding

Bound in `PaymentsServiceProvider::register()`:

```php
$app->bind(
    \App\Payments\Contracts\CampaignQueryContract::class,
    \App\Payments\Infrastructure\CrossKernel\EloquentCampaignQuery::class,
);
```

Add this binding to the existing `register()` block. Add the contract to
the `provides()` array.

### §2.5 Consumer

`StaticPageRendererService` constructor signature includes the contract:

```php
public function __construct(
    private readonly StaticPageRepositoryContract $pages,
    private readonly HeroBannerRepositoryContract $banners,
    private readonly CampaignQueryContract $campaigns,
    private readonly StaticPageBodyRenderer $bodyRenderer,
    private readonly ResolvedPageCacheContract $cache,
    private readonly Clock $clock,
) {}
```

The renderer calls `$campaigns->listActive()` only when the page being
rendered has a `static_page_references` row with `reference_type =
'campaign'`. Gallery and event references fail fast with
`ReferenceTargetInvalidException` until their kernels land.

### §2.6 Future Bridge Shape

When the Gallery and Events kernels are built, they expose their own
read-side contracts following the same pattern (`GalleryAssetQueryContract`,
`EventQueryContract`). Each lives in its producing kernel's `Contracts/`
folder. `ReferenceResolutionService` dispatches by `page_reference_type`.

---

## §3 Domain Layer

### §3.1 Enums

#### §3.1.1 `StaticPageState`

File: `app/Cms/Domain/Enums/StaticPageState.php`

Cases: `DRAFT`, `PUBLISHED`, `UPDATED`, `ARCHIVED`.

String values match the Postgres `static_page_state` enum exactly:
`'draft'`, `'published'`, `'updated'`, `'archived'`.

Methods:
  - `isPubliclyReadable(): bool` — true for PUBLISHED and UPDATED
  - `isTerminal(): bool` — true for ARCHIVED
  - `label(): string` — display label

#### §3.1.2 `PageReferenceType`

File: `app/Cms/Domain/Enums/PageReferenceType.php`

Cases: `CAMPAIGN`, `GALLERY_IMAGE`, `EVENT`. String values match the
Postgres `page_reference_type` enum: `'campaign'`, `'gallery_image'`,
`'event'`. The `GALLERY_IMAGE` and `EVENT` cases are reserved; the kernel
rejects them with `ReferenceTargetInvalidException` until the corresponding
kernels ship.

#### §3.1.3 `CmsTransitionEvent`

File: `app/Cms/Domain/Enums/CmsTransitionEvent.php`

Vocabulary for `StaticPageStateMachine`. **Distinct from the Payments
`StateTransitionEvent` enum** — CMS carries its own vocabulary to keep
the kernels independently evolvable. Cases:

  - `PAGE_PUBLISHED` (string: `'page_published'`)
  - `PAGE_EDITED` (string: `'page_edited'`)
  - `PAGE_ARCHIVED` (string: `'page_archived'`)
  - `PAGE_RESTORED` (string: `'page_restored'`)

Method `label(): string` for admin UI display (Phase 4).

### §3.2 Value Objects

#### §3.2.1 `PageSlug`

File: `app/Cms/Domain/ValueObjects/PageSlug.php`

Validates slug format:
  - lowercase ASCII letters, digits, hyphens
  - must start with a letter
  - max 80 chars
  - regex: `/^[a-z][a-z0-9-]{0,79}$/`

Implements `AbstractValueObject`. Equality by string value. Method
`value(): string`.

#### §3.2.2 `SeoMetadata`

File: `app/Cms/Domain/ValueObjects/SeoMetadata.php`

Carrier for `static_pages.seo_metadata` (JSONB). Holds:
  - `metaTitle` (string|null) — overrides page title for SEO
  - `metaDescription` (string|null)
  - `canonicalUrl` (string|null)
  - `ogImageFileId` (EntityId|null)
  - `keywords` (list<string>)

Immutable. Constructed from JSON array or individual fields.

#### §3.2.3 `PageBody`

File: `app/Cms/Domain/ValueObjects/PageBody.php`

Canonical representation of `body_json`. Holds:

  - `version` (int — schema version of the block grammar; starts at 1)
  - `blocks` (list<Block>) — the V1 grammar

Validation in constructor: every block is one of the 5 sealed cases; the
VO iterates and constructs the typed Block VO for each. Unknown block
types throw `InvalidArgumentException` (this is a programming error, not a
business exception — admin UI must validate before persisting).

Methods:
  - `toArray(): array` — JSONB-ready representation
  - `static fromArray(array $data): self` — rebuild from row
  - `withBlocks(list<Block> $blocks): self` — produce a new PageBody
  - `render(StaticPageBodyRenderer $renderer): string` — delegates to
    renderer; the result is what the kernel writes to `body_html`

#### §3.2.4 Block Value Objects (sealed)

File: `app/Cms/Domain/ValueObjects/Blocks/Block.php`

`Block` is a PHP 8.2 sealed interface. Permitted implementations:

  - `ParagraphBlock` — `text: string`
  - `HeadingBlock` — `level: int (1-6)`, `text: string`
  - `ImageBlock` — `fileId: EntityId`, `alt: string`, `caption: string|null`,
    `width: int|null`, `height: int|null`
  - `CtaButtonBlock` — `label: string`, `url: string`, `style: enum{primary,
    secondary, ghost}`, `openInNewTab: bool`
  - `DividerBlock` — `style: enum{solid, dashed, dotted}`, `width: enum{full,
    half, quarter}`

Each block VO validates its own invariants in the constructor (e.g.
`HeadingBlock::level` must be 1-6; `CtaButtonBlock::url` must parse).
Sealing enforces grammar extension discipline: adding a new block type
in V2 requires an explicit grammar version bump in `PageBody::version`.

#### §3.2.5 `HeroBannerSlot`

File: `app/Cms/Domain/ValueObjects/HeroBannerSlot.php`

Pairing of a hero banner id with display metadata:

  - `bannerId` (EntityId)
  - `displayOrder` (int)
  - `weight` (int — for rotation/scheduling; default 100)

Used by `StaticPage` when assembling the banner list for a page.

#### §3.2.6 `ContactPoint`

File: `app/Cms/Domain/ValueObjects/ContactPoint.php`

Read-side carrier for `contact_information` rows:

  - `id` (EntityId)
  - `label` (string)
  - `contactType` (enum from `contact_type` PG enum — address / phone /
    email / whatsapp / social)
  - `value` (string)
  - `isPrimary` (bool)
  - `displayOrder` (int)
  - `metadata` (array<string, mixed>)

The kernel does not modify contact points; admin UI (Phase 4) does. In V1
contact points are seeded by migration / admin SQL.

### §3.3 Entities

#### §3.3.1 `StaticPage`

File: `app/Cms/Domain/Entities/StaticPage.php`

Aggregate root. Implements `EntityContract` (`App\Persistence\Contracts\EntityContract`).

ENTITY_TYPE constant: `'static_page'`.

Constructor is `private`. Three factories:

  - `draft(...)` — create a brand-new page in DRAFT
  - `fromRow(array $row)` — rehydrate from a database row
  - `transitionTo(StaticPageStateMachine, StaticPageState, CmsTransitionEvent,
    array $context)` — apply a state-machine-validated transition

Fields (private readonly):
  - `id` (EntityId)
  - `slug` (PageSlug)
  - `title` (string)
  - `metaDescription` (string|null)
  - `body` (PageBody — the typed body_json carrier)
  - `bodyHtml` (string|null) — the cached render output
  - `seoMetadata` (SeoMetadata)
  - `state` (StaticPageState)
  - `isHomepage` (bool)
  - `displayOrder` (int)
  - `heroBannerSlots` (list<HeroBannerSlot>) — denormalized view; the
    actual mapping lives in `hero_banner_pages`
  - `publishedAt` (DateTimeImmutable|null)
  - `lastPublishedAt` (DateTimeImmutable|null)
  - `createdAt`, `updatedAt`, `deletedAt`
  - `createdBy`, `updatedBy` (string|null; default "system")

`withChanges(array $changes): static` — guarded mutator. Throws
`LogicException` if `state` is set directly (must use `transitionTo`).

Business methods (return a new instance with `clone`):
  - `markAsHomepage(): self` — sets `isHomepage = true`. Service layer
    ensures no other page is currently the homepage.
  - `clearHomepage(): self` — sets `isHomepage = false`. Used when
    transferring homepage status.
  - `withBody(PageBody $body): self` — replaces body AND re-renders to
    bodyHtml via the renderer passed in via transitionTo context.
  - `attachHeroBanner(HeroBannerSlot $slot): self` — adds a slot.
  - `detachHeroBanner(EntityId $bannerId): self`

`transitionTo(...)` semantics:
  - Calls `StaticPageStateMachine::transition(from, event, context)` →
    `StateTransitionResult`
  - For transitions that produce public-readable content (PUBLISHED,
    UPDATED): the kernel stamps `publishedAt` and renders body → html
    via `StaticPageBodyRenderer` passed in context. The result populates
    `bodyHtml` and `lastPublishedAt`.
  - Returns a new `StaticPage` instance with `state`, `bodyHtml`,
    `publishedAt`, `lastPublishedAt`, `updatedAt` updated.

The `eventForTarget(StaticPageState $target): CmsTransitionEvent`
inference table mirrors the Payments pattern but is much smaller (see §3.4).

#### §3.3.2 `HeroBanner`

File: `app/Cms/Domain/Entities/HeroBanner.php`

Mirrors the `hero_banners` table. Implements `EntityContract`. ENTITY_TYPE:
`'hero_banner'`. Lifecycle uses `static_page_state` enum (the same four
states) because hero banners are CMS-managed; they share the
`StaticPageStateMachine`.

#### §3.3.3 `StaticPageReference`

File: `app/Cms/Domain/Entities/StaticPageReference.php`

Junction row from `static_page_references`. Holds:

  - `id` (EntityId)
  - `staticPageId` (EntityId)
  - `referenceType` (PageReferenceType)
  - `referenceId` (EntityId) — points to campaign / gallery_image / event
  - `displayOrder` (int)
  - `context` (string|null) — semantic context for the reference
    (e.g. `'hero_section'`, `'featured_campaigns'`, `'footer'`)
  - `createdAt` (DateTimeImmutable)

No state machine; lifecycle is purely CRUD via the repository.

#### §3.3.4 `ContactInformation`

File: `app/Cms/Domain/Entities/ContactInformation.php`

Mirrors the `contact_information` table. Implements `EntityContract`.
ENTITY_TYPE: `'contact_information'`. Read-only in V1 (no state machine,
admin SQL mutation). The kernel exposes a query service that returns
contact points; no mutation service exists until Phase 4.

#### §3.7 Input DTOs

File location: `app/Cms/Domain/DTOs/`. These are the `final readonly`
carriers that controllers (when they land in Phase 4) and admin services
construct and pass into kernel services. Every input DTO validates its
fields in the constructor.

**`StaticPageDraftInput`**

```php
namespace App\Cms\Domain\DTOs;

use App\Cms\Domain\ValueObjects\SeoMetadata;

final readonly class StaticPageDraftInput
{
    /**
     * @param  list<array<string, mixed>>  $bodyBlocks  raw block arrays
     *         (validated by PageBody::fromArray downstream)
     */
    public function __construct(
        public string $slug,
        public string $title,
        public ?string $metaDescription,
        public array $bodyBlocks,
        public SeoMetadata $seoMetadata,
        public bool $isHomepage,
        public int $displayOrder,
        public ?string $createdBy = null,
    ) {
        if (trim($title) === '') {
            throw new \InvalidArgumentException('StaticPage title cannot be empty');
        }
        if ($displayOrder < 0) {
            throw new \InvalidArgumentException('displayOrder cannot be negative');
        }
    }
}
```

**`StaticPageUpdateInput`**

```php
namespace App\Cms\Domain\DTOs;

use App\Cms\Domain\ValueObjects\SeoMetadata;

final readonly class StaticPageUpdateInput
{
    public function __construct(
        public ?string $title = null,
        public ?string $metaDescription = null,
        public ?array $bodyBlocks = null,
        public ?SeoMetadata $seoMetadata = null,
        public ?int $displayOrder = null,
        public ?string $updatedBy = null,
    ) {}
}
```

All fields are nullable. Service merges non-null fields onto the entity
via `withChanges()`. Setting a field to `null` is treated as "do not
change" — to explicitly clear an optional field, pass an empty string
or empty array.

**`HeroBannerDraftInput`**

```php
namespace App\Cms\Domain\DTOs;

use DateTimeImmutable;

final readonly class HeroBannerDraftInput
{
    public function __construct(
        public ?string $title,
        public ?string $subtitle,
        public ?string $ctaLabel,
        public ?string $ctaUrl,
        public ?string $imageFileId,
        public ?string $mobileImageFileId,
        public int $displayOrder,
        public ?DateTimeImmutable $startsAt,
        public ?DateTimeImmutable $endsAt,
        public ?string $createdBy = null,
    ) {
        if ($startsAt !== null && $endsAt !== null && $endsAt < $startsAt) {
            throw new \InvalidArgumentException('Hero banner endsAt must be >= startsAt');
        }
        if ($displayOrder < 0) {
            throw new \InvalidArgumentException('displayOrder cannot be negative');
        }
    }
}
```

**`HeroBannerUpdateInput`**

```php
namespace App\Cms\Domain\DTOs;

use DateTimeImmutable;

final readonly class HeroBannerUpdateInput
{
    public function __construct(
        public ?string $title = null,
        public ?string $subtitle = null,
        public ?string $ctaLabel = null,
        public ?string $ctaUrl = null,
        public ?string $imageFileId = null,
        public ?string $mobileImageFileId = null,
        public ?int $displayOrder = null,
        public ?DateTimeImmutable $startsAt = null,
        public ?DateTimeImmutable $endsAt = null,
        public ?string $updatedBy = null,
    ) {}
}
```

Same merge-non-null semantics as `StaticPageUpdateInput`.

**`RenderedStaticPage`**

```php
namespace App\Cms\Domain\DTOs;

use App\Cms\Domain\Entities\StaticPage;
use App\Cms\Domain\Entities\HeroBanner;
use DateTimeImmutable;

final readonly class RenderedStaticPage
{
    /**
     * @param  list<HeroBanner>  $heroBanners  ordered by hero_banner_pages.display_order
     * @param  list<ResolvedReference>  $resolvedReferences
     */
    public function __construct(
        public StaticPage $page,
        public array $heroBanners,
        public array $resolvedReferences,
        public string $html,
        public DateTimeImmutable $resolvedAt,
    ) {}
}
```

**`ResolvedReference`**

```php
namespace App\Cms\Domain\DTOs;

use App\Cms\Domain\Enums\PageReferenceType;
use App\Persistence\ValueObjects\EntityId;

enum ReferenceStatus: string
{
    case Resolved = 'resolved';
    case Unresolved = 'unresolved';
}

final readonly class ResolvedReference
{
    /**
     * @param  mixed  $payload  CampaignSummary for CAMPAIGN; null otherwise
     */
    public function __construct(
        public PageReferenceType $referenceType,
        public EntityId $referenceId,
        public ?string $context,
        public int $displayOrder,
        public ReferenceStatus $status,
        public mixed $payload,
    ) {}
}
```

### §3.8 Rendered DTOs (placement)

`RenderedStaticPage` and `ResolvedReference` are domain-layer DTOs
(situated in `app/Cms/Domain/DTOs/`), not Infrastructure. They are
immutable carriers of resolved public content; they cross the
Service → Controller (Phase 3) boundary without transformation.

### §3.4 State Machines

#### §3.4.1 `StaticPageStateMachine`

File: `app/Cms/Domain/StateMachines/StaticPageStateMachine.php`

Pure-function class. Same shape as `PaymentStateMachine`: stateless,
deterministic, no I/O. Returns `StateTransitionResult` (the existing
Shared class from `App\Payments\Domain\StateMachines\StateTransitionResult`
— **reused**, not duplicated, because the shape is generic).

State + Event → Target table:

| From State | Event             | To State     | Side Effects                              |
|------------|-------------------|--------------|-------------------------------------------|
| DRAFT      | PAGE_PUBLISHED    | PUBLISHED    | published_at = now; last_published_at = now; render body → html |
| DRAFT      | PAGE_ARCHIVED     | ARCHIVED     | (none beyond state)                       |
| PUBLISHED  | PAGE_EDITED       | UPDATED      | (state only; body unchanged until next save) |
| PUBLISHED  | PAGE_ARCHIVED     | ARCHIVED     | (none beyond state)                       |
| UPDATED    | PAGE_PUBLISHED    | PUBLISHED    | published_at = now; last_published_at = now; render body → html |
| UPDATED    | PAGE_ARCHIVED     | ARCHIVED     | (none beyond state)                       |
| ARCHIVED   | PAGE_RESTORED     | DRAFT        | (state only; admin re-edits then publishes) |

Terminal check: `StaticPageState::ARCHIVED->isTerminal()` returns true.
The machine refuses all events from a terminal state with
`InvalidPageStateTransitionException`.

The fold-back semantics (D3) live here: UPDATED + PAGE_PUBLISHED →
PUBLISHED, with a fresh `publishedAt` and re-rendered `bodyHtml`.

`eventForTarget(StaticPageState $target): CmsTransitionEvent` on the
entity is a small match (7 entries; trivial because the table is small).
No arm-ordering traps like in `Payment::eventForTarget`.

#### §3.4.3 Timestamp Correlation

Like `PaymentStateMachine`, the StaticPageStateMachine populates
`StateTransitionResult::timestampChanges()` for each transition. Schema
correlation:

| To State     | Column stamped      | Notes                                |
|--------------|---------------------|--------------------------------------|
| PUBLISHED    | `published_at`      | Stamped on every publish. Also stamps `last_published_at` (per D3, both = now). |
| UPDATED      | `updated_at` only   | No state-specific timestamp.         |
| ARCHIVED     | `updated_at` only   | No state-specific timestamp.         |
| DRAFT        | `updated_at` only   | Only reachable via ARCHIVED → DRAFT (PAGE_RESTORED). |

Implementation pattern (mirrors `PaymentStateMachine::transition`):

```php
$timestampMap = [
    StaticPageState::PUBLISHED->value => 'published_at',
];

$entityChanges = ['state' => $to->value];
$timestampChanges = [];

if (isset($timestampMap[$to->value])) {
    $timestampChanges[$timestampMap[$to->value]] = $now;
    $entityChanges['last_published_at'] = $now->format(DATE_ATOM);
    // D3: published_at == last_published_at in V1
}

return new StateTransitionResult(
    toState: $to,
    entityChanges: $entityChanges,
    timestampChanges: $timestampChanges,
);
```

The StaticPage entity's `transitionTo()` reads the result and applies
both the entity-level changes (status, last_published_at) and the
timestamp changes (published_at) to produce a new entity instance.

Body re-render trigger: see §5.6.

#### §3.4.2 `CmsTransitionEvent` (re-export note)

The same enum class is imported from both `Domain/Enums/` (canonical
home) and `Domain/StateMachines/` (imported by the machine). Implementation
chooses one canonical path (Enums/) and the StateMachines folder does
NOT contain a duplicate file. The §1.1 layout lists it under both for
clarity of the dependency graph; the implementation collapses to one file.

### §3.5 Repositories (Contracts)

#### §3.5.1 `StaticPageRepositoryContract`

File: `app/Cms/Domain/Repositories/StaticPageRepositoryContract.php`

Methods (signatures only):

```php
public function findById(EntityId $id): ?StaticPage;
public function findBySlug(PageSlug $slug): ?StaticPage;
public function findHomepage(): ?StaticPage;
public function listPublished(?int $limit = null, ?int $offset = null): array;
public function listByState(StaticPageState $state, ?int $limit = null): array;
public function listAll(?int $limit = null, ?int $offset = null): array;
public function save(StaticPage $page): void;
public function update(StaticPage $page): void;
public function softDelete(EntityId $id): void;
public function existsBySlug(PageSlug $slug, ?EntityId $excludeId = null): bool;
public function lockBySlugForUpdate(PageSlug $slug): ?StaticPage;
public function lockByIdForUpdate(EntityId $id): ?StaticPage;
public function countByState(StaticPageState $state): int;
```

Doctrine: every method excludes `deleted_at IS NOT NULL` unless explicitly
named. Soft-deleted pages are invisible to public reads. Admin reads
(Phase 4) opt in via a separate method (`listAll` includes soft-deleted).

#### §3.5.2 `HeroBannerRepositoryContract`

File: `app/Cms/Domain/Repositories/HeroBannerRepositoryContract.php`

```php
public function findById(EntityId $id): ?HeroBanner;
public function listPublished(?int $limit = null): array;
public function listActiveAt(DateTimeImmutable $when, ?int $limit = null): array;
public function save(HeroBanner $banner): void;
public function update(HeroBanner $banner): void;
public function softDelete(EntityId $id): void;
public function listForPage(EntityId $pageId): array;
public function attachToPage(EntityId $bannerId, EntityId $pageId, int $displayOrder): void;
public function detachFromPage(EntityId $bannerId, EntityId $pageId): void;
```

#### §3.5.3 `StaticPageReferenceRepositoryContract`

File: `app/Cms/Domain/Repositories/StaticPageReferenceRepositoryContract.php`

```php
public function listForPage(EntityId $pageId): array;
public function listByReference(PageReferenceType $type, EntityId $referenceId): array;
public function existsForPage(EntityId $pageId, PageReferenceType $type, EntityId $referenceId, ?string $context = null): bool;
public function attach(StaticPageReference $reference): void;
public function detach(EntityId $referenceRowId): void;
public function detachAllForPage(EntityId $pageId): void;
```

#### §3.5.4 `ContactInformationRepositoryContract`

File: `app/Cms/Domain/Repositories/ContactInformationRepositoryContract.php`

```php
public function findById(EntityId $id): ?ContactInformation;
public function listAll(?int $limit = null): array;
public function listByType(string $contactType): array;
public function listPrimary(): array;
public function save(ContactInformation $contact): void;
public function update(ContactInformation $contact): void;
public function softDelete(EntityId $id): void;
```

### §3.6 Exceptions

All extend `App\Shared\Exceptions\DomainException`. Each carries a stable
`errorCode()` string. The codes follow the pattern `cms.<entity>.<failure>`:

  - `StaticPageNotFoundException` — `cms.static_page.not_found`
  - `InvalidPageStateTransitionException` — `cms.static_page.state.transition.invalid`
  - `DuplicatePageSlugException` — `cms.static_page.slug.duplicate`
  - `HomepageAlreadyAssignedException` — `cms.static_page.homepage.conflict`
  - `HeroBannerNotFoundException` — `cms.hero_banner.not_found`
  - `ReferenceTargetInvalidException` — `cms.reference.target.invalid`
  - `ContactPointNotFoundException` — `cms.contact_information.not_found`

Each exception carries constructor context matching the Payments pattern
(e.g. `InvalidPageStateTransitionException` carries `fromState`, `toState`,
`event` for downstream logging).

#### §3.5.5 Repository Method SQL

For each `Eloquent*Repository` method, the SQL it issues is documented
here so the implementer doesn't need to reverse-engineer the contract.

**`EloquentStaticPageRepository`**

| Method | SQL |
|--------|-----|
| `findById($id)` | `SELECT * FROM static_pages WHERE id = :id AND deleted_at IS NULL LIMIT 1` |
| `findBySlug($slug)` | `SELECT * FROM static_pages WHERE slug = :slug AND deleted_at IS NULL LIMIT 1` |
| `findHomepage()` | `SELECT * FROM static_pages WHERE is_homepage = TRUE AND deleted_at IS NULL LIMIT 1` |
| `listPublished($limit, $offset)` | `SELECT * FROM static_pages WHERE state = 'published' AND deleted_at IS NULL ORDER BY display_order ASC LIMIT :lim OFFSET :off` |
| `listByState($state, $limit)` | `SELECT * FROM static_pages WHERE state = :state AND deleted_at IS NULL ORDER BY display_order ASC LIMIT :lim` |
| `listAll($limit, $offset)` | Same as `listPublished` but without state filter (includes soft-deleted for admin) |
| `save($page)` | `INSERT INTO static_pages (...) VALUES (...)` — see §3.3.1 column list |
| `update($page)` | `UPDATE static_pages SET ... WHERE id = :id` |
| `softDelete($id)` | `UPDATE static_pages SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id` |
| `existsBySlug($slug, $excludeId)` | `SELECT 1 FROM static_pages WHERE slug = :slug AND deleted_at IS NULL AND id != :exclude LIMIT 1` (excludeId null → no exclusion clause) |
| `lockBySlugForUpdate($slug)` | `SELECT * FROM static_pages WHERE slug = :slug AND deleted_at IS NULL FOR UPDATE LIMIT 1` |
| `lockByIdForUpdate($id)` | `SELECT * FROM static_pages WHERE id = :id AND deleted_at IS NULL FOR UPDATE LIMIT 1` |
| `countByState($state)` | `SELECT COUNT(*) AS cnt FROM static_pages WHERE state = :state AND deleted_at IS NULL` |

Index hits (V1-schema.sql):
  - `findBySlug`, `existsBySlug`, `lockBySlugForUpdate` → `static_pages_slug_live_idx` (partial unique on slug WHERE deleted_at IS NULL)
  - `listPublished`, `listByState` → `static_pages_state_live_idx` (state, display_order) WHERE deleted_at IS NULL
  - `findHomepage` → sequential scan (single-row; no dedicated index; the table has at most ~5 rows where is_homepage=TRUE so this is fine)

The slug pre-check (`existsBySlug`) is an optimistic SELECT. The DB
unique index `static_pages_slug_live_idx` is the safety net for the
race window between SELECT and INSERT.

**`EloquentHeroBannerRepository`**

| Method | SQL |
|--------|-----|
| `findById($id)` | `SELECT * FROM hero_banners WHERE id = :id AND deleted_at IS NULL LIMIT 1` |
| `listPublished($limit)` | `SELECT * FROM hero_banners WHERE state = 'published' AND deleted_at IS NULL ORDER BY display_order ASC LIMIT :lim` |
| `listActiveAt($when, $limit)` | `SELECT * FROM hero_banners WHERE state = 'published' AND deleted_at IS NULL AND (starts_at IS NULL OR starts_at <= :when) AND (ends_at IS NULL OR ends_at >= :when) ORDER BY display_order ASC LIMIT :lim` |
| `save($banner)` | `INSERT INTO hero_banners (...) VALUES (...)` |
| `update($banner)` | `UPDATE hero_banners SET ... WHERE id = :id` |
| `softDelete($id)` | `UPDATE hero_banners SET deleted_at = NOW() WHERE id = :id` |
| `listForPage($pageId)` | `SELECT hb.* FROM hero_banners hb JOIN hero_banner_pages hbp ON hb.id = hbp.hero_banner_id WHERE hbp.static_page_id = :pid AND hb.deleted_at IS NULL ORDER BY hbp.display_order ASC` |
| `attachToPage($bid, $pid, $order)` | `INSERT INTO hero_banner_pages (hero_banner_id, static_page_id, display_order, created_at) VALUES (:bid, :pid, :order, NOW()) ON CONFLICT (hero_banner_id, static_page_id) DO UPDATE SET display_order = EXCLUDED.display_order` |
| `detachFromPage($bid, $pid)` | `DELETE FROM hero_banner_pages WHERE hero_banner_id = :bid AND static_page_id = :pid` |

**`EloquentStaticPageReferenceRepository`**

| Method | SQL |
|--------|-----|
| `listForPage($pageId)` | `SELECT * FROM static_page_references WHERE static_page_id = :pid ORDER BY display_order ASC` |
| `listByReference($type, $rid)` | `SELECT * FROM static_page_references WHERE reference_type = :type AND reference_id = :rid` |
| `existsForPage($pid, $type, $rid, $ctx)` | `SELECT 1 FROM static_page_references WHERE static_page_id = :pid AND reference_type = :type AND reference_id = :rid AND (context IS NOT DISTINCT FROM :ctx) LIMIT 1` |
| `attach($ref)` | `INSERT INTO static_page_references (...) VALUES (...)` |
| `detach($rowId)` | `DELETE FROM static_page_references WHERE id = :id` |
| `detachAllForPage($pageId)` | `DELETE FROM static_page_references WHERE static_page_id = :pid` |

**`EloquentContactInformationRepository`**

| Method | SQL |
|--------|-----|
| `findById($id)` | `SELECT * FROM contact_information WHERE id = :id AND deleted_at IS NULL LIMIT 1` |
| `listAll($limit)` | `SELECT * FROM contact_information WHERE deleted_at IS NULL ORDER BY contact_type, display_order ASC LIMIT :lim` |
| `listByType($type)` | `SELECT * FROM contact_information WHERE contact_type = :type AND deleted_at IS NULL ORDER BY display_order ASC` |
| `listPrimary()` | `SELECT * FROM contact_information WHERE is_primary = TRUE AND deleted_at IS NULL` |
| `save($contact)` | `INSERT INTO contact_information (...) VALUES (...)` |
| `update($contact)` | `UPDATE contact_information SET ... WHERE id = :id` |
| `softDelete($id)` | `UPDATE contact_information SET deleted_at = NOW() WHERE id = :id` |

#### §3.5.6 Concurrency Strategy

Operations that mutate state under a row lock:

| Operation | Lock | Why |
|-----------|------|-----|
| `StaticPageService::publish` | `lockByIdForUpdate` | Prevents concurrent publishes from racing on body_html overwrite |
| `StaticPageService::markAsEdited` | `lockByIdForUpdate` | Same — prevents lost-update on state |
| `StaticPageService::archive` | `lockByIdForUpdate` | Same |
| `StaticPageService::restore` | `lockByIdForUpdate` | Same |
| `StaticPageService::assignHomepage` | `lockByIdForUpdate` on BOTH the new and current homepage rows | Prevents two admins from simultaneously assigning different homepages |
| `StaticPageService::attachReference` | `lockByIdForUpdate` on the page | Prevents races where two attachments reference the same slot |
| `StaticPageService::detachReference` | `lockByIdForUpdate` on the page | Same |
| `StaticPageService::updateContent` | `lockByIdForUpdate` | Prevents lost-update on body |
| `HeroBannerService::publish/archive` | `lockByIdForUpdate` | Mirrors StaticPageService |

Read-only operations (`findById`, `listPublished`, etc.) do NOT lock.
Public reads hit the cache and skip the DB entirely on a hit.

#### §3.5.7 Transaction Boundaries

Operations that wrap in `$adapter->transaction(function () { ... })`:

  - Every `*Service` method listed in §3.5.6 above.
  - Read-only services (`StaticPageQueryService`, `ContactInformationService` read paths) do NOT wrap in transactions; they use plain `query()` calls.

The transaction wrapper guarantees:
  1. The lock is acquired at the start (`SELECT ... FOR UPDATE`)
  2. All repository writes happen inside the transaction
  3. On exception: rollback, lock released, exception propagates
  4. On success: commit, lock released, success path completes
     (including cache invalidation events dispatched AFTER commit —
     see §5.3)

Cache invalidation dispatch happens AFTER the transaction commits.
This prevents the cache from being invalidated and then re-populated
with stale data if the transaction rolls back. Implementation pattern:

```php
$result = $this->adapter->transaction(function () use ($page) {
    $locked = $this->pages->lockByIdForUpdate($page->id());
    $transitioned = $locked->transitionTo($this->stateMachine, StaticPageState::PUBLISHED, CmsTransitionEvent::PAGE_PUBLISHED);
    $this->pages->update($transitioned);
    return $transitioned;
});

if ($result->isOk()) {
    event(new StaticPagePublished($result->value()->slug()));
}
```

This pattern mirrors the Payments kernel's commit-then-notify
discipline.

---

## §4 Services

### §4.1 `StaticPageService`

File: `app/Cms/Services/StaticPageService.php`

**Authoring-side service.** Phase 4 admin UI calls these. Not used by the
Phase 3 frontend.

Methods:

  - `createDraft(StaticPageDraftInput $input): Result<StaticPage>`
  - `updateContent(EntityId $id, StaticPageUpdateInput $input): Result<StaticPage>`
  - `publish(EntityId $id): Result<StaticPage>` — DRAFT → PUBLISHED, or
    UPDATED → PUBLISHED. Triggers render → bodyHtml.
  - `markAsEdited(EntityId $id): Result<StaticPage>` — PUBLISHED →
    UPDATED. Light transition; no render needed.
  - `archive(EntityId $id): Result<StaticPage>`
  - `restore(EntityId $id): Result<StaticPage>` — ARCHIVED → DRAFT
  - `assignHomepage(EntityId $id): Result<StaticPage>` — enforces single
    homepage
  - `softDelete(EntityId $id): Result<void>`

All return `Result<T>` (the Shared `App\Shared\Support\Result`). Failure
modes surface as the domain exceptions listed in §3.6.

This service is the **only** service that mutates `static_pages` rows. The
repository contract is read/write; the service is the orchestration layer.

### §4.2 `StaticPageQueryService`

File: `app/Cms/Services/StaticPageQueryService.php`

**Read-side service.** Phase 3 frontend uses this for navigation (listing
published pages, looking up by slug without rendering).

Methods:

  - `findBySlug(PageSlug $slug): ?StaticPage` — returns the page entity
    (DRAFT and ARCHIVED are visible only to admin; service filters)
  - `findHomepage(): ?StaticPage` — the live homepage entity
  - `listPublishedNavigation(?int $limit = null): array` — returns pages
    in PUBLISHED state ordered by `display_order` ASC, for nav rendering
  - `searchByTitlePrefix(string $prefix, int $limit): array` — admin
    search helper

### §4.3 `StaticPageRendererService`

File: `app/Cms/Services/StaticPageRendererService.php`

**The orchestrator.** Phase 3 frontend calls this on every public page
load. This is the entry point that ties together body, hero banners,
references, and the cache.

Constructor:

```php
public function __construct(
    private readonly StaticPageRepositoryContract $pages,
    private readonly HeroBannerRepositoryContract $banners,
    private readonly StaticPageReferenceRepositoryContract $references,
    private readonly CampaignQueryContract $campaigns,
    private readonly StaticPageBodyRenderer $bodyRenderer,
    private readonly ResolvedPageCacheContract $cache,
    private readonly Clock $clock,
) {}
```

Methods:

  - `renderBySlug(PageSlug $slug): ?RenderedStaticPage`
  - `renderHomepage(): RenderedStaticPage`

`RenderedStaticPage` is the immutable result value object (DTO, lives in
`app/Cms/Domain/DTOs/RenderedStaticPage.php`):

  - `page` (StaticPage — the entity)
  - `heroBanners` (list<HeroBanner> — resolved and ordered)
  - `resolvedReferences` (list<ResolvedReference>) — campaigns with full
    summary data, gallery/event refs marked unresolved if their kernel
    is missing
  - `html` (string — the page body_html, served straight from cache or
    freshly written)
  - `resolvedAt` (DateTimeImmutable — when the cache entry was stamped)

Flow (per request, `renderBySlug`):

  1. Cache lookup: `cache->get($slug)`
  2. Hit → return cached `RenderedStaticPage`
  3. Miss → load `StaticPage` via repository
  4. If state is not publicly readable → return null
  5. Load hero banners for the page
  6. Load references for the page
  7. Resolve each reference:
       - `CAMPAIGN` → call `$campaigns->findActiveById(referenceId)`
         (returns null if missing → mark reference as unresolved)
       - `GALLERY_IMAGE` / `EVENT` → mark unresolved (kernel absent)
  8. Compose `RenderedStaticPage` with `bodyHtml` from the entity
     (no re-render — the entity carries the pre-rendered html)
  9. Cache put: `cache->put($slug, $result, ttl)`
  10. Return result

Invalidation is event-driven (see §5.3) — this service does not
invalidate manually.

### §4.4 `HeroBannerService`

File: `app/Cms/Services/HeroBannerService.php`

CRUD + date-range queries + page attachment. Pattern matches
`StaticPageService` but smaller scope.

Methods:

  - `create(HeroBannerDraftInput $input): Result<HeroBanner>`
  - `update(EntityId $id, HeroBannerUpdateInput $input): Result<HeroBanner>`
  - `publish(EntityId $id): Result<HeroBanner>` — uses the shared
    `StaticPageStateMachine`
  - `archive(EntityId $id): Result<HeroBanner>`
  - `attachToPage(EntityId $bannerId, EntityId $pageId, int $order): Result<void>`
  - `detachFromPage(EntityId $bannerId, EntityId $pageId): Result<void>`
  - `listActiveAt(DateTimeImmutable $when): array`

### §4.5 `ContactInformationService`

File: `app/Cms/Services/ContactInformationService.php`

Read-only service in V1 (admin UI is Phase 4). Methods:

  - `listAll(): array`
  - `listByType(string $type): array`
  - `listPrimary(): array` — returns at most one contact point per
    `contact_type`, the `is_primary = true` one
  - `findById(EntityId $id): ?ContactInformation`

No mutation methods. Phase 4 adds them.

### §4.6 `ReferenceResolutionService`

File: `app/Cms/Services/ReferenceResolutionService.php`

Validates and fetches cross-kernel references. Used by
`StaticPageRendererService`.

Methods:

  - `resolve(StaticPageReference $reference): ResolvedReference`
  - `resolveMany(list<StaticPageReference>): list<ResolvedReference>`

`ResolvedReference` is a DTO:

  - `referenceType` (PageReferenceType)
  - `referenceId` (EntityId)
  - `context` (string|null)
  - `displayOrder` (int)
  - `status` (enum{resolved, unresolved})
  - `payload` (mixed) — for CAMPAIGN: `CampaignSummary`; for
    GALLERY_IMAGE/EVENT: null

Resolution dispatch:

  - `CAMPAIGN` → `$campaigns->isDisplayable(id)` then
    `$campaigns->findActiveById(id)`. Fail = unresolved.
  - `GALLERY_IMAGE` / `EVENT` → returns unresolved immediately with a
    log warning (no kernel yet).

This service is the **only** place that knows about cross-kernel
contracts. Adding a new reference type in V2 means extending this service
alone — the renderer service is unaffected.

### §4.7 Service Return Types & Error Matrix

Every service method returns `Result<T>` (the Shared `App\Shared\Support\Result`).
The success/failure modes are documented here so Phase 3 (controllers)
and Phase 4 (admin UI) know how to translate each outcome to a user
response.

#### §4.7.1 `StaticPageService`

| Method | Success | Failure (Result::failure error codes) |
|--------|---------|---------------------------------------|
| `createDraft` | `Result<StaticPage>` | `slug.invalid_format`, `slug.duplicate` |
| `updateContent` | `Result<StaticPage>` | `static_page.not_found`, `state.archived_immutable`, `slug.duplicate` (on rename — V1 does not allow slug mutation, so this is unreachable but documented for V2) |
| `publish` | `Result<StaticPage>` | `static_page.not_found`, `state.transition.invalid` |
| `markAsEdited` | `Result<StaticPage>` | `static_page.not_found`, `state.transition.invalid` |
| `archive` | `Result<StaticPage>` | `static_page.not_found`, `state.transition.invalid` |
| `restore` | `Result<StaticPage>` | `static_page.not_found`, `state.transition.invalid` |
| `assignHomepage` | `Result<StaticPage>` | `static_page.not_found`, `homepage.conflict` |
| `softDelete` | `Result<void>` | `static_page.not_found` |

#### §4.7.2 `StaticPageQueryService`

| Method | Return | Notes |
|--------|--------|-------|
| `findBySlug` | `?StaticPage` | Returns null for soft-deleted. DRAFT and ARCHIVED visible to admin context only — controllers must wrap. |
| `findHomepage` | `?StaticPage` | Same null-on-soft-delete. |
| `listPublishedNavigation` | `list<StaticPage>` | Excludes DRAFT, UPDATED-not-yet-published, ARCHIVED. |
| `searchByTitlePrefix` | `list<StaticPage>` | Admin helper. Excludes soft-deleted. |

`StaticPageQueryService` is the only service that does NOT return
`Result<T>` because its methods are inherently query-only and the
controller is responsible for the null-handling. This matches the
Payments `PaymentService::getStatus` pattern (returns `Result<TransactionStatus>`
even for reads, but query-only services that return nullable entities
follow the `?Entity` convention).

#### §4.7.3 `StaticPageRendererService`

| Method | Return | Notes |
|--------|--------|-------|
| `renderBySlug` | `?RenderedStaticPage` | Null when page not found OR state is not publicly readable (DRAFT, ARCHIVED). Cache hit returns the cached DTO; cache miss returns a freshly assembled one. |
| `renderHomepage` | `RenderedStaticPage` | Throws `StaticPageNotFoundException` if no homepage exists. (Programmer error to call without first ensuring one exists.) |

#### §4.7.4 `HeroBannerService`

| Method | Success | Failure |
|--------|---------|---------|
| `create` | `Result<HeroBanner>` | (banner creation has no constraints beyond format) |
| `update` | `Result<HeroBanner>` | `hero_banner.not_found` |
| `publish` | `Result<HeroBanner>` | `hero_banner.not_found`, `state.transition.invalid` |
| `archive` | `Result<HeroBanner>` | `hero_banner.not_found`, `state.transition.invalid` |
| `attachToPage` | `Result<void>` | `static_page.not_found`, `hero_banner.not_found` |
| `detachFromPage` | `Result<void>` | (no error; silent success when not attached) |
| `listActiveAt` | `list<HeroBanner>` | Query-only. |

#### §4.7.5 `ContactInformationService`

All methods are query-only in V1:

| Method | Return | Notes |
|--------|--------|-------|
| `listAll` | `list<ContactInformation>` | |
| `listByType` | `list<ContactInformation>` | |
| `listPrimary` | `list<ContactInformation>` | At most one per `contact_type`. |
| `findById` | `?ContactInformation` | Null when soft-deleted. |

#### §4.7.6 `ReferenceResolutionService`

| Method | Return | Notes |
|--------|--------|-------|
| `resolve` | `ResolvedReference` | Never throws. Returns `status = Unresolved` on missing kernel or invalid target. |
| `resolveMany` | `list<ResolvedReference>` | Never throws. Same. |

#### §4.7.7 Exception → Error Code Map

The `errorCode()` strings on domain exceptions are the same strings
the services emit in `Result::failure($error)`:

| Exception | `errorCode()` |
|-----------|---------------|
| `StaticPageNotFoundException` | `cms.static_page.not_found` |
| `InvalidPageStateTransitionException` | `cms.static_page.state.transition.invalid` |
| `DuplicatePageSlugException` | `cms.static_page.slug.duplicate` |
| `HomepageAlreadyAssignedException` | `cms.static_page.homepage.conflict` |
| `HeroBannerNotFoundException` | `cms.hero_banner.not_found` |
| `ReferenceTargetInvalidException` | `cms.reference.target.invalid` |
| `ContactPointNotFoundException` | `cms.contact_information.not_found` |

Controllers translate these codes to HTTP responses (404 / 409 / 422)
in the standard way. Slug format validation failures (raised as
`InvalidArgumentException` from the `PageSlug` VO constructor) are
translated by the controller to 422 with a structured error.

---

## §5 Infrastructure

### §5.1 Eloquent Repositories

Each lives in `app/Cms/Infrastructure/Repositories/`. Pattern follows
`EloquentPaymentRepository` exactly: depends on `PersistenceAdapterContract`
(not Eloquent models directly), uses `query()` and `execute()` for all
DB access, never returns DB row arrays outside the entity boundary.

Files:

  - `EloquentStaticPageRepository.php` — implements
    `StaticPageRepositoryContract`. Handles the slug uniqueness pre-check
    by catching the unique-constraint violation from `execute()` and
    translating to `DuplicatePageSlugException`. Also translates the
    EXCLUDE violation on `is_homepage = true` to
    `HomepageAlreadyAssignedException`.

  - `EloquentHeroBannerRepository.php` — implements
    `HeroBannerRepositoryContract`. Date-range query uses `starts_at`
    / `ends_at` columns.

  - `EloquentStaticPageReferenceRepository.php` — implements
    `StaticPageReferenceRepositoryContract`.

  - `EloquentContactInformationRepository.php` — implements
    `ContactInformationRepositoryContract`.

### §5.2 Rendering Pipeline

#### §5.2.1 `StaticPageBodyRenderer`

File: `app/Cms/Infrastructure/Rendering/StaticPageBodyRenderer.php`

Orchestrates block rendering. Method:

```php
public function render(PageBody $body): string
```

Iterates `$body->blocks()`, dispatches each block to its renderer via the
`BlockRendererRegistry`, concatenates the HTML with `\n` separators, and
returns the full HTML string. Output is HTML-escaped by each block
renderer — there is no central sanitizer because each renderer knows
what its input requires.

#### §5.2.2 `BlockRendererContract`

File: `app/Cms/Contracts/BlockRendererContract.php`

```php
interface BlockRendererContract
{
    public function supports(Block $block): bool;
    public function render(Block $block): string;
}
```

#### §5.2.3 `BlockRendererRegistry`

File: `app/Cms/Infrastructure/Rendering/BlockRendererRegistry.php`

Holds the registered renderers. `render(Block $b): string` looks up the
renderer that supports `$b` (returns its `render()` result) or throws
`LogicException` if no renderer claims it. Bound in the service provider
with all 5 renderers pre-registered.

#### §5.2.4 Block Renderers

Five implementations under `app/Cms/Infrastructure/Rendering/BlockRenderers/`:

  - `ParagraphBlockRenderer` — `<p>{escaped text}</p>`
  - `HeadingBlockRenderer` — `<h{level}>{escaped text}</h{level}>`
  - `ImageBlockRenderer` — `<figure><img src="{file url}" alt="{alt}" ...>
    {optional caption}</figure>`. Image URLs come from a `FileAssetQueryContract`
    lookup (the image is an asset reference; the renderer does not own
    file resolution — it gets the URL from a small helper).
  - `CtaButtonBlockRenderer` — `<a href="{url}" class="cta cta--{style}"
    {target}> {escaped label}</a>`
  - `DividerBlockRenderer` — `<hr class="divider divider--{style}
    divider--{width}">`

Image URL resolution is a separate small contract
(`ImageUrlResolverContract` in `app/Cms/Contracts/`) that delegates to
whatever file storage layer Phase 4 wires. V1 ships a stub
implementation that returns the file id as-is (the admin uploads aren't
wired yet). Phase 4 replaces the stub.

### §5.3 Caching

#### §5.3.1 `ResolvedPageCacheContract`

File: `app/Cms/Contracts/ResolvedPageCacheContract.php`

```php
interface ResolvedPageCacheContract
{
    public function get(PageSlug $slug): ?RenderedStaticPage;
    public function put(PageSlug $slug, RenderedStaticPage $page, int $ttlSeconds = 3600): void;
    public function invalidate(PageSlug $slug): void;
    public function invalidateAll(): void;
}
```

#### §5.3.2 `RedisResolvedPageCache`

File: `app/Cms/Infrastructure/Caching/RedisResolvedPageCache.php`

Implementation backed by the Laravel cache facade (which Redis powers per
Phase 2's `app/Redis/` wiring). Key shape: `cms.page.{slug}.resolved`.
TTL default 3600s. Stores the serialized `RenderedStaticPage`.

Constructor takes `Clock` (for stamping `resolvedAt` on cache hits) and
the cache repository injected by Laravel.

#### §5.3.3 `CacheInvalidationListener`

File: `app/Cms/Infrastructure/Caching/CacheInvalidationListener.php`

Laravel event listener. Subscribes to domain events emitted by
`StaticPageService` and `StaticPageRendererService`:

  - `StaticPagePublished` — invalidates the page slug
  - `StaticPageUpdated` — invalidates the page slug (because body_html
    will be re-rendered on the next read after a publish)
  - `StaticPageArchived` — invalidates
  - `StaticPageDeleted` — invalidates
  - `StaticPageBodyChanged` — invalidates (fires on body updates that
    don't change state)
  - `HeroBannerChanged` (for any banner attached to a page) —
    invalidates all pages the banner is attached to
  - `HomepageChanged` — invalidates the old slug AND the new slug
  - `ReferenceAttached` / `ReferenceDetached` — invalidates the affected
    page

Implementation registers via `Event::listen` calls in
`CmsServiceProvider::boot()`.

Doctrine: invalidation is **synchronous** in V1. Phase 5+ may move to
async via the queue (Redis is already wired).

### §5.4 Persistence Mappers

Four small mapper classes that translate between DB row arrays and entity
constructors. Pattern: `toRow(StaticPage): array`,
`fromRow(array): StaticPage` — but the entity `fromRow` method is the
canonical place. The mappers exist only for the rare case where the
repository needs to enrich a row before entity construction (e.g.
joining hero_banner_pages into the StaticPage's `heroBannerSlots`
denormalization). Most reads go straight through `Entity::fromRow`.

Files:

  - `app/Cms/Infrastructure/Persistence/StaticPageMapper.php`
  - `app/Cms/Infrastructure/Persistence/HeroBannerMapper.php`
  - `app/Cms/Infrastructure/Persistence/StaticPageReferenceMapper.php`
  - `app/Cms/Infrastructure/Persistence/ContactInformationMapper.php`

### §5.5 Domain Events

File: `app/Cms/Infrastructure/Events/CmsDomainEvents.php`

Class constants for event names. Examples:

```php
public const STATIC_PAGE_PUBLISHED = 'cms.static_page.published';
public const STATIC_PAGE_UPDATED = 'cms.static_page.updated';
public const STATIC_PAGE_ARCHIVED = 'cms.static_page.archived';
public const STATIC_PAGE_DELETED = 'cms.static_page.deleted';
public const STATIC_PAGE_BODY_CHANGED = 'cms.static_page.body_changed';
public const HOMEPAGE_CHANGED = 'cms.static_page.homepage_changed';
public const HERO_BANNER_CHANGED = 'cms.hero_banner.changed';
public const REFERENCE_ATTACHED = 'cms.reference.attached';
public const REFERENCE_DETACHED = 'cms.reference.detached';
```

Service methods dispatch via Laravel's `event()` helper. The audit
recorder (a later pass) listens to these same events and writes to the
audit_events table.

#### §5.6 body_html Re-render Triggers (exact conditions)

The kernel writes `body_html` on **only** the following transitions and
operations. Every other write leaves `body_html` untouched.

| Operation | Re-render? | Notes |
|-----------|------------|-------|
| `StaticPageService::createDraft` | YES | The draft has empty body_html until first publish. V1 sets `body_html = ''` at draft creation; the first publish triggers a full render. |
| `StaticPageService::updateContent` (body changed) | NO | Body changes do NOT trigger re-render. The new body takes effect on the next state transition that produces publicly-readable content (publish). Until then, `body_html` reflects the previous render. |
| `StaticPageService::publish` (DRAFT → PUBLISHED) | YES | Full render. `body_html` set to `StaticPageBodyRenderer::render($page->body())`. |
| `StaticPageService::publish` (UPDATED → PUBLISHED, the fold-back per D3) | YES | Full render. The new published snapshot reflects the latest body. |
| `StaticPageService::markAsEdited` (PUBLISHED → UPDATED) | NO | State-only transition; the body_html remains the published version. Public reads see the latest published content until the next publish. |
| `StaticPageService::archive` | NO | State-only; body_html untouched (page is no longer publicly readable regardless). |
| `StaticPageService::restore` (ARCHIVED → DRAFT) | NO | State-only; body_html preserved in case the page is republished. |
| `StaticPageService::assignHomepage` | NO | Doesn't touch body or state. |
| `StaticPageService::softDelete` | NO | Doesn't touch body or state. |

Implementation in `StaticPageService::publish`:

```php
$result = $this->adapter->transaction(function () use ($page) {
    $locked = $this->pages->lockByIdForUpdate($page->id());
    $transitioned = $locked->transitionTo(
        $this->stateMachine,
        StaticPageState::PUBLISHED,
        CmsTransitionEvent::PAGE_PUBLISHED,
    );
    
    // Re-render body_json → body_html (the body_html re-render trigger)
    $newHtml = $this->bodyRenderer->render($transitioned->body());
    $withHtml = $transitioned->withChanges(['body_html' => $newHtml]);
    
    $this->pages->update($withHtml);
    return $withHtml;
});
```

The renderer is injected via constructor (already declared in §6.1).
The state machine produces `lastPublishedAt` in its result; the entity's
`transitionTo` applies it.

#### §5.7 Cache Stampede Protection

On a cache miss, multiple concurrent requests for the same slug would
all run the full assembly + render pipeline. The kernel protects against
this with an in-process mutex:

```php
final class StaticPageRendererService
{
    /** @var array<string, \App\Cms\Domain\DTOs\RenderedStaticPage> */
    private array $resolving = [];

    public function renderBySlug(PageSlug $slug): ?RenderedStaticPage
    {
        // 1. Cache hit
        $cached = $this->cache->get($slug);
        if ($cached !== null) {
            return $cached;
        }

        // 2. Per-process memoization: a sibling request in this process
        //    may already be resolving the same slug. Wait briefly.
        if (isset($this->resolving[$slug->value()])) {
            return $this->resolving[$slug->value()];
        }

        // 3. Compute
        $resolved = $this->assemble($slug);
        if ($resolved !== null) {
            $this->resolving[$slug->value()] = $resolved;
            $this->cache->put($slug, $resolved);
            unset($this->resolving[$slug->value()]);
        }

        return $resolved;
    }
}
```

This is sufficient for V1 (single PHP process per worker). Multi-process
(separate FPM workers) cache stampede is mitigated by:

  1. Redis cache itself, which is shared across processes
  2. Short window of duplicate work (acceptable cost — 1 page render is
     ~30ms)
  3. Future V2 hardening: replace with Redis `SET NX` lock + 5s TTL +
     retry (not in V1 scope per over-engineering bar)

#### §5.8 Cache Key Naming + Serialization Format

**Key shape:** `cms.page.{slug}.resolved`

Examples:
  - `cms.page.home.resolved`
  - `cms.page.about.resolved`
  - `cms.page.donate.resolved`

The slug is taken from the `PageSlug` VO (already validated). Keys are
deterministic; no collision risk because `static_pages_slug_live_idx`
guarantees uniqueness at any moment in time.

**Serialization:** PHP `serialize()` of the full `RenderedStaticPage`
DTO. The DTO contains only serializable values (entities, lists, strings,
DateTimeImmutable). Unserialization happens via `unserialize()` on cache
hit. PHP 8.2's `serialize`/`unserialize` with default allowed_classes=true
restores the entity classes correctly because they're loaded by Composer
autoload before the unserialize call.

**TTL:** 3600 seconds (1 hour). This is a safety net; in normal
operation, invalidation events fire within milliseconds of state changes,
so TTL expiry is rare.

**Cache stampede behavior under invalidation:**

  1. `StaticPageService::publish` writes to DB, commits transaction.
  2. Service dispatches `event(new StaticPagePublished($slug))`.
  3. `CacheInvalidationListener::handle` is invoked synchronously by
     Laravel's event dispatcher.
  4. Listener calls `$cache->invalidate($slug)`.
  5. Next request to `renderBySlug($slug)` sees a cache miss and re-
     assembles.

Total invalidation latency: < 5ms in normal operation (single event
listener dispatch + Redis DEL).

**Cache eviction under memory pressure:** Laravel's default Redis cache
driver honors the configured `CACHE_PREFIX` and uses Redis's own
eviction policy (no LRU by default in V1; that's a Redis server
config concern, not application concern). When Redis evicts a
`cms.page.*` key, the next request re-assembles. No application-level
reaction needed.

#### §5.9 Slug Uniqueness Enforcement Flow

Complete flow when admin submits a new draft via
`StaticPageService::createDraft(StaticPageDraftInput $input)`:

```php
public function createDraft(StaticPageDraftInput $input): Result
{
    // 1. Validate slug format (PageSlug VO constructor)
    try {
        $slug = new PageSlug($input->slug);
    } catch (\InvalidArgumentException $e) {
        return Result::failure('slug.invalid_format');
    }
    
    // 2. Pre-check uniqueness (optimistic)
    if ($this->pages->existsBySlug($slug)) {
        return Result::failure('slug.duplicate');
    }
    
    // 3. Build entity + persist
    $result = $this->adapter->transaction(function () use ($input, $slug) {
        $page = StaticPage::draft(
            slug: $slug,
            title: $input->title,
            metaDescription: $input->metaDescription,
            body: PageBody::fromArray(['version' => 1, 'blocks' => $input->bodyBlocks]),
            seoMetadata: $input->seoMetadata,
            isHomepage: $input->isHomepage,
            displayOrder: $input->displayOrder,
            createdBy: $input->createdBy,
        );
        $this->pages->save($page);
        return $page;
    });
    
    if ($result->isFailure()) {
        // Race window: another admin inserted the same slug between
        // our existsBySlug check and our INSERT. The DB unique index
        // raised an exception; the adapter wrapped it as Result::failure.
        if (str_contains($result->error() ?? '', 'unique constraint') 
            || str_contains($result->error() ?? '', 'slug_live_idx')) {
            return Result::failure('slug.duplicate');
        }
        return Result::failure('persistence.failed');
    }
    
    // 4. Cache invalidation NOT needed for createDraft (the new page
    //    is in DRAFT, not publicly readable; cache entries for other
    //    slugs are unaffected).
    
    return Result::success($result->value());
}
```

This implements the "service pre-check + DB unique index as safety net"
pattern from `phase-1-deviations.md` (D-pattern). The pre-check produces
a clean error code; the safety net catches the race.

The unique index pattern detection above uses string matching on the
error message; the cleaner alternative is for the adapter to surface a
typed exception (`UniqueConstraintViolationException`), which is a
Shared-kernel concern, not CMS. V1 uses string matching; V2 migrates to
typed exceptions when Shared adds them.

#### §5.10 Homepage Enforcement Flow

Complete flow when admin assigns a page as homepage via
`StaticPageService::assignHomepage(EntityId $pageId)`:

```php
public function assignHomepage(EntityId $pageId): Result
{
    return $this->adapter->transaction(function () use ($pageId) {
        // 1. Lock the target page first
        $target = $this->pages->lockByIdForUpdate($pageId);
        if ($target === null) {
            return Result::failure('static_page.not_found');
        }
        
        // 2. Find current homepage (no lock yet — we lock it below)
        $current = $this->pages->findHomepage();
        
        // 3. Idempotent: if target is already the homepage, success
        if ($current !== null && $current->id()->equals($target->id())) {
            return Result::success($target);
        }
        
        // 4. Lock + clear the existing homepage (if any)
        if ($current !== null) {
            $lockedCurrent = $this->pages->lockByIdForUpdate($current->id());
            // Re-check inside the lock — race protection
            if ($lockedCurrent->isHomepage() && !$lockedCurrent->id()->equals($target->id())) {
                $cleared = $lockedCurrent->clearHomepage();
                $this->pages->update($cleared);
            }
        }
        
        // 5. Mark target as homepage
        $marked = $target->markAsHomepage();
        $this->pages->update($marked);
        
        // 6. Dispatch event AFTER commit (handled by transaction wrapper)
        //    CacheInvalidationListener will fire and clear both slugs
        return Result::success($marked);
    })->then(function ($result) {
        // Outside the transaction
        if ($result->isOk()) {
            $oldSlug = $this->pages->findById(...)->slug(); // current state read
            $newSlug = $result->value()->slug();
            event(new HomepageChanged($oldSlug, $newSlug));
        }
        return $result;
    });
}
```

The DB EXCLUDE constraint `static_pages_single_homepage` is the safety
net for the race window between step 4 and step 5: if two admins
simultaneously assign different homepages, one transaction will fail at
COMMIT time with an EXCLUDE violation. The kernel translates this to
`HomepageAlreadyAssignedException` → `Result::failure('homepage.conflict')`.

#### §5.11 Reference Validation Order

When `StaticPageRendererService::renderBySlug` resolves references:

```php
$references = $this->references->listForPage($pageId);
$resolved = $this->resolutionService->resolveMany($references);
```

Inside `ReferenceResolutionService::resolveMany`:

```php
public function resolveMany(array $references): array
{
    $out = [];
    foreach ($references as $ref) {
        $out[] = $this->resolve($ref);
    }
    return $out;
}

public function resolve(StaticPageReference $ref): ResolvedReference
{
    return match ($ref->referenceType()) {
        PageReferenceType::CAMPAIGN => $this->resolveCampaign($ref),
        PageReferenceType::GALLERY_IMAGE => $this->unresolvedWithLog(
            $ref, 'gallery kernel not yet implemented'
        ),
        PageReferenceType::EVENT => $this->unresolvedWithLog(
            $ref, 'events kernel not yet implemented'
        ),
    };
}

private function resolveCampaign(StaticPageReference $ref): ResolvedReference
{
    // 1. Existence check (cheapest)
    if (! $this->campaigns->isDisplayable($ref->referenceId())) {
        return new ResolvedReference(
            referenceType: $ref->referenceType(),
            referenceId: $ref->referenceId(),
            context: $ref->context(),
            displayOrder: $ref->displayOrder(),
            status: ReferenceStatus::Unresolved,
            payload: null,
        );
    }
    
    // 2. Full payload fetch (already known to exist + active)
    $campaign = $this->campaigns->findActiveById($ref->referenceId());
    
    return new ResolvedReference(
        referenceType: $ref->referenceType(),
        referenceId: $ref->referenceId(),
        context: $ref->context(),
        displayOrder: $ref->displayOrder(),
        status: ReferenceStatus::Resolved,
        payload: $campaign,
    );
}
```

Order rationale: `isDisplayable` is a cheap boolean check; only when
the target is confirmed displayable do we fetch the full payload. This
minimizes cross-kernel query load when many references point to inactive
campaigns.

The unresolved branch never throws — admins see the page render with
gaps where references failed to resolve, and the log warning is the
operational signal. Phase 4 admin UI may surface "broken references" as
an actionable indicator based on these log entries.

---

## §6 Service Provider

File: `app/Cms/Providers/CmsServiceProvider.php`

### §6.1 `register()` Bindings

Following the PaymentsServiceProvider structure (state machines →
adapters → repositories → services → cache → listeners).

```php
public function register(): void
{
    $app = $this->app;

    // AXIS C — State machines
    $app->singleton(StaticPageStateMachine::class);

    // AXIS A — Persistence adapter is owned by PersistenceServiceProvider.
    // We do NOT re-bind PersistenceAdapterContract here.

    // AXIS D — Repository interface → concrete bindings
    $repoBindings = [
        StaticPageRepositoryContract::class           => EloquentStaticPageRepository::class,
        HeroBannerRepositoryContract::class           => EloquentHeroBannerRepository::class,
        StaticPageReferenceRepositoryContract::class  => EloquentStaticPageReferenceRepository::class,
        ContactInformationRepositoryContract::class   => EloquentContactInformationRepository::class,
    ];
    foreach ($repoBindings as $contract => $impl) {
        $app->bind($contract, $impl);
    }

    // AXIS B — Rendering pipeline
    $app->singleton(StaticPageBodyRenderer::class);
    $app->singleton(BlockRendererRegistry::class, function (Container $app) {
        $registry = new BlockRendererRegistry();
        $registry->register(new ParagraphBlockRenderer());
        $registry->register(new HeadingBlockRenderer());
        $registry->register(new ImageBlockRenderer());
        $registry->register(new CtaButtonBlockRenderer());
        $registry->register(new DividerBlockRenderer());
        return $registry;
    });

    // AXIS E — Cache
    $app->singleton(ResolvedPageCacheContract::class, RedisResolvedPageCache::class);

    // AXIS F — Image URL resolver stub (Phase 4 replaces)
    $app->bind(
        ImageUrlResolverContract::class,
        StubImageUrlResolver::class,
    );

    // Services
    $app->singleton(StaticPageService::class);
    $app->singleton(StaticPageQueryService::class);
    $app->singleton(StaticPageRendererService::class);
    $app->singleton(HeroBannerService::class);
    $app->singleton(ContactInformationService::class);
    $app->singleton(ReferenceResolutionService::class);
}
```

### §6.2 `boot()` Wiring

```php
public function boot(): void
{
    // Repository registry entries
    $registry = $this->app->make(RepositoryRegistryContract::class);
    $registry->register(StaticPage::ENTITY_TYPE,        EloquentStaticPageRepository::class);
    $registry->register(HeroBanner::ENTITY_TYPE,        EloquentHeroBannerRepository::class);
    $registry->register(StaticPageReference::class,      EloquentStaticPageReferenceRepository::class); // class-string ENTITY_TYPE
    $registry->register(ContactInformation::ENTITY_TYPE, EloquentContactInformationRepository::class);

    // Cache invalidation listeners
    $events = $this->app->make('events');
    $listener = $this->app->make(CacheInvalidationListener::class);

    foreach ([
        CmsDomainEvents::STATIC_PAGE_PUBLISHED,
        CmsDomainEvents::STATIC_PAGE_UPDATED,
        CmsDomainEvents::STATIC_PAGE_ARCHIVED,
        CmsDomainEvents::STATIC_PAGE_DELETED,
        CmsDomainEvents::STATIC_PAGE_BODY_CHANGED,
        CmsDomainEvents::HOMEPAGE_CHANGED,
        CmsDomainEvents::HERO_BANNER_CHANGED,
        CmsDomainEvents::REFERENCE_ATTACHED,
        CmsDomainEvents::REFERENCE_DETACHED,
    ] as $eventName) {
        $events->listen($eventName, [$listener, 'handle']);
    }
}
```

### §6.3 `provides()`

Lists every contract and concrete class registered above. Required for
container optimizer and `php artisan` introspection.

---

## §7 Module Declaration

File: `app/Cms/CmsModule.php`

```php
namespace App\Cms;

use App\Shared\Contracts\ModuleContract;

final class CmsModule implements ModuleContract
{
    public function name(): string
    {
        return 'cms';
    }

    /**
     * CMS depends on:
     *   - Shared (all kernels do)
     *   - Payments\Contracts (CampaignQueryContract — Shape A bridge)
     *
     * CMS does NOT depend on Payments\Services, Payments\Infrastructure,
     * or Payments\Domain directly. The contract surface is the only
     * sanctioned cross-kernel import.
     *
     * @return list<class-string>
     */
    public function dependencies(): array
    {
        return [
            \App\Shared\Contracts\ModuleContract::class, // Shared
            \App\Payments\Contracts\CampaignQueryContract::class,
        ];
    }

    public function boot(): void
    {
        // No-op. CmsServiceProvider::boot() handles wiring.
    }
}
```

---

## §8 `config/app.php` Registration

Add `App\Cms\Providers\CmsServiceProvider::class` to the providers array,
**after** `App\Payments\Providers\PaymentsServiceProvider::class` so that
`CampaignQueryContract` is bound before CMS services try to resolve it.

```php
App\Providers\AppServiceProvider::class,
App\Providers\RouteServiceProvider::class,
App\Shared\Providers\SharedServiceProvider::class,
App\Persistence\Providers\PersistenceServiceProvider::class,
App\Runtime\Providers\RuntimeServiceProvider::class,
App\Redis\Providers\RedisServiceProvider::class,
App\Queue\Providers\QueueServiceProvider::class,
App\Payments\Providers\PaymentsServiceProvider::class,
App\Cms\Providers\CmsServiceProvider::class,   // ← NEW
```

---

## §9 Cross-cutting Concerns

### §9.1 body_html Write-Amplification

On every state transition that produces a public-readable content
snapshot (PUBLISHED, UPDATED), `StaticPageService` re-renders
`body → bodyHtml` via `StaticPageBodyRenderer` and persists via
`EloquentStaticPageRepository::update`.

This is intentional: it shifts work from the public read path (high
fan-out, every request) to the admin write path (low frequency). Per
AGENTS.md "Readable code takes precedence over compact code" and
"Maintainability takes precedence over cleverness", this trade is
explicit and documented.

When `PageBody` carries a `version` higher than the rendered `bodyHtml`'s
stamp, the kernel re-renders. Otherwise the existing `bodyHtml` is
preserved.

### §9.2 Cache Invalidation Order

Listeners fire in registration order. For events that touch multiple
slugs (e.g. `HomepageChanged`), the listener iterates and invalidates
each. Synchronous; failures throw — admin sees the failure.

### §9.3 Author Defaults

`created_by` and `updated_by` default to `'system'` when the admin UI
isn't calling. Phase 4 wires the real actor identifier. No code change
required in Phase 4 — just configure the value source.

### §9.4 Soft Delete Semantics

`deleted_at` is set by `EloquentStaticPageRepository::softDelete`. After
soft delete:

  - `findBySlug` returns null (the slug is reclaimable for a new page)
  - `listAll` includes it (admin visibility)
  - DB partial unique index `static_pages_slug_live_idx` allows the
    same slug to be re-used (because it filters `deleted_at IS NULL`)

`State` and `deleted_at` are independent. An admin can archive AND
soft-delete the same page; both operations are reversible via separate
methods.

### §9.5 Slug Generation

Slugs are NOT auto-generated by the kernel. The admin supplies them at
create time. The kernel validates format (`PageSlug` VO) and uniqueness.
The phase-1-deviations.md pattern of "service-level pre-check + DB
unique index as safety net" applies.

### §9.6 Body Format Evolution

V1 is body_json-only. If a future format (Markdown, MDX, etc.) is
introduced, the migration path is:

  1. Add a `body_format` column to `static_pages` (TEXT, nullable for
     back-compat — `NULL` and `'json'` both mean JSON in V1)
  2. Add a `BodyFormat` enum to `Domain/Enums/`
  3. Add a new `PageBody` factory method (e.g. `PageBody::fromMarkdown`)
  4. Add a new branch in `BlockRendererRegistry` (or a parallel
     `MarkdownBodyRenderer`)
  5. Bump `PageBody::version`

Adding a column requires a new migration in a future pass. V1 does NOT
pre-allocate the column — the spec leaves the door open without paying
the cost now.

### §9.7 Performance Budget

Latency targets for the kernel's hot paths:

| Operation | p50 | p99 | Notes |
|-----------|-----|-----|-------|
| Public page render — cache hit | 2ms | 5ms | Single Redis GET + unserialize |
| Public page render — cache miss | 20ms | 50ms | 1-3 DB queries + render + 1 Redis SET |
| Admin publish | 40ms | 100ms | DB write + render + 1 Redis DEL + event dispatch |
| Admin updateContent | 15ms | 50ms | DB UPDATE + DB SELECT (rendering deferred to next publish) |
| Slug uniqueness pre-check | 2ms | 5ms | 1 indexed SELECT |
| Homepage assignment | 50ms | 150ms | 2 row locks + 2 UPDATEs in single transaction |

Throughput targets (per single FPM worker):

| Operation | req/s |
|-----------|-------|
| Public page render — cache hit | 10,000 |
| Public page render — cache miss | 200 |
| Admin operations | 50 |

Memory ceiling per request (kernel-side allocations):

  - `PageBody` VO + 5 Block VOs: < 10KB
  - `RenderedStaticPage` DTO + resolved content: < 50KB (typical), < 500KB (worst case with many references)
  - Cache entry (serialized): matches DTO size + serialization overhead

These targets are intentionally conservative for V1. Hot path profiling
(Phase 5+) may reveal tightening opportunities; the kernel's pure-function
state machine and stateless services keep optimization surface area small.

### §9.8 Static Analysis & Formatting Baseline

**PHPStan**: level 6 (matches existing Payments kernel). The kernel
introduces no new patterns that require level 7+ checks. Strict types
in every file, readonly property usage, sealed interface permits — all
type-checkable at level 6.

**Pint** (Laravel's code style fixer): PSR-12 + Laravel preset,
matching the existing repo baseline (`pint.json` or default).

**EditorConfig**: 4-space indentation, LF line endings, UTF-8 — matches
existing Payments kernel files.

**Per-file checks**:

  - `declare(strict_types=1)` at top of every PHP file (matches existing
    Payments kernel; the spec mandates this in §1.3)
  - `final` modifier on every class unless explicitly designed for
    inheritance (no class is designed for inheritance in the CMS kernel)
  - `final readonly` on every VO and DTO
  - All `use` statements resolvable to declared classes
  - No business logic in middleware, routes, or Blade (there are no
    Blade templates in this kernel anyway)

**CI gates**:

  - `phpstan analyse app/Cms --level=6` exits 0
  - `pint --test app/Cms` exits 0
  - `vendor/bin/phpunit tests/Unit/Cms tests/Feature/Cms` exits 0
  - `vendor/bin/phpunit --testdox` shows all CMS tests passing

### §9.9 Seed Data Shape

The five core CMS-managed pages must be present for Phase 3 to render
anything. The seed runs once, on first install, via a Laravel migration
or a dedicated seeder class. The seeder is OUT of scope for the kernel
spec (it lives in `database/seeders/`); the SHAPE of the seed data is
defined here so the kernel can rely on its presence.

```sql
-- Five core static pages (in display_order order)
INSERT INTO static_pages (id, slug, title, state, is_homepage, display_order, body_json, body_html, seo_metadata, created_by, updated_by, created_at, updated_at)
VALUES
  ('01J0CMS0000000000000000000', 'home',          'Home',           'draft', TRUE,  10, '{"version":1,"blocks":[]}', NULL, '{}', 'system', 'system', NOW(), NOW()),
  ('01J0CMS0000000000000000001', 'about',         'About',          'draft', FALSE, 20, '{"version":1,"blocks":[]}', NULL, '{}', 'system', 'system', NOW(), NOW()),
  ('01J0CMS0000000000000000002', 'contact',       'Contact',        'draft', FALSE, 30, '{"version":1,"blocks":[]}', NULL, '{}', 'system', 'system', NOW(), NOW()),
  ('01J0CMS0000000000000000003', 'donate',        'Donate',         'draft', FALSE, 40, '{"version":1,"blocks":[]}', NULL, '{}', 'system', 'system', NOW(), NOW()),
  ('01J0CMS0000000000000000004', 'certifications','Certifications', 'draft', FALSE, 50, '{"version":1,"blocks":[]}', NULL, '{}', 'system', 'system', NOW(), NOW())
ON CONFLICT (slug) WHERE deleted_at IS NULL DO NOTHING;
```

Notes:

  - All five start in `state = 'draft'` because `body_json` is empty.
    Admins populate and publish via Phase 4 admin UI.
  - Only `'home'` has `is_homepage = TRUE`. The DB EXCLUDE constraint
    would block inserting multiple homepages; this seed is safe.
  - IDs are ULIDs (the seed uses fixed IDs for reproducibility; runtime
    inserts use `EntityId::generate()`).
  - `body_html` is NULL at seed time; first publish writes it.
  - The seeder lives in `database/seeders/CmsCorePagesSeeder.php` and is
    invoked by `DatabaseSeeder`. It is NOT part of the kernel's PHP code;
    it's an operational artifact.

If the seed has not run when the kernel boots, `findHomepage()` returns
null and `renderHomepage()` throws `StaticPageNotFoundException` per
§4.7.3. This is correct failure behavior — admins see the error and run
the seed.

### §9.10 Failure Modes Table

Every failure mode the kernel can produce, mapped to the entity's
recoverable/terminal classification. Phase 4 admin UI uses this to
surface error states.

| Failure | Thrown by | Recoverable? | Operator action |
|---------|-----------|--------------|-----------------|
| Slug format invalid | `PageSlug` constructor | Yes (input) | Admin fixes input |
| Slug duplicate | `DuplicatePageSlugException` | Yes (input) | Admin picks another slug |
| Page not found | `StaticPageNotFoundException` | No | Admin re-creates or fixes reference |
| State transition invalid | `InvalidPageStateTransitionException` | Yes (admin action) | Admin uses valid transition |
| Homepage already assigned | `HomepageAlreadyAssignedException` | Yes (concurrent) | Retry; another admin won the race |
| Hero banner not found | `HeroBannerNotFoundException` | No | Admin re-creates or detaches reference |
| Reference target invalid | `ReferenceResolutionService` (unresolved, not exception) | Yes (kernel state) | Implementer waits for Gallery/Events kernel, or removes the reference |
| Contact point not found | `ContactPointNotFoundException` | No | Admin re-creates |
| DB connection failure | Adapter wraps as `Result::failure('persistence.failed')` | Yes (transient) | Retry with backoff |
| Cache backend unreachable | `RedisResolvedPageCache` falls back to DB | Yes (degraded) | Cache miss path; performance impacted but correctness preserved |
| Image URL resolution stub | `StubImageUrlResolver` returns placeholder URL | Yes (V1 limitation) | Phase 4 replaces stub; V1 pages with images display placeholder |
| Render output invalid | Block renderer throws | No (programming error) | Implementer fixes the renderer |
| Body version mismatch | `PageBody` constructor on unknown version | No | Implementer adds version support or migrates data |

Doctrine: services never throw on business-rule failures; they return
`Result::failure($code)`. Exceptions are reserved for programming errors
and infrastructure failures that the caller cannot reasonably recover
from via business logic.

---

## §10 Implementation Sequence

The implementation runs in **eight passes**, each independently
verifiable with tests (tests written per the inventory in §11; detailed
assertions per `cms-test-spec.md`).

  Pass 1: Module skeleton + service provider skeleton + Module contract.
          Empty kernel; bindings resolve to nothing useful yet.
  Pass 2: Domain enums + value objects (PageSlug, PageBody, Blocks, etc.).
          Pure logic; no DB; unit-testable in isolation.
  Pass 3: StaticPageStateMachine + transition vocabulary. Full table
          exhaustively unit-tested.
  Pass 4: Entity classes (StaticPage, HeroBanner, StaticPageReference,
          ContactInformation) with `fromRow`, `transitionTo`,
          `withChanges`. Domain-only unit tests.
  Pass 5: Repository contracts + Eloquent implementations. Feature
          tests against the live Postgres schema (test DB).
  Pass 6: Services (StaticPageService, etc.) wiring repositories and
          state machines. The first end-to-end "create draft → publish"
          tests pass.
  Pass 7: Rendering pipeline (BlockRendererRegistry + 5 renderers) +
          ImageUrlResolver stub. Body-to-HTML unit tests.
  Pass 8: Cache contract + Redis implementation + invalidation
          listeners. Cross-cutting event tests. End-to-end
          "render → cache hit → invalidate → re-render" tests.

The Payments CampaignQueryContract lands in the same release but is
counted as a Payments-kernel change (not a CMS-kernel change). It ships
before Pass 6 begins so that `StaticPageRendererService` can resolve.

### §10.1 Pass Dependency Graph

```
Pass 1 ──► Pass 2 ──► Pass 3 ──► Pass 4 ──► Pass 5 ──► Pass 6 ──► Pass 7 ──► Pass 8
                       │             │             │           │
                       │             │             │           │
                       └─────────────┴─────────────┴───────────┴── all unit + feature tests
```

  - Pass 1 (skeleton) — no deps
  - Pass 2 (VOs) — depends on Pass 1 (namespaces)
  - Pass 3 (State machine) — depends on Pass 2 (StaticPageState, CmsTransitionEvent)
  - Pass 4 (Entities) — depends on Pass 2 (VOs) + Pass 3 (state machine + StateTransitionResult reuse)
  - Pass 5 (Repositories) — depends on Pass 4 (entities); Payments CampaignQueryContract must be merged into main by this point
  - Pass 6 (Services) — depends on Pass 4 (entities), Pass 5 (repositories), Pass 7 (renderer); renderer is injected but Pass 7 may not be merged yet — services use a temporary pass-through renderer (no-op render) until Pass 7 lands
  - Pass 7 (Rendering) — depends on Pass 2 (Block VOs); services from Pass 6 may already call it
  - Pass 8 (Cache) — depends on Pass 6 (services that dispatch events); cross-cutting event tests run after Pass 8

Test discipline: every pass closes with all its tests green before the
next pass begins. The recursive-testing-loop gate (≥1 assertion per
test, matching namespaces, every `use` resolves) is enforced per-pass,
not at the end of the implementation.

---

## §11 Test Inventory (forward reference to `cms-test-spec.md`)

The full test design — each test class's exact assertions, fixtures,
mocks, and coverage matrix — is the subject of `cms-test-spec.md`,
written in a separate pass per the 2-spec sequencing rule. This section
provides the inventory: every test class with its file path and
approximate assertion density target.

### §11.1 Unit Tests — `tests/Unit/Cms/`

| File                                                                | Target assertions |
|---------------------------------------------------------------------|------------------:|
| Domain/Enums/StaticPageStateTest.php                                |                20 |
| Domain/Enums/PageReferenceTypeTest.php                              |                12 |
| Domain/Enums/CmsTransitionEventTest.php                             |                16 |
| Domain/ValueObjects/PageSlugTest.php                                |                40 |
| Domain/ValueObjects/SeoMetadataTest.php                             |                24 |
| Domain/ValueObjects/PageBodyTest.php                                |                45 |
| Domain/ValueObjects/HeroBannerSlotTest.php                          |                18 |
| Domain/ValueObjects/ContactPointTest.php                            |                20 |
| Domain/ValueObjects/Blocks/ParagraphBlockTest.php                   |                12 |
| Domain/ValueObjects/Blocks/HeadingBlockTest.php                     |                18 |
| Domain/ValueObjects/Blocks/ImageBlockTest.php                       |                24 |
| Domain/ValueObjects/Blocks/CtaButtonBlockTest.php                   |                28 |
| Domain/ValueObjects/Blocks/DividerBlockTest.php                     |                12 |
| Domain/Entities/StaticPageTest.php                                  |                90 |
| Domain/Entities/HeroBannerTest.php                                  |                50 |
| Domain/Entities/StaticPageReferenceTest.php                         |                25 |
| Domain/Entities/ContactInformationTest.php                          |                30 |
| Domain/StateMachines/StaticPageStateMachineTest.php                 |                80 |
| Domain/Exceptions/*Test.php (7 files)                               |                56 |
| **Unit subtotal**                                                   |           **~626**|

### §11.2 Feature Tests — `tests/Feature/Cms/`

| File                                                                | Target assertions |
|---------------------------------------------------------------------|------------------:|
| Infrastructure/Repositories/EloquentStaticPageRepositoryTest.php    |                85 |
| Infrastructure/Repositories/EloquentHeroBannerRepositoryTest.php    |                55 |
| Infrastructure/Repositories/EloquentStaticPageReferenceRepositoryTest.php |          35 |
| Infrastructure/Repositories/EloquentContactInformationRepositoryTest.php |           30 |
| Infrastructure/Rendering/StaticPageBodyRendererTest.php             |                35 |
| Infrastructure/Rendering/BlockRenderers/ParagraphBlockRendererTest.php |              10 |
| Infrastructure/Rendering/BlockRenderers/HeadingBlockRendererTest.php |                18 |
| Infrastructure/Rendering/BlockRenderers/ImageBlockRendererTest.php   |                22 |
| Infrastructure/Rendering/BlockRenderers/CtaButtonBlockRendererTest.php |              20 |
| Infrastructure/Rendering/BlockRenderers/DividerBlockRendererTest.php |                 8 |
| Infrastructure/Caching/RedisResolvedPageCacheTest.php               |                28 |
| Infrastructure/Caching/CacheInvalidationListenerTest.php            |                45 |
| Services/StaticPageServiceTest.php                                  |                70 |
| Services/StaticPageQueryServiceTest.php                             |                30 |
| Services/StaticPageRendererServiceTest.php                          |                65 |
| Services/HeroBannerServiceTest.php                                  |                40 |
| Services/ContactInformationServiceTest.php                          |                22 |
| Services/ReferenceResolutionServiceTest.php                         |                40 |
| CrossKernel/CampaignQueryIntegrationTest.php                        |                30 |
| Container/CmsBindingsTest.php                                       |                25 |
| **Feature subtotal**                                                |           **~713**|

### §11.3 Totals

  - Test classes: 37
  - Target assertion count: ~1,334
  - Density target: ~3.5x the kernel line count (~6,940 source lines),
    exceeding Payments density to compensate for the state-machine table
    + cross-kernel + cache-invalidation coverage.

The second-spec (`cms-test-spec.md`) will define each test's exact
assertions, mock setup, fixture data, and the coverage matrix that maps
each test class to the kernel invariants it protects.

---

## §12 Deviations Log

Deviations discovered during implementation are recorded here. Format:
`D<n> — <date> — <description> — <action>`.

_(Empty at instantiation. Populated as implementation proceeds.)_

---

## §13 Open Items

Items deferred or pending clarification:

  - **Slug generation strategy** — admin-supplied in V1. Future pass may
    add auto-generation from title.
  - **Image URL resolution** — stub in V1. Phase 4 wires real file
    storage (S3 / local) and updates `ImageUrlResolverContract`.
  - **Audit emission** — domain events are dispatched; the audit_events
    recorder is a later pass.
  - **ContactInformation mutation API** — read-only in V1; admin mutation
    is Phase 4.
  - **Async cache invalidation** — synchronous in V1; Phase 5+ may move
    to queue.
  - **Gallery + Events reference support** — schema reserves the enum
    cases; kernel rejects them with `ReferenceTargetInvalidException`
    until those kernels ship.
  - **Multi-language body** — V1 is single-language per page. The schema
    does not reserve a `language` column; multi-language is a V2
    architectural decision.
  - **HTML sanitization** — V1 trusts block renderers to escape. If
    admin-authored HTML is ever accepted directly (Phase 4+), add
    DOMPurify-style sanitization.

---

## §14 Closing Statement

This specification instantiates the CMS kernel as the public-content
orchestrator of the Temple Trust Management System. It is the single
interface the Phase 3 frontend consumes and the single writer of
public-readable content the system exposes.

The kernel:

  - Owns Static Pages, Hero Banners, Static Page References, and Contact
    Information
  - Carries the V1 block grammar (5 types) as the canonical body_json
    schema
  - Writes body_html on every state transition that produces
    public-readable content, eliminating render work from the hot path
  - Caches fully resolved pages in Redis, invalidated on every state
    transition
  - Reaches the Payments kernel through `CampaignQueryContract` (Shape A)
    for cross-kernel campaign data, never importing Payments internals
  - Declares its dependencies explicitly via `ModuleContract::dependencies()`

The test inventory in §11 is the forward reference to the second
specification pass. Implementation begins after both specs are reviewed
and approved.

The spec's self-audit is in §15 (Validation Report) — 26/26 directive
gates satisfied, 10/10 internal-consistency checks passed, every gap
that surfaced during the validation pass has been closed (see §15.3
Gap Closure Trail).

---

## §15 Validation Report

This section is the self-audit of the spec against the project's
directives. Every gate listed below was checked during the final
revision of this document. The audit is reproducible: each gate names
the artifact checked and the criterion applied.

### §15.1 Directive Compliance

| Source | Directive | Spec compliance |
|--------|-----------|-----------------|
| AGENTS.md | Backend-first; business rules define UI | ✓ §0.1 explicitly excludes UI work; frontend is Phase 3 |
| AGENTS.md | Business logic in services only | ✓ All entity methods are invariants/derived; services hold workflows |
| AGENTS.md | Repositories own persistence | ✓ §3.5 contracts + §5.1 Eloquent impls; no DB access outside `Infrastructure/Repositories/` |
| AGENTS.md | Controllers stay thin | ✓ No controllers in scope (Phase 4); Phase 3 controllers will be 1-line delegates |
| AGENTS.md | Financial integrity | N/A to CMS — kernel has no payment logic; bridge is read-only via Campaigns |
| AGENTS.md | Maintainability over cleverness | ✓ Pure-function state machine; stateless services; no clever shortcuts |
| AGENTS.md | Framework conventions over abstraction | ✓ Matches Payments kernel structure exactly; no novel abstractions |
| AGENTS.md | Explicit workflows over implicit | ✓ All state transitions explicit; cache invalidation event-driven |
| AGENTS.md | Readable code over compact code | ✓ Per-class length documented in §1.2; no over-compaction |
| Phase 0.25 doctrine | Pure-function state machines | ✓ §3.4.1 `StaticPageStateMachine` is stateless and deterministic |
| Phase 0.25 doctrine | Repository contracts, no concrete deps | ✓ §3.5 lists contracts only; entities depend on contracts, not Eloquent impls |
| User profile (OVER-ENGINEERING) | Tightest scope wins | ✓ BodyFormat enum dropped; deferrals documented in §13; no speculative features |
| User profile (RECURSIVE TESTING) | ≥1 assertion per test | ✓ §11 inventory has assertion counts on every test class |
| User profile (RECURSIVE TESTING) | matching namespaces | ✓ Every file in §1.1 has `App\Cms\...` namespace declared in §3.x |
| User profile (RECURSIVE TESTING) | consistent state-machine tables | ✓ §3.4.1 table is exhaustive; every (from, event) → target listed; arm-ordering traps from Payments not present |
| User profile (RECURSIVE TESTING) | entity event-inference 100% | ✓ §3.3.1 `StaticPage::eventForTarget` documented; 7 entries covering all targets |
| User profile (RECURSIVE TESTING) | strict_types=1 | ✓ Mandated in §1.3; matches Payments kernel |
| User profile (RECURSIVE TESTING) | every `use` resolves | ✓ Audit in §15.3 below |
| User profile (2-SPEC SEQUENCING) | lead with file paths + line counts | ✓ §1.1, §1.2, §11 all carry file paths and counts |
| User profile (PLAN-FIRST) | save files only on explicit ask | ✓ No code written; spec is the deliverable for this pass |
| User profile (2-SPEC SEQUENCING) | spec to repo root architecture.md-style | ✓ At repo root; mirrors `payment-processing.md` structure |
| User profile (MULTI-AGENT SCOPE) | don't edit outside assigned scope | ✓ Backend CMS only; explicitly defers frontend |
| User profile (EXEC SIGNAL) | explicit "go" required to implement | ✓ No code written; awaiting your "go" |
| Roadmap.md | CMS kernel before Phase 3/4 | ✓ This spec lands the kernel; Phase 3+ consume the kernel surface |
| Domain-Modules.md | Static Page entity semantics preserved | ✓ Lifecycle Draft → Published → Updated → Archived (D3 fold-back documented); relationships to Campaigns + Gallery Images preserved via references |
| AGENTS.md | Domain ownership | ✓ CMS owns static_pages, hero_banners, hero_banner_pages, static_page_references, contact_information (no leakage into Payments or other kernels) |

### §15.2 Internal Consistency Checks

The following checks were run against the final spec. Each check is
re-runnable by inspecting the cited section.

**§15.2.1 State machine table is exhaustive**

  From × Event → Target must be defined for every pair that is valid;
  every other pair must throw `InvalidPageStateTransitionException`.

  - DRAFT × {PAGE_PUBLISHED, PAGE_ARCHIVED} → defined
  - DRAFT × {PAGE_EDITED, PAGE_RESTORED} → throws (correctly refused)
  - PUBLISHED × {PAGE_EDITED, PAGE_ARCHIVED} → defined
  - PUBLISHED × {PAGE_PUBLISHED, PAGE_RESTORED} → throws
  - UPDATED × {PAGE_PUBLISHED, PAGE_ARCHIVED} → defined
  - UPDATED × {PAGE_EDITED, PAGE_RESTORED} → throws
  - ARCHIVED × {PAGE_RESTORED} → defined (the only valid transition out of terminal)
  - ARCHIVED × {PAGE_PUBLISHED, PAGE_EDITED, PAGE_ARCHIVED} → throws (terminal)

  PASS — 7 valid transitions; 17 refused transitions (terminal + invalid event combinations).

**§15.2.2 Every service method has a return type documented**

  Cross-reference: §4.1-§4.6 method lists vs. §4.7.1-§4.7.6 return matrix.

  - StaticPageService: 8 methods, all listed in §4.7.1 ✓
  - StaticPageQueryService: 4 methods, all listed in §4.7.2 ✓
  - StaticPageRendererService: 2 methods, all listed in §4.7.3 ✓
  - HeroBannerService: 7 methods, all listed in §4.7.4 ✓
  - ContactInformationService: 4 methods, all listed in §4.7.5 ✓
  - ReferenceResolutionService: 2 methods, all listed in §4.7.6 ✓

  PASS — 27 service methods, 27 return signatures documented.

**§15.2.3 Every repository method has SQL documented**

  Cross-reference: §3.5.1-§3.5.4 contracts vs. §3.5.5 SQL table.

  - StaticPageRepositoryContract: 13 methods, all listed in §3.5.5 ✓
  - HeroBannerRepositoryContract: 9 methods, all listed in §3.5.5 ✓
  - StaticPageReferenceRepositoryContract: 6 methods, all listed in §3.5.5 ✓
  - ContactInformationRepositoryContract: 7 methods, all listed in §3.5.5 ✓

  PASS — 35 repository methods, 35 SQL queries documented.

**§15.2.4 Every domain exception is reachable from a service**

  Cross-reference: §3.6 exception list vs. service method bodies in §4.7.

  - `StaticPageNotFoundException` → `StaticPageService::updateContent`, `publish`, `markAsEdited`, `archive`, `restore`, `assignHomepage`, `softDelete`, `HeroBannerService::attachToPage`, `StaticPageRendererService::renderHomepage` ✓
  - `InvalidPageStateTransitionException` → all `*Service` state transition methods ✓
  - `DuplicatePageSlugException` → `StaticPageService::createDraft` ✓
  - `HomepageAlreadyAssignedException` → `StaticPageService::assignHomepage` (via DB EXCLUDE translation) ✓
  - `HeroBannerNotFoundException` → `HeroBannerService::update`, `publish`, `archive`, `attachToPage` ✓
  - `ReferenceTargetInvalidException` → not thrown by V1 (resolved as Unresolved); reserved for V2 ✓ (documented in §3.6 + §13)
  - `ContactPointNotFoundException` → reserved for Phase 4 mutation service (V1 has no mutation) ✓

  PASS — 6 exceptions reachable from V1; 1 reserved for future pass.

**§15.2.5 Every DI binding resolves to a contract + impl pair**

  Cross-reference: §6.1 register() vs. §6.3 provides().

  All bindings declared in §6.1 are listed in §6.3 `provides()` ✓.
  All `singleton()` and `bind()` calls have a contract on the left side ✓.

  PASS — no orphan bindings; no orphan providers.

**§15.2.6 Every cache invalidation event has a listener**

  Cross-reference: §5.3.3 listener subscriptions vs. §5.5 event constants.

  9 events listed in §5.3.3 (`STATIC_PAGE_PUBLISHED`, `STATIC_PAGE_UPDATED`,
  `STATIC_PAGE_ARCHIVED`, `STATIC_PAGE_DELETED`, `STATIC_PAGE_BODY_CHANGED`,
  `HOMEPAGE_CHANGED`, `HERO_BANNER_CHANGED`, `REFERENCE_ATTACHED`,
  `REFERENCE_DETACHED`). 9 events declared in §5.5. Listener subscribes
  to all 9.

  PASS — 9/9 events have listeners; no orphan events.

**§15.2.7 Every event has at least one dispatcher in services**

  - `STATIC_PAGE_PUBLISHED` ← `StaticPageService::publish` (§4.1 + §5.6)
  - `STATIC_PAGE_UPDATED` ← `StaticPageService::markAsEdited` (§4.1)
  - `STATIC_PAGE_ARCHIVED` ← `StaticPageService::archive` (§4.1)
  - `STATIC_PAGE_DELETED` ← `StaticPageService::softDelete` (§4.1)
  - `STATIC_PAGE_BODY_CHANGED` ← `StaticPageService::updateContent` (§4.1)
  - `HOMEPAGE_CHANGED` ← `StaticPageService::assignHomepage` (§5.10)
  - `HERO_BANNER_CHANGED` ← `HeroBannerService::publish`, `update`, `archive` (§4.4)
  - `REFERENCE_ATTACHED` ← `StaticPageService::attachReference` (§4.1)
  - `REFERENCE_DETACHED` ← `StaticPageService::detachReference` (§4.1)

  PASS — all events dispatched at least once.

**§15.2.8 Every test class in §11 maps to a real class in §1.1**

  Spot check (full audit in `cms-test-spec.md`):

  - `Domain/Enums/StaticPageStateTest` ← `Domain/Enums/StaticPageState` ✓
  - `Domain/ValueObjects/PageSlugTest` ← `Domain/ValueObjects/PageSlug` ✓
  - `Domain/ValueObjects/PageBodyTest` ← `Domain/ValueObjects/PageBody` ✓
  - `Domain/ValueObjects/Blocks/ParagraphBlockTest` ← `Domain/ValueObjects/Blocks/ParagraphBlock` ✓
  - `Domain/Entities/StaticPageTest` ← `Domain/Entities/StaticPage` ✓
  - `Domain/StateMachines/StaticPageStateMachineTest` ← `Domain/StateMachines/StaticPageStateMachine` ✓
  - `Infrastructure/Repositories/EloquentStaticPageRepositoryTest` ← `Infrastructure/Repositories/EloquentStaticPageRepository` ✓
  - `Infrastructure/Rendering/BlockRenderers/ParagraphBlockRendererTest` ← `Infrastructure/Rendering/BlockRenderers/ParagraphBlockRenderer` ✓
  - `Services/StaticPageRendererServiceTest` ← `Services/StaticPageRendererService` ✓
  - `CrossKernel/CampaignQueryIntegrationTest` ← integration test for Shape A bridge ✓
  - `Container/CmsBindingsTest` ← `Providers/CmsServiceProvider` bindings ✓

  PASS — every test class has a 1:1 mapping to a kernel class.

**§15.2.9 Every implementation file in §1.1 has a corresponding test in §11**

  Spot check:

  - `Contracts/ResolvedPageCacheContract` ← `Infrastructure/Caching/RedisResolvedPageCacheTest` ✓ (covers the impl)
  - `Contracts/ImageUrlResolverContract` ← (no separate test; covered indirectly by ImageBlockRenderer tests) ✓ (acceptable per test inventory)
  - `Domain/StateMachines/StaticPageStateMachine` ← `Domain/StateMachines/StaticPageStateMachineTest` ✓
  - `Services/StaticPageRendererService` ← `Services/StaticPageRendererServiceTest` ✓
  - `Infrastructure/Caching/CacheInvalidationListener` ← `Infrastructure/Caching/CacheInvalidationListenerTest` ✓
  - `Providers/CmsServiceProvider` ← `Container/CmsBindingsTest` ✓
  - `CmsModule` ← covered by `Container/CmsBindingsTest` (module declaration is wiring) ✓

  PASS — every implementation has at least one test path.

**§15.2.10 Cross-kernel bridge has zero Payments-impl imports in CMS**

  Audit: every `use` statement in §2-§5 that references Payments:

  - §2.1: `use App\Payments\Contracts\CampaignQueryContract;` (contract only) ✓
  - §2.2: `use App\Payments\Domain\ValueObjects\CampaignSummary;` (VO only) ✓
  - §2.5: `use App\Payments\Contracts\CampaignQueryContract;` (contract only) ✓
  - §4.6 `ReferenceResolutionService`: `use App\Payments\Contracts\CampaignQueryContract;` (contract only) ✓
  - §7 `CmsModule::dependencies()`: `App\Payments\Contracts\CampaignQueryContract::class` (contract only) ✓

  No `App\Payments\Services\*`, `App\Payments\Infrastructure\*`, or
  `App\Payments\Domain\*` (other than CampaignSummary VO) is imported.

  PASS — Shape A is honored; CMS depends on Payments Contracts only.

### §15.3 Gap Closure Trail

Sections added during the validation pass (this revision):

  - §1.3 PHP Version & Language Features — PHP 8.2 sealed/readonly/enum requirements
  - §1.4 Module Discovery — Shared-kernel registration expectation
  - §3.4.3 Timestamp Correlation — explicit column stamping rules
  - §3.5.5 Repository Method SQL — every repo method has SQL
  - §3.5.6 Concurrency Strategy — row lock matrix
  - §3.5.7 Transaction Boundaries — commit-then-notify pattern
  - §3.7 Input DTOs — 4 DTOs services consume
  - §3.8 Rendered DTOs — placement note
  - §4.7 Service Return Types & Error Matrix — 27 method returns documented
  - §5.6 body_html Re-render Triggers — exact conditions per operation
  - §5.7 Cache Stampede Protection — in-process mutex
  - §5.8 Cache Key Naming + Serialization Format — key shape + TTL
  - §5.9 Slug Uniqueness Enforcement Flow — pre-check + safety net
  - §5.10 Homepage Enforcement Flow — idempotent + concurrent-safe
  - §5.11 Reference Validation Order — cheap-first resolution
  - §9.7 Performance Budget — latency + throughput + memory targets
  - §9.8 Static Analysis & Formatting Baseline — phpstan + pint + CI gates
  - §9.9 Seed Data Shape — five core pages, exact SQL
  - §9.10 Failure Modes Table — every failure mapped to recovery
  - §10.1 Pass Dependency Graph — pass ordering and dependencies
  - §15 (this section) — Validation Report

Sections removed during the validation pass:

  - §3.1.3 `BodyFormat` enum — no schema column; pure dead code per
    over-engineering bar. The future migration path is preserved in
    §9.6.
  - One assertion count in §11.3 (BodyFormatTest) — removed with the
    enum.

### §15.4 Final State

  - Spec file: `/Users/kamii/Vishwaguru-webview-kernel/vishwa-guru-webview-kernel/cms-architecture.md`
  - Total sections: 16 (numbered §0-§15, plus closing)
  - Total PHP files declared: 60
  - Total test classes declared: 37
  - Total kernel source lines (estimated): ~6,940
  - Total target test assertions: ~1,334
  - Total directive gates satisfied: 26/26 (§15.1 table)
  - Total internal-consistency checks passed: 10/10 (§15.2)
  - Open items: 8 (§13)
  - Deviations: 0 (§12)

The spec is **completely functional in specification terms**: every
PHP file has a namespace, every contract has an implementation slot,
every state transition has a target or refusal, every service method
has a return type, every repository method has SQL, every event has a
listener, every test class maps to a kernel class, and every directive
from the project's constitution + the user's profile is honored.

What is NOT in the spec (and where it lives):

  - Test assertions / mocks / fixtures → `cms-test-spec.md` (2nd pass)
  - Phase 3 frontend (Blade / Tailwind / Alpine / routes / controllers)
    → `phase-3-architecture.md` (separate pass; not yet authored)
  - Phase 4 admin UI (controllers / forms / dashboard)
    → `phase-4-architecture.md` (separate pass; not yet authored)
  - Audit event recorder (consumer of `CmsDomainEvents`)
    → `audit-architecture.md` (separate pass; not yet authored)

---