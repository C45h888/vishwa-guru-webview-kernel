# AGENTS.md

# AI Engineering Constitution

## Purpose

This document defines the operational rules, architectural directives, and engineering expectations that every AI coding agent must follow while contributing to the Temple Trust Management System.

The objective of this document is to ensure that all implementation decisions remain aligned with the repository's architectural philosophy. AI agents are expected to function as disciplined software engineers operating within an established engineering system rather than autonomous code generators.

The architecture of this repository has been intentionally designed before implementation begins. AI agents must preserve this architectural foundation throughout the lifetime of the project.

---

# Project Overview

The Temple Trust Management System is a backend-first Laravel application designed to manage the operational workflows of a single temple trust.

The system is responsible for handling donations, payment processing, content management, events, galleries, and supporting business operations. Administrative workflows and user authentication are Phase 4 forward work and are not part of this constitution.

The repository prioritizes financial integrity, maintainability, security, and long-term operational stability over rapid feature delivery.

Every implementation must reinforce these principles.

---

# Technology Stack

Framework

Laravel

Programming Language

PHP

Frontend

Svelte 5 (components) + Inertia 2 (server-driven SPA bridge)

Tailwind CSS + shadcn-svelte (bits-ui) UI primitives

Frontend Build

Vite 5 + laravel-vite-plugin

Frontend Path Aliases

$shared/* → resources/js/shared/*, $domains/* → resources/js/domains/*

Database

Neon PostgreSQL

ORM

Laravel Eloquent

Primary Payment Gateway

Razorpay

Secondary Payment Gateway

PayPal

Caching & Idempotency Backend

Redis (active — SETEX dedupe for webhook + idempotency, resolved-page cache)

Notifications

Email (channel reserved; Notifications module lands in Phase 4)

---

# Engineering Philosophy

The repository follows a backend-first development philosophy.

Business rules define user interfaces.

User interfaces never define business rules.

Every feature should first exist as a backend capability before presentation components are implemented.

The backend is the authoritative implementation of business behavior.

The frontend exists solely to expose validated backend workflows.

---

# Architectural Authority

The architectural center of the application is the Service Layer.

Controllers coordinate requests.

Services implement business workflows.

Repositories perform persistence.

Models represent data.

Svelte 5 components render presentation through Inertia 2, driven by Inertia::render responses from controllers. The single Blade template in the runtime (resources/views/app.blade.php) exists ONLY as the Inertia root view shell.

AI agents must preserve this hierarchy.

---

# Primary Engineering Principles

Every implementation should satisfy the following principles.

Financial integrity takes precedence over convenience.

Security takes precedence over development speed.

Maintainability takes precedence over cleverness.

Framework conventions take precedence over unnecessary abstraction.

Explicit business workflows take precedence over implicit behavior.

Readable code takes precedence over compact code.

---

# Domain Organization

The repository is organized around business modules rather than technical folders.

The kernel is composed of ten modules grouped by surface:

Public surface

Campaigns     — donation causes; owns CampaignsQueryContract

Cms           — static pages, hero banners, contact information; owns StaticPageRendererContract + PublicMediaPresentationService

Events        — public read surface for temple events; owns EventsQueryContract

Gallery       — public read surface for photo galleries; owns GalleryQueryContract

Money surface

Payments      — gateway adapters (Razorpay, PayPal, InMemory), state machines, repositories; owns CampaignQueryContract (cross-kernel Shape A bridge)

Infrastructure kernels

Persistence   — owns PersistenceAdapterContract + RepositoryRegistryContract

Redis         — owns RedisConnectorContract; service code depends on the contract, not Illuminate\Support\Facades\Redis

Queue         — owns QueueConnectorContract; same doctrine as Redis

Runtime       — health probes, FailureRouter, EnvValidator, runtime diagnostics

Architectural foundation

Shared        — ConfigurationContract, EnvironmentContract, Clock, IdentifierGenerator, ConfigurationRegistry; every other kernel depends on it

Donations are not a module. The cause-side lifecycle (slug, target, dates, featured flag) lives in Campaigns; the money-side lifecycle (intent, verification, capture, refund, receipt) lives in Payments. The two lifecycles meet only at PaymentService::initialize, where a DonationIntent is built from a campaign_id.

Each module owns its own services, controllers, requests, repositories, policies, and related resources.

Modules should remain cohesive and self-contained.

---

# Kernel.md Discipline

Every kernel directory under `app/` contains a `Kernel.md` landing page
following a fixed five-section template:

1. **Boundaries** — what the kernel owns, what it explicitly does NOT
   own, and the inbound/outbound cross-kernel edges (each with the
   contract FQCN it traverses).
2. **Contracts** — the public contracts other kernels consume (paths +
   FQCNs).
3. **Providers** — the `ServiceProvider` filename + the boot-order
   invariant this kernel pins (cross-reference
   `tests/Unit/Bootstrap/ProviderOrderTest.php`).
4. **FSMs** — paths to any handwritten state machines, with allowed
   events; or "None — state is validated at the FormRequest boundary
   (Phase 4 doctrine)" if the kernel has no FSMs.
5. **Tests** — `tests/Unit/<Kernel>/...` and `tests/Feature/<Kernel>/...`
   inventory.

When a new kernel is added, its `Kernel.md` lands in the same commit.
When an existing kernel's surface changes (new contract, new outbound
edge, new FSM), the `Kernel.md` updates alongside the code change.
Architectural drift caused by an out-of-date `Kernel.md` is considered
the same class of defect as an out-of-date doc-comment — fix it in the
same commit.

The current ten `Kernel.md` files:

- `app/Campaigns/Kernel.md`
- `app/Cms/Kernel.md`
- `app/Events/Kernel.md`
- `app/Gallery/Kernel.md`
- `app/Payments/Kernel.md`
- `app/Persistence/Kernel.md`
- `app/Redis/Kernel.md`
- `app/Queue/Kernel.md`
- `app/Runtime/Kernel.md`
- `app/Shared/Kernel.md`

---

# Migration Filename Convention

All migrations live in `database/migrations/` and use the prefix
`k_<kernel>_` between the timestamp and the slug. This is a visual
grouping mechanism only — Laravel's migrator keys on the class inside the
file, not the filename, so renaming does not affect already-applied
migrations.

Examples:

- `2026_07_16_000001_k_bootstrap_create_v1_schema_postgres.php`
- `2026_07_16_000005_k_cms_create_cms_tables_sqlite.php`
- `2026_08_02_000011_k_auth_create_users_table_postgres.php`
- `2026_08_07_000001_k_payments_add_receipt_access_token.php`

When writing a new migration:

1. Pick the kernel that owns the table. If no kernel owns it (e.g.
   `failed_jobs`, `job_batches`), use `k_bootstrap_`.
2. Place the prefix immediately after the timestamp:
   `YYYY_MM_DD_HHMMSS_k_<kernel>_<slug>.php`.
3. The kernel segment is single-word, lowercase. Valid values: `bootstrap`,
   `cms`, `gallery`, `events`, `campaigns`, `payments`, `auth`, `runtime`,
   `persistence`, `redis`, `queue`, `shared`.

Do not introduce a `loadMigrationsFrom()` call — the default Laravel
discovery already covers `database/migrations/*.php`, and adding a
non-default discovery path would couple the migrator to a particular
kernel layout.

---

# Payments HTTP Consolidation

Payments HTTP controllers and form requests live inside the Payments
kernel at `App\Payments\Http\Controllers\*` and
`App\Payments\Http\Requests\*` — NOT at the global
`App\Http\Controllers\Payments\*` or `App\Http\Requests\Payments\*`
namespaces that earlier phases used.

This is the only FQCN-changing structural rule in the codebase. Every
other kernel's HTTP surface (admin + public) stays at
`App\Http\Controllers\Admin\<Domain>\` and
`App\Http\Controllers\Public\<Domain>\` respectively, per the Phase 4
admin doctrine and the public-route symmetry convention.

When adding a new Payments HTTP controller:

1. Place the file under `app/Payments/Http/Controllers/`.
2. Declare `namespace App\Payments\Http\Controllers;`.
3. Reach form requests from `App\Payments\Http\Requests\*` (sibling
   subdirectory).
4. Wire the route in `routes/donation.php` or `routes/webhook.php` using
   the new FQCN.
5. Do NOT recreate `app/Http/Controllers/Payments/` or
   `app/Http/Requests/Payments/` — those paths are intentionally empty.

When the test suite needs to cover a Payments FormRequest, the test
lives at `tests/Unit/Payments/Http/Requests/` (NOT under
`tests/Unit/Http/Requests/Payments/`, which is intentionally empty).

---

# Service Layer Rules

Business logic belongs exclusively within services.

Controllers must remain thin.

Repositories encapsulate persistence.

Services may coordinate with other services when implementing complete business workflows.

Business logic must never be implemented inside:

Controllers

Svelte components and route handlers

Routes

Middleware

Models (except simple accessors, mutators, and relationships)

---

# Controller Responsibilities

Controllers should:

Receive validated requests.

Call services.

Return responses.

Redirect users where necessary.

Return Inertia::render responses for page requests and JSON for mutating endpoints. Never mix.

Controllers should not:

Contain business rules.

Execute payment verification.

Implement complex queries.

Perform financial calculations.

Coordinate multiple business workflows.

---

# Repository Responsibilities

Repositories own persistence operations.

Services should interact with repositories rather than directly implementing complex Eloquent operations.

Repositories should remain focused on storage concerns and avoid business logic.

---

# Payment Directives

Financial integrity is a foundational principle.

No donation should be considered successful until the payment gateway has verified the transaction.

The canonical payment workflow is:

Request Validation

↓

Payment Initialization

↓

Gateway Processing

↓

Webhook Callback

↓

Signature Verification

↓

Database Persistence

↓

Receipt Generation

↓

Notification (Phase 4 — receipt delivery is currently a PDF download stub; see Receipt.svelte:99)

↓

Audit

AI agents must never bypass gateway verification.

AI agents must never trust client-side payment responses.

---

# Database Directives

PostgreSQL is the authoritative source of operational data.

Business entities are persisted only after successful validation and verification.

Repositories own database interactions.

Database schema modifications must be performed through Laravel migrations.

Direct database modifications outside migrations are prohibited.

---

# File Storage

Large files should never be embedded inside PostgreSQL.

The database stores metadata describing uploaded files.

Laravel Storage manages physical file persistence.

Uploaded assets include:

Temple Images

Donation Receipts

Trust Documents

Certificates

Gallery Assets

Videos

Public media is served through the /media/{id} route (Public/CmsMedia/ShowController). Files are streamed from Laravel Storage with ETag + Cache-Control headers; bytes are never embedded as base64 or persisted as PostgreSQL bytea.

---

# Notification Directives

Business services should communicate with the Notification Service. Business logic must never directly invoke email providers. Future communication providers should remain interchangeable.

Phase 4 status: the Notifications module is not yet built. Receipt delivery currently falls back to a PDF download link in resources/js/domains/payments/Receipt.svelte:99. When the Notifications module lands, the existing ReceiptDeliveryState enum (app/Payments/Domain/Enums/) and the ReceiptService's delivery channel (app/Payments/Services/ReceiptService.php) are the integration points.

---

# Security Directives

Validate all external input.

Never trust client-side state.

Enforce authorization before business execution.

Protect financial operations through gateway verification.

Use Laravel's native security features (CSRF, encrypted cookies, session) supplemented by sanctified third-party integrations (Inertia, gateway SDKs, dompdf) where they earn their place.

Store secrets only in environment configuration.

Never expose sensitive credentials.

Prefer Laravel-native solutions, but do not avoid sanctified third-party packages — inertiajs/inertia-laravel, @inertiajs/svelte, razorpay/razorpay, paypal/paypal-checkout-sdk, barryvdh/laravel-dompdf, bits-ui — when they are the canonical implementation of a subsystem the constitution recognises.

---

# AI Decision Framework

Before implementing any feature, evaluate the following questions:

Does this implementation preserve financial integrity?

Does this implementation respect domain boundaries?

Does this implementation keep business logic inside services?

Does this implementation follow Laravel conventions?

Can another developer easily understand this implementation six months from now?

Would this implementation remain maintainable if the project doubles in size?

If the answer to any question is no, reconsider the implementation.

---

# Coding Expectations

Prefer readability over cleverness.

Prefer explicit implementations over implicit behavior.

Prefer composition over duplication.

Prefer dependency injection over static coupling.

Prefer small cohesive services over large multi-purpose classes.

Avoid premature optimization.

Avoid unnecessary abstraction.

Avoid introducing additional dependencies without clear architectural justification.

---

# Documentation Responsibilities

Whenever introducing a new domain, architectural pattern, or significant workflow, update the relevant documentation.

Documentation should evolve alongside implementation.

Architectural drift caused by undocumented decisions is considered a defect.

---

# Scope

The application currently targets a single temple trust.

Do not introduce multi-tenant abstractions.

Do not implement speculative scalability features.

Future architectural evolution should occur only when justified by business requirements.

The frontend stack is Svelte 5 + Inertia 2 + Tailwind + shadcn-svelte (bits-ui). Do not introduce Blade views or Alpine.js for public pages without explicit reconciliation of this constitution.

Phase 4 work (Authentication module, Notifications module, admin CMS editing surface) must not be partially implemented. Either build them behind their own constitution section or leave them out.

---

# Phase 4: Admin Kernel

Lands now (per user decision 2026-08-02). The Authentication module + admin CMS editing surface have their own dedicated constitution section, satisfying the "must not be partially implemented" rule above.

## Scope

The admin kernel has exactly TWO surfaces:

  1. Campaigns — admin can edit existing campaigns, create new ones (Pass 2).
  2. Events — admin can create new upcoming events and end upcoming events to move them into the past-events surface (Pass 3).

Both surfaces are gated behind a single canonical admin role (`'admin'`). One admin, env-driven credentials, no public registration. The notifications module remains deferred to its own pass — receipt delivery currently uses the PDF download fallback documented elsewhere.

## Canonical references

  - DB schema: applied via Neon MCP. Source of truth is the live Neon `br-shiny-poetry-aow2d8mt` branch.
  - Laravel migration mirrors: `database/migrations/2026_08_02_000011_k_auth_create_users_table_postgres.php` (PG, guarded) + `2026_08_02_000012_k_auth_create_users_table_sqlite.php` (SQLite test mirror).
  - Auth architecture mirrors Laravel Breeze 1.x's `inertia-common` stubs (controllers + middleware + routes) so a future Laravel 11 / Breeze 2.x upgrade is a swap, not a rewrite. The Svelte login page is hand-rolled because Breeze 1.x ships only React/Vue stubs for Laravel 10.

## Authentication surface

  - Login: `POST /login` (form via Inertia on `resources/js/domains/Auth/Login.svelte`).
  - Logout: `POST /logout` (session invalidate + CSRF token regen, Breeze canonical).
  - NOT BUILT (and intentionally absent): register, forgot-password, reset-password, email-verification, confirm-password. Single canonical admin authenticates via env-driven credentials only.
  - Throttle: 5 failed attempts per email+IP per minute (Breeze canonical).

## Pass 2 — Campaigns admin

Lands in this pass. The two canonical admin capabilities on campaigns:

  1. Create a new campaign (slug, title, description, cover image, target amount, dates, state).
  2. Edit an existing campaign (same field set; slug uniqueness excludes the row being edited).

Doctrine:

  - No state machine. State is validated against `[draft, active, completed]` at the FormRequest boundary. No transitions are gated.
  - Cover image upload is real: the admin form POSTs a file to `/admin/media/upload` which writes to `file_assets` on the `public` disk and returns the `file_asset_id`. Files dedupe by SHA-256.
  - `created_by` / `updated_by` are stamped from the current admin user inside `CampaignAuthoringService`. Controllers never pass these.
  - Slug uniqueness is enforced at the storage layer (partial unique index `campaigns_slug_live_idx`) and surfaced to the controller as `DuplicateCampaignSlugException`.
  - Public campaigns surface (`state IN ('active','completed')`). Admin list surfaces ALL states including drafts.

Public-site edit affordance: when `authUser.role === 'admin'`, the public Campaigns list + show pages render a floating pencil overlay on each card linking to the admin edit page. The overlay is a no-op for non-admin visitors — zero DOM impact. New-campaign pencil sits in the breadcrumb strip.

## Authorization model

  - Single role: `'admin'`. CHECK constraint on the table rejects any other value. Multi-role expansion is a follow-on migration, not a code redesign.
  - Middleware aliases in `app/Http/Kernel.php`:
      - `auth`   — Illuminate's canonical, redirects unauthenticated → /login
      - `guest`  — `App\Http\Middleware\RedirectIfAuthenticated` redirects authenticated → /admin
      - `admin`  — `App\Http\Middleware\EnsureUserIsAdmin` enforces `User::isAdmin() === true`, else 403
  - Route group: `/admin/*` mounted under `['web', 'auth', 'admin']` (see `routes/admin.php`).

## Canonical admin seeding

  - `database/seeders/AdminSeeder.php` reads `ADMIN_EMAIL` / `ADMIN_PASSWORD` / `ADMIN_NAME` from env.
  - Idempotent: re-runs update the existing row's password + role + name via `ON CONFLICT (email) DO UPDATE`.
  - Default password `changeme-admin-2026` is intentionally weak so a missing env var produces a loud warning to stderr; production must override.

## Architectural invariants

  - The admin kernel does NOT introduce new domain modules. It is a separate `App\Http\Controllers\Admin\` namespace; business modules (Campaigns, Events) own their own contracts and services, and the admin controllers delegate to those contracts.
  - Admin mutations live in services, not controllers. Pass 2 / Pass 3 add new `CampaignAuthoringService` / `EventAuthoringService` contracts to the existing Campaigns + Events modules; the admin controllers are thin shells around them.
  - The admin uses Inertia + Svelte 5. No Blade admin views, no Alpine.js, no shadcn-svelte variant other than bits-ui.
  - File upload UI is built when the corresponding authoring surface lands (Pass 2 for campaigns, Pass 3 for events). The V1 `StubImageUrlResolver` is replaced when the first upload UI ships.
  - Audit log: `created_by` / `updated_by` columns on `campaigns` / `events` are populated with the admin's user id by the authoring services. Wiring the `audit_events` table is deferred to a follow-on pass — Pass 1 keeps the surface tight.

## Non-goals (deliberately excluded from Pass 1)

  - Cover/banner image upload UI (Pass 2 / Pass 3).
  - State machines (replaced with FormRequest validation, per user decision 2026-08-02).
  - Audit log wiring (Pass 2/3 if needed).
  - Email verification (single canonical admin doesn't need it).
  - Password reset (single canonical admin uses env-var override).
  - Multi-admin management UI (single canonical admin only).

---

# Mission

Every contribution made by an AI coding agent should leave the repository in a cleaner, more maintainable, and more understandable state than it was found.

The objective is not simply to generate code.

The objective is to build a secure, reliable, and maintainable operational platform that faithfully supports the long-term needs of the temple trust while preserving the architectural integrity established by this repository.


## Pass 3 — Events admin

Lands in this pass. The two canonical admin capabilities on events:

  1. Create a new event (slug, title, banner image, starts_at, ends_at, venue, state).
  2. End an upcoming event — state `'published'` → `'completed'`, `completed_at` set to now. The event immediately moves to the past-events surface (`listPast`).

Doctrine:

  - No state machine. State is validated against `[draft, published, completed]` at the FormRequest boundary. Admin can transition any state to any other state via the regular edit path (including published → draft for unpublishing).
  - Banner image upload is real: the admin form POSTs a file to `/admin/media/upload` which writes to `file_assets` on the `public` disk with `owner_type='event_cover'` and returns the `file_asset_id`. Files dedupe by SHA-256.
  - `created_by` / `updated_by` are stamped from the current admin user inside `EventAuthoringService`. Controllers never pass these in.
  - Slug uniqueness is enforced at the storage layer (partial unique index `events_slug_live_idx`) and surfaced to the controller as `DuplicateEventSlugException`.
  - **End event** is a dedicated POST `/admin/events/{event}/end` endpoint (not a state-transition on the edit form) because it's the user-facing button that admins click to retire an event. Idempotent: an already-completed event's `completed_at` is preserved.
  - Public events surface (`state IN ('published','completed')`). Admin list surfaces ALL states including drafts.

Public-site edit affordance: when `authUser.role === 'admin'`, the public Events list cards + show pages render a floating pencil overlay linking to the admin edit page. The end-event button surfaces in the admin edit page with a confirmation modal — admin must explicitly confirm the state transition before the POST fires.



## Pass 4 — Public-side events pencil + banner UI

The Phase 4 admin kernel surfaces a floating pencil affordance on the public events surface so the canonical admin can edit from anywhere without bouncing back to /admin. Doctrine:

  - Pencil overlays render only when `$page.props.authUser?.role === 'admin'`. Non-admin visitors see zero DOM impact.
  - Pencil overlays are wired on:
    1. The public event detail page hero image block (`/events/{slug}`)
    2. The public event detail page related-event cards grid
    3. The public events listing page breadcrumb strip ("New event" link)
  - Each pencil links to `/admin/events/{id}/edit` (or `/admin/events/new` for the create affordance).
  - The admin events form already supports a banner image upload via the shared `/admin/media/upload` endpoint. The public show page hydrates `event.banner_image` (resolved URL + alt) from `event.banner_file_id` via `PublicMediaPresentationService::enrich` in the controller. The DTO surface (`event.banner_image`) is consumed by `resources/js/domains/events/Show.svelte` via `<PublicMediaImage media={heroImage!} />`.
  - Intentionally NOT in this pass: pencil on `EventsList` (custom date-block row layout, used on the home page), pencil on `EventsRow` (component is unused in the current codebase), pencil on `events/Journal.svelte` / `events/Article.svelte` (these are static past-article content).


## Pass 5 — SEO Optimisation (server-rendered head)

Lands in this pass. The `Seo` kernel (`app/Seo/`) owns the public
webview's discoverability surface: per-page metadata, the XML sitemap,
and robots.txt.

## Why this pass exists

The public webview is an Inertia SPA. Before this pass every SEO tag
was authored inside `<svelte:head>`, which means the tags only exist
after the browser executes JavaScript. Two consequences:

  - **Link previews were completely broken.** WhatsApp, Facebook
    (`facebookexternalhit`) and iMessage (Apple's unfurl service) never
    execute JavaScript. They `GET` a URL and read `<head>` directly.
    A shared link rendered as a bare 62-character trust name with no
    description and no image.
  - **Google indexed the JS-rendered document in a second pass** —
    slower, and ranking lower than a server-rendered equivalent.

## The mechanism

Inertia hands the root Blade shell the fully-resolved page object as
`$page` (`vendor/inertiajs/inertia-laravel/src/Response.php:219`). So
the `seo` prop built on the backend is available server-side for free —
no second query, no extra round trip.

  1. A controller calls `SeoMetaContract::forPage(title:, description:,
     imageUrl:, ...)`. It passes **domain values only** and never
     constructs `og:` keys.
  2. The result rides as a normal Inertia prop named `seo`, so it
     exists in the initial HTML *and* in every later JSON navigation.
  3. `resources/views/app.blade.php` prints it with a single generic
     loop over `{tag, attrs}` pairs. The view owns no SEO logic.
  4. `resources/js/shared/components/SeoHead.svelte` renders the same
     values on the client and **takes no props**.

Step 4 exists for exactly one reason: Inertia client-side navigations
return JSON (they carry the `X-Inertia` header) and therefore never
re-render the Blade shell. Without it, tapping from the homepage to
`/campaigns` would leave the tab title frozen on the homepage's.
Both writers consume the identical payload — including a pre-encoded
`jsonLdString` — so they cannot drift.

## Doctrine

  - **One implementation, three scrapers.** The Open Graph quartet
    (`og:title` / `og:description` / `og:url` / `og:image`) is a single
    set of tags that WhatsApp, Facebook and iMessage all read. There is
    no per-platform SEO path in this codebase and there must never be
    one.
  - **The backend is the single source of truth.** `SeoHead.svelte` is
    now a pure renderer. Every call site is the prop-less
    `<SeoHead />`. Page titles, descriptions and the `HinduTemple`
    JSON-LD graph live in controllers, never in components.
  - **The `seo` prop is not optional on a public route.** A public
    controller that renders Inertia without it silently produces a page
    with no link preview. `tests/Feature/Seo/HeadTagsTest.php` is the
    acceptance gate.
  - **Server tags precede `@inertiaHead` in document order.** Scrapers
    read first-occurrence.
  - **Duplicate tags after hydration are accepted, not engineered
    around.** On first load the server writes the tags and Svelte
    writes the same ones again. Google ignores identical duplicate meta
    tags and every scraper reads the server copy. The alternative —
    Svelte adopting and mutating the server's nodes — adds a
    hydration-order dependency for no gain the crawlers care about.
  - **Transactional pages are `noindex` server-side**: `/donate/success`,
    `/donate/cancel`, and the token-gated receipt surface. The `robots`
    tag is emitted *only* when it deviates from index/follow.
  - **The homepage title is the trust name alone** (no ` — {appName}`
    suffix). The `page.title` field is `"Home"`, which would otherwise
    produce a 68-character string that is truncated in every result and
    every link preview.
  - **The root view stays the only Blade template.** `SeoMetaBuilder`
    emits a flat tag list precisely so the shell can render it with one
    loop and no new `.blade.php` file.

## Canonical host pinning

`AppServiceProvider::boot()` calls
`URL::forceRootUrl(rtrim(config('app.url'), '/'))` when `app.url`
carries a scheme. This is load-bearing: `route()` and `url()` derive
absolute URLs from the host a request *arrived on*, which is how the
live site came to advertise `https://www.vsrsms.in/...` in its sitemap
while its canonical tags advertised `https://vsrsms.in`. Pinning the
root URL makes generated URLs, canonical tags and `og:url` all derive
from one configured value. `SitemapBuilder::cachedXml()` appends the
host to its cache key so a host change invalidates the cached XML
rather than serving the previous host for a full TTL.

## Kernel relocation

`SitemapBuilder` and `SitemapUrl` moved from `App\Cms\Services\` to
`App\Seo\Services\` (`tests/Unit/Cms/SitemapBuilderTest.php` moved to
`tests/Unit/Seo/` accordingly). Sitemap enumeration is discoverability,
not CMS content, and the sitemap must stay consistent with the
canonical tags — splitting one concern across two kernels is
architectural drift. `app/Cms/Kernel.md` records the relocation.

## Explicitly out of scope for this pass

  - Image weight and Core Web Vitals. The homepage hero is a ~2 MB
    uncompressed PNG served with `Cache-Control: max-age=86400`, and
    `HeroSlideshow.svelte:69` evaluates `image ?? mobile_image` — the
    desktop image is tested first, so the `mobile_image_file_id`
    column can never win. This is a real and significant ranking
    factor, and it is a PERFORMANCE pass, not this one.
  - Full Inertia SSR. The `Dockerfile` pins "ONE runtime" and Node is
    present only to run the frontend build. SSR would add a
    long-running Node process and contradict a stated doctrine. It is
    a genuine future option, to be decided on measured evidence, not a
    config toggle.
  - Local listings and multilingual content (a Kannada surface, a
    Google Business Profile). Outside the codebase.
