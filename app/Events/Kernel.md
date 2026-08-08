# Events Kernel

> One-screen navigation map for the `Events` kernel.
> Read order: Boundaries → Contracts → Providers → FSMs → Module class → Tests.

## Boundaries

**Owns:** the public read surface for temple events (upcoming + past),
plus Phase 4 admin mutations. Single V1 entity: `Event`. Owns the
content-rendered journal articles (`app/Events/Content/event-articles.php`).

**Does NOT own:** campaign state (`Campaigns`), media asset storage
(`Payments` via `FileAssetRepositoryContract`), gallery assets (`Gallery`).

**Outbound edges:**
- Into Payments via `App\Payments\Contracts\FileAssetRepositoryContract`
  (banner image storage — Phase 4 admin).
- Into Cms via `App\Cms\Contracts\PublicMediaQueryContract` for cover-image
  resolution (read path).
- Into Shared for identifier generation.

**Path-pinned:** `app/Events/Content/event-articles.php` is referenced via
`app_path('Events/Content/event-articles.php')` from three controllers in
`app/Http/Controllers/Public/Events/` (`IndexController`,
`JournalIndexController`, `JournalShowController`). This directory stays
at `app/Events/Content/` and is not reshuffled.

## Contracts

| Contract | FQCN | Direction |
|---|---|---|
| EventsQueryContract | `App\Events\Contracts\EventsQueryContract` | Public read (Phase 3 UI consumes) |
| EventAuthoringContract | `App\Events\Contracts\EventAuthoringContract` | Phase 4 Admin mutations |
| EventRepositoryContract | `App\Events\Domain\Repositories\EventRepositoryContract` | Internal kernel contract |

## Providers

`App\Events\Providers\EventsServiceProvider` — pinned last in the provider
order: Shared → Persistence → Runtime → Redis → Queue → Payments → Cms →
Campaigns → Gallery → **Events**. Boots last because it depends on
contract resolution from every earlier kernel (read-only consumers of
Cms, Payments, Gallery cross-kernel surfaces).

Bindings:
- `EventRepositoryContract → EloquentEventRepository`
- `EventsQueryService` singleton + `EventsQueryContract → EventsQueryService`
- `EventAuthoringContract → EventAuthoringService` (Phase 4)
- `EventBannerUploadService` singleton

`boot()` registers the `event` entity type against
`RepositoryRegistryContract`.

## FSMs

**None.** State transitions are validated at the FormRequest boundary
(Phase 4 doctrine; `agents.md` § Phase 4).

## Module class

Present: `App\Events\EventsModule` (implements
`App\Shared\Contracts\ModuleContract`).

`dependencies()`: `App\Shared\Contracts\ModuleContract` only. V1 is
read-only — no Shape A bridges in or out. The Phase 3 UI consumes this
contract directly via the Cms module's `dependencies()` list.

## Tests

- `tests/Unit/Events/Domain/` — entity + value-object unit tests (incl.
  `DTOs/`).
- `tests/Feature/Events/Infrastructure/` — repository round-trip tests
  against the in-memory SQLite backend.
- `tests/Feature/Admin/Events/` — Phase 4 Admin Kernel authoring tests
  (HTTP-level coverage of `EventAuthoringService`).