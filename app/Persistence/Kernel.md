# Persistence Kernel

> One-screen navigation map for the `Persistence` kernel.
> Read order: Boundaries → Contracts → Providers → FSMs → Module class → Tests.

## Boundaries

**Owns:** the persistence-adapter boundary — single contract surface
(`PersistenceAdapterContract`) that every kernel's Eloquent-via-X
repository reaches through. Owns the `RepositoryRegistry` (entity type
→ repository class) and the `EntityId` value object. Owns the
Neon-PostgreSQL DoctrineCompliance substrate (`app/Persistence/Neon/`).

**Does NOT own:** business logic, gateway integrations, payment
orchestration, or HTTP controllers. Persistence is a substrate, not a
business capability.

**Outbound edges:**
- None — Persistence is a dependency *root*, not a consumer.

**Path-pinned:** `app/Persistence/Neon/` is referenced by
`tests/Feature/Persistence/Neon/DoctrineComplianceTest.php` (three
hardcoded `__DIR__` paths). This directory stays at
`app/Persistence/Neon/` and is not reshuffled.

## Contracts

| Contract | FQCN | Direction |
|---|---|---|
| PersistenceAdapterContract | `App\Persistence\Contracts\PersistenceAdapterContract` | Single persistence surface every kernel reaches through |
| RepositoryRegistryContract | `App\Persistence\Contracts\RepositoryRegistryContract` | Entity-type → repository-class lookup (consumed by every content kernel at boot) |
| EntityContract | `App\Persistence\Contracts\EntityContract` | Marker interface for entities that register with the registry |

Three contracts; the smallest contract surface in the codebase. This is
by design — the kernel exposes only the seams other kernels need.

## Providers

`App\Persistence\Providers\PersistenceServiceProvider` — pinned **second**
in the provider order: Shared → **Persistence** → Runtime → Redis →
Queue → Payments → Cms → Campaigns → Gallery → Events. The
`RepositoryRegistry` singleton must be registered before any other
kernel's `boot()` calls `$registry->register(...)`.

Bindings:
- `PersistenceAdapterContract → EloquentPersistenceAdapter` (the single
  binding the entire codebase depends on)
- `RepositoryRegistryContract → RepositoryRegistry` (singleton)
- `EntityId` value object auto-resolves (no explicit binding)

`boot()` registers the `entity_id` value object with the
RepositoryRegistry.

## FSMs

**None.** Persistence has no business lifecycle; it is a substrate.

## Module class

**Intentionally absent.** No `PersistenceModule.php`.

**Why:** The `ModuleContract` encodes business capability, not
infrastructure capability. Every content kernel already declares
`App\Shared\Contracts\ModuleContract` (the architectural root); adding
`PersistenceModule` with a `dependencies()` list would create a
peer-of-content-kernel module that isn't a peer. Persistence is a
substrate consumed by every other kernel — declaring it as a `Module`
would conflate substrate with business.

## Tests

- `tests/Unit/Persistence/Neon/` — DoctrineCompliance unit tests
  (schema-shape invariants against the canonical Neon DDL).
- `tests/Unit/Persistence/Neon/ValueObjects/` — `EntityId` + related
  value-object unit tests.
- `tests/Unit/Persistence/ValueObjects/` — generic value-object tests.
- `tests/Feature/Persistence/Neon/` — DoctrineCompliance integration
  tests (incl. `Console/`, `Diagnostics/`).