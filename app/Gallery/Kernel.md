# Gallery Kernel

> One-screen navigation map for the `Gallery` kernel.
> Read order: Boundaries → Contracts → Providers → FSMs → Module class → Tests.

## Boundaries

**Owns:** the public read surface for photo galleries (gallery covers +
image grids). Two V1 entities: `Gallery`, `GalleryImage`. Owns no
mutations in V1 (Phase 4 admin arrives later).

**Does NOT own:** file-asset storage (`Payments` via
`FileAssetRepositoryContract` — gallery images reference files managed by
the Payments kernel).

**Outbound edges:**
- Into Payments via `App\Payments\Contracts\FileAssetRepositoryContract`
  (image-file storage — Phase 4 admin).
- Into Cms via `App\Cms\Contracts\PublicMediaQueryContract` for cover-image
  resolution (read path).
- Into Shared for identifier generation.

## Contracts

| Contract | FQCN | Direction |
|---|---|---|
| GalleryQueryContract | `App\Gallery\Contracts\GalleryQueryContract` | Public read (Phase 3 UI consumes) |
| GalleryRepositoryContract | `App\Gallery\Domain\Repositories\GalleryRepositoryContract` | Internal kernel contract |

Single public contract; this is the smallest of the four content
kernels. The tight surface reflects the kernel's read-only V1 scope.

## Providers

`App\Gallery\Providers\GalleryServiceProvider` — pinned position in the
provider order: Shared → Persistence → Runtime → Redis → Queue → Payments
→ Cms → Campaigns → **Gallery** → Events. Boots after Campaigns so it can
resolve contract surfaces Campaigns declares.

Bindings:
- `GalleryRepositoryContract → EloquentGalleryRepository`
- `GalleriesQueryService` singleton + `GalleryQueryContract → GalleriesQueryService`
- `GalleryImageRepositoryContract → EloquentGalleryImageRepository`

`boot()` registers the `gallery` + `gallery_image` entity types against
`RepositoryRegistryContract`.

## FSMs

**None.** State transitions are validated at the FormRequest boundary
(Phase 4 doctrine; `agents.md` § Phase 4).

## Module class

Present: `App\Gallery\GalleryModule` (implements
`App\Shared\Contracts\ModuleContract`).

`dependencies()`: `App\Shared\Contracts\ModuleContract` only. V1 is
read-only — no Shape A bridges in or out. The Phase 3 UI consumes this
contract directly via the Cms module's `dependencies()` list.

## Tests

- `tests/Unit/Gallery/Domain/` — entity + value-object unit tests (incl.
  `DTOs/`).
- `tests/Feature/Gallery/Infrastructure/` — repository round-trip tests
  against the in-memory SQLite backend.