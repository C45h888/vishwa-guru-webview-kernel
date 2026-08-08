# Campaigns Kernel

> One-screen navigation map for the `Campaigns` kernel.
> Read order: Boundaries → Contracts → Providers → FSMs → Module class → Tests.

## Boundaries

**Owns:** the cause-side lifecycle of a temple donation campaign
(slug, target, dates, state). Read-side queries + Phase 4 admin mutations.

**Does NOT own:** the money-side lifecycle (payments, intents, captures,
receipts, refunds) — that is `app/Payments/`. The two meet only at
`App\Payments\Contracts\CampaignQueryContract` (Shape A bridge; Payments owns
the contract because the producing kernel declares the boundary).

**Outbound edges:**
- Reads from Cms via `App\Cms\Contracts\PublicMediaQueryContract` + Cms media
  state for cover-image and gallery references (admin authoring).
- Reaches into Payments via `App\Payments\Contracts\FileAssetRepositoryContract`
  (transitively — file asset storage is a Payments responsibility).
- Reads from Shared for identifier generation, configuration, environment.

## Contracts

| Contract | FQCN | Direction |
|---|---|---|
| CampaignsQueryContract | `App\Campaigns\Contracts\CampaignsQueryContract` | Public read (Phase 3 UI consumes) |
| CampaignAuthoringContract | `App\Campaigns\Contracts\CampaignAuthoringContract` | Phase 4 Admin mutations |
| CampaignRepositoryContract | `App\Campaigns\Domain\Repositories\CampaignRepositoryContract` | Internal kernel contract |

## Providers

`App\Campaigns\Providers\CampaignsServiceProvider` — bound as a singleton in
`bootstrap/providers.php`. The 9 boot-order invariants in
`tests/Unit/Bootstrap/ProviderOrderTest.php` pin Shared → Persistence →
Runtime → Redis → Queue → Payments → Cms → **Campaigns** → Gallery → Events.
Campaigns boots after Cms so it can resolve Cms contracts during register().

Bindings (see `register()` for axis labels):
- `CampaignRepositoryContract → EloquentCampaignRepository`
- `CampaignsQueryService` singleton + `CampaignsQueryContract → CampaignsQueryService`
- `CampaignAuthoringContract → CampaignAuthoringService` (Phase 4)
- `CampaignCoverUploadService` singleton

`boot()` registers the `campaign` entity type against
`RepositoryRegistryContract`.

## FSMs

**None.** State transitions are validated at the FormRequest boundary (Phase 4
doctrine; `agents.md` § Phase 4). Internal lifecycle fields (`state`,
`display_order`) are not externally mutated through a state machine.

## Module class

Present: `App\Campaigns\CampaignsModule` (implements
`App\Shared\Contracts\ModuleContract`).

`dependencies()`: `App\Shared\Contracts\ModuleContract` only. V1 is
read-only — no Shape A bridges in or out. The Phase 3 UI consumes this
contract directly via the Cms module's `dependencies()` list (CMS bridges
into Campaigns).

## Tests

- `tests/Unit/Campaigns/Domain/` — entity + value-object unit tests
  (incl. `DTOs/`).
- `tests/Feature/Campaigns/Infrastructure/` — repository round-trip tests
  against the in-memory SQLite backend.
- `tests/Feature/Admin/Campaigns/` — Phase 4 Admin Kernel authoring tests
  (HTTP-level coverage of `CampaignAuthoringService`).