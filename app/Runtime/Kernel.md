# Runtime Kernel

> One-screen navigation map for the `Runtime` kernel.
> Read order: Boundaries → Contracts → Providers → FSMs → Module class → Tests.

## Boundaries

**Owns:** runtime substrate — failures, diagnostics, exceptions, console
commands, validation, and HTTP middleware. Owns the failure-state
machine (`FailureStateMachine`) that Payments consumes. Owns the
diagnostic console commands under `app/Runtime/Diagnostics/` and
`app/Runtime/Console/`.

**Does NOT own:** persistence, queueing, caching, or HTTP routing (those
are Laravel framework concerns; Runtime owns only the kernel-specific
additions).

**Outbound edges:**
- Into Shared for configuration + environment reads.
- Into Redis for diagnostics storage (queue depth, idempotency stats).

## Contracts

**None** — Runtime does not declare a `Contracts/` directory. Consumers
(Payments) bind `App\Payments\Contracts\FailureStateContract` from the
Payments side; Runtime exposes the implementation directly via the
container.

This asymmetry is by design: Runtime is a **consumed substrate** (other
kernels reach into it for failure handling, diagnostics, validation),
not a **published substrate** (other kernels don't discover its
internals). The `FailureStateContract` is a Payments-side alias for
the Payments consumer; the Runtime side exposes the concrete
`FailureStateMachine` for the Payments kernel to bind against.

## Providers

`App\Runtime\Providers\RuntimeServiceProvider` — pinned position in the
provider order: Shared → Persistence → **Runtime** → Redis → Queue →
Payments → Cms → Campaigns → Gallery → Events. Boots third so it can
publish the failure + diagnostic surfaces before Payments reaches into
them.

Bindings:
- `FailureStateMachine` singleton
- `DiagnosticsRegistry` singleton (collects diagnostic reporters)
- Console command registrations under `app/Runtime/Console/`
- Validation rule registrations under `app/Runtime/Validation/`
- Exception handler hooks under `app/Runtime/Exceptions/`
- HTTP middleware registrations under `app/Runtime/Http/`

## FSMs

`App\Runtime\Failure\StateMachines\FailureStateMachine` — handwritten FSM
covering the failure-state graph (detected → isolated → reported →
recovered | permanent-failure). Returns `FailureTransitionResult`
envelope. Payments consumes this via `FailureStateContract` (Payments-side
contract that binds to the Runtime concrete).

Tests: `tests/Unit/Runtime/Failure/StateMachines/`,
`tests/Feature/Runtime/Failure/` (incl. `Handlers/`).

## Module class

**Intentionally absent.** No `RuntimeModule.php`.

**Why:** Runtime is the runtime substrate consumed by every other
kernel — declaring a peer Module would conflate substrate with business.
Runtime is published into the Laravel container by
`RuntimeServiceProvider` and consumed by injection; the `ModuleContract`
discovery seam is reserved for content kernels.

## Tests

Largest kernel test surface after Payments:

- `tests/Unit/Runtime/Diagnostics/` — diagnostic reporter unit tests.
- `tests/Unit/Runtime/Failure/` — failure state machine unit tests
  (incl. `Enums/` — `FailureState`, `FailureSeverity`, etc.).
- `tests/Unit/Runtime/Validation/` — custom validation rule unit tests.
- `tests/Feature/Runtime/Console/` — artisan command integration tests.
- `tests/Feature/Runtime/Diagnostics/` — diagnostic reporter integration.
- `tests/Feature/Runtime/Failure/` — failure-handling integration
  (incl. `Handlers/`).
- `tests/Feature/Runtime/Http/` — middleware integration tests.
- `tests/Feature/Runtime/Validation/` — request-validation integration.