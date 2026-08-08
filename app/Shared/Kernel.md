# Shared Kernel

> One-screen navigation map for the `Shared` kernel.
> Read order: Boundaries → Contracts → Providers → FSMs → Module class → Tests.

## Boundaries

**Owns:** the architectural root — primitive contracts and concrete
implementations every kernel depends on. Configuration, environment,
identifier generation (`EntityId` is in `Persistence/ValueObjects` but
is consumed universally), exceptions, enums, support classes, and value
objects.

**Does NOT own:** persistence (the `EntityId` lives in `Persistence/ValueObjects/`),
queueing, caching, HTTP — those are separate kernels with their own
contracts.

**Outbound edges:**
- None — Shared is a dependency *root*.

## Contracts

The largest contract surface in the codebase (seven contracts — every
kernel imports at least one):

| Contract | FQCN | Direction |
|---|---|---|
| ModuleContract | `App\Shared\Contracts\ModuleContract` | The contract four content kernels (Campaigns/Cms/Events/Gallery) implement via `*Module.php` |
| ModuleServiceProviderContract | `App\Shared\Contracts\ModuleServiceProviderContract` | Service provider contract |
| ServiceContract | `App\Shared\Contracts\ServiceContract` | Service marker |
| RepositoryContract | `App\Shared\Contracts\RepositoryContract` | Repository marker |
| ValueObjectContract | `App\Shared\Contracts\ValueObjectContract` | Value object marker |
| ConfigurationContract | `App\Shared\Contracts\ConfigurationContract` | Read access to typed configuration (consumed by every kernel's service provider) |
| EnvironmentContract | `App\Shared\Contracts\EnvironmentContract` | Environment introspection (dev/prod/staging, driver names) |

## Providers

`App\Shared\Providers\SharedServiceProvider` — pinned **first** in the
provider order: **Shared** → Persistence → Runtime → Redis → Queue →
Payments → Cms → Campaigns → Gallery → Events. Every other kernel's
provider depends on Shared being registered first because every kernel
consumes `ConfigurationContract` + `EnvironmentContract` during its
own `register()`.

Bindings:
- `ConfigurationContract → Configuration` (singleton)
- `EnvironmentContract → Environment` (singleton)
- Module discovery: scans `App\*\CampaignsModule|CmsModule|EventsModule|GalleryModule`
  (the four kernels that implement `ModuleContract`); consumes
  `ModuleServiceProviderContract`.

## FSMs

**None.** Shared is an architectural root with no business lifecycle.

## Module class

**Intentionally absent.** No `SharedModule.php`.

**Why:** Shared is the dependency root — declaring
`SharedModule::dependencies()` would cycle, since every other Module
already declares `App\Shared\Contracts\ModuleContract` in its own
`dependencies()` array. Shared consumes nothing; it publishes the
contracts every kernel reaches through.

## Tests

- `tests/Unit/Shared/Enums/` — shared enum unit tests.
- `tests/Unit/Shared/Support/` — shared support-class unit tests.
- `tests/Unit/Shared/ValueObjects/` — shared value-object unit tests.