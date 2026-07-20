# CMS-Payments Bridge Contract — Design Spec

**Date:** 2026-07-20
**Status:** Approved (brainstorming complete)
**Owner:** Payments kernel + CMS kernel integration
**Scope:** Cross-kernel bridge to fully wire the CMS kernel

## Context

The CMS kernel (`app/Cms/`) is functionally complete: 4 entities, 4 repository contracts, 4 Eloquent repos, 6 services, state machine, rendering pipeline, caching, image URL resolver, and the service provider — all present.

The CMS module declares `App\Payments\Contracts\CampaignQueryContract` as a **Shape A cross-kernel bridge** dependency (per `cms-architecture.md §7`). The producing kernel (Payments) owns the contract; the consuming kernel (CMS) only imports the interface.

The contract is **missing from the codebase**. This causes 2 of 15 CMS services to fail DI resolution:
- `App\Cms\Services\StaticPageRendererService` — uses `ReferenceResolutionService`
- `App\Cms\Services\ReferenceResolutionService` — directly constructor-injects `CampaignQueryContract`

The Razorpay SDK test harness (all 12 probes) does **not** depend on these 2 services and passes regardless. But to fully wire the CMS kernel ("backend working as intended"), the contract must exist with a working implementation.

## Goals (in scope)

1. Define `App\Payments\Contracts\CampaignQueryContract` with the exact API the CMS services use.
2. Define a minimal `App\Payments\Domain\DTOs\CampaignDTO` value object the contract returns.
3. Implement `App\Payments\Infrastructure\Adapters\CampaignQueryAdapter` that fulfills the contract via the existing `PersistenceAdapterContract` (queries the `campaigns` table).
4. Bind the contract to the adapter in `PaymentsServiceProvider::register()`.
5. Verify all 15 CMS services now resolve from the container.
6. Verify the 12 Razorpay probes still pass.

## Non-goals (out of scope)

- Full campaign repository rewrite (a `CampaignRepositoryContract` would be the right long-term home; this spec adds the bridge only).
- CMS HTTP surface (controllers, routes) — already discussed, deferred.
- Admin UI, Blade views — Phase 4+.
- Gallery / Events kernels — separate modules.

## Decisions locked in

| Q | Decision | Source |
|---|---|---|
| Contract shape | Two methods only: `isDisplayable(string): bool`, `findActiveById(string): ?CampaignDTO` | From `ReferenceResolutionService.php:72,77` |
| DTO shape | `CampaignDTO { id, title, slug, currencyCode, targetAmountMinor, isActive }` — read-only VO | Minimal fields to render a campaign reference |
| Implementation | Single `EloquentCampaignQueryAdapter` reading from `campaigns` table via `PersistenceAdapterContract` | Doctrine: kernel-crossing contracts MUST go through `PersistenceAdapterContract`, never `DB::` facade |
| Binding location | `PaymentsServiceProvider::register()` (mirror the existing repo bindings pattern) | Pattern consistency |

## Architecture

```
                ┌──────────────────────────────────┐
                │   App\Cms\Services\             │
                │   ReferenceResolutionService    │
                │   StaticPageRendererService     │
                └────────────────┬─────────────────┘
                                 │ constructor-injects
                                 ▼
                ┌──────────────────────────────────┐
                │ App\Payments\Contracts\          │
                │ CampaignQueryContract            │
                │   isDisplayable(string): bool    │
                │   findActiveById(string): ?DTO   │
                └────────────────┬─────────────────┘
                                 │ bound in
                                 ▼
                ┌──────────────────────────────────┐
                │ App\Payments\Infrastructure\     │
                │ Adapters\CampaignQueryAdapter    │
                │   queries campaigns table via    │
                │   PersistenceAdapterContract     │
                └──────────────────────────────────┘
```

## API sign-off

`ReferenceResolutionService` (already on disk) calls:

```php
$this->campaigns->isDisplayable($reference->referenceId());   // line 72
$this->campaigns->findActiveById($reference->referenceId());  // line 77
```

`isDisplayable` returns `bool`. `findActiveById` returns `?CampaignDTO` (null when not found or not active).

The `CampaignDTO` is a read-only value object passed back across the kernel boundary. Fields needed for reference rendering:
- `id` (string) — campaign id
- `title` (string) — display title
- `slug` (string) — public URL slug
- `currencyCode` (string) — ISO 4217
- `targetAmountMinor` (int) — for progress bar rendering
- `isActive` (bool) — already filtered by `findActiveById`, but kept for explicit access

## Files to create

| File | LOC (est.) | Purpose |
|---|---|---|
| `app/Payments/Contracts/CampaignQueryContract.php` | ~25 | Interface |
| `app/Payments/Domain/DTOs/CampaignDTO.php` | ~70 | Read-only VO |
| `app/Payments/Infrastructure/Adapters/CampaignQueryAdapter.php` | ~80 | Eloquent-style query adapter |
| `app/Payments/Providers/PaymentsServiceProvider.php` (edit) | +5 lines | Bind contract → adapter |

**Total: ~180 LOC across 3 new files + 1 binding edit.**

## Error handling

- `findActiveById` returns `null` when the campaign doesn't exist OR isn't active (state ≠ 'active' or deleted_at IS NOT NULL). The adapter logs a warning if the row exists but is soft-deleted or inactive.
- `isDisplayable` returns `false` in the same conditions.
- The adapter does **not** throw — it returns null/false. CMS callers treat null as "skip rendering this reference."

## Verification (after implementation)

1. **Static check:** `php -l` on all 3 new files.
2. **DI resolution smoke:**
   ```bash
   php artisan tinker --execute='
   echo app(\App\Cms\Services\ReferenceResolutionService::class)::class . PHP_EOL;
   echo app(\App\Cms\Services\StaticPageRendererService::class)::class . PHP_EOL;
   '
   ```
   Expected: both resolve without error.
3. **Adapter functional check:** create a campaign row via DB, call `findActiveById`, assert non-null DTO.
4. **Razorpay regression:**
   ```bash
   RAZORPAY_TEST_CONTAINER=temple-trust-app ./scripts/validate-razorpay.sh
   ```
   Expected: `=== Summary: pass=12 fail=0 fatal=0 ===`
5. **Full CMS smoke:** resolve all 15 CMS contracts + services — all should succeed.

## Risks & Mitigations

| Risk | Mitigation |
|---|---|
| Schema drift — `campaigns` columns don't match what `CampaignDTO` expects | Read `schema-neon/V1-schema.sql` first; align field names exactly |
| `PersistenceAdapterContract::query()` is async-ish — could it return Result or throw? | Already wrapped — adapter checks `$r->isFailure()` and returns null on failure |
| `ReferenceResolutionService` is consumed by `StaticPageRendererService` which is in turn consumed by routes — adding routes later might surface new failures | Scope: only the contract + adapter + binding; do NOT add routes in this spec |

## What This Spec Does NOT Cover

1. Adding a `CampaignRepositoryContract` (long-term home for the query logic). Out of scope for this fix.
2. Admin UI to manage campaigns. Out of scope.
3. CMS HTTP routes. Out of scope (separate "Kernel + minimal HTTP surface" brainstorm deferred).
4. Gallery / Events kernels. Separate modules.
5. Reverting the Razorpay spec's "fabricated mode" — that's still relevant until a real payment is captured.