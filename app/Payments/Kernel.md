# Payments Kernel

> One-screen navigation map for the `Payments` kernel.
> Read order: Boundaries → Contracts → Providers → FSMs → Module class → Tests.

## Boundaries

**Owns:** the money-side lifecycle end-to-end (intent → verify →
capture → receipt → refund). Razorpay + PayPal gateway adapters,
payment orchestration, idempotency-via-Redis, receipt generation, and
file-asset storage (the kernel that owns money also owns the files money
needs to point at). It also owns the donor CRM projection: identified
profiles are created or linked only after verified payment, while
anonymous donations remain unlinked. The densest cross-kernel hub in the system.

**Does NOT own:** the cause-side (`Campaigns` — what the money is for),
the static content that hosts the donation form (`Cms`), or the auth
identity making the payment (Phase 4 `Auth`).

**Outbound edges (6 inbound cross-kernel edges — see doctrine note below):**
- Into Campaigns via `App\Campaigns\Contracts\CampaignsQueryContract`
  (orchestrator needs to know the target of an intent).
- Into Cms via `App\Cms\Contracts\PublicMediaQueryContract` (receipt
  rendering needs the campaign banner / brand block).
- Into Shared for identifier generation (`EntityId`), configuration
  (`ConfigurationContract`), and the Redis-backed idempotency contract.
- Into Runtime for `App\Runtime\Contracts\FailureStateContract` and
  the failure-state-machine path (`App\Runtime\Failure\StateMachines\FailureStateMachine`).
- Into Persistence via `App\Persistence\Contracts\RepositoryRegistryContract`
  (repository indirection).
- Into Redis via `App\Redis\Contracts\RedisConnectorContract` (idempotency
  `SETEX` dedupe).

## Contracts

| Contract | FQCN | Producer/Consumer |
|---|---|---|
| PaymentGatewayContract | `App\Payments\Contracts\PaymentGatewayContract` | Razorpay + PayPal adapters implement |
| PaymentProviderContract | `App\Payments\Contracts\PaymentProviderContract` | Provider factory |
| PaymentVerificationContract | `App\Payments\Contracts\PaymentVerificationContract` | Webhook signature verification |
| PaymentReconciliationContract | `App\Payments\Contracts\PaymentReconciliationContract` | Optional gateway capability: authoritative order-status reconciliation (Razorpay adapter implements; consumed by PaymentOrchestrator::reconcileOrder) |
| ReceiptGenerationContract | `App\Payments\Contracts\ReceiptGenerationContract` | Receipt rendering |
| CampaignQueryContract | `App\Payments\Contracts\CampaignQueryContract` | **Shape A bridge** — owned by Payments; Cms consumes |
| FailureStateContract | `App\Payments\Contracts\FailureStateContract` | Payments consumes Runtime's failure surface |

Plus internal contracts under `App\Payments\Domain\Repositories\` and
`App\Payments\Infrastructure\Adapters\`.

## Providers

`App\Payments\Providers\PaymentsServiceProvider` — pinned position: Shared
→ Persistence → Runtime → Redis → Queue → **Payments** → Cms → Campaigns
→ Gallery → Events. Boots after Queue so it can resolve queue-backed
idempotency + retry policies; before Cms because Cms depends on
`CampaignQueryContract` (a Payments contract) at boot time.

Bindings (largest in the codebase — see `register()` for axis labels):
- All payment + receipt + provider contracts → concrete services
- `FailureStateContract → RuntimeFailureStateAdapter`
- `FileAssetRepositoryContract → EloquentFileAssetRepository`
- Gateway adapters (`RazorpayGateway`, `PayPalGateway`) bound conditionally
  on configuration presence
- All FSMs as singletons (`PaymentStateMachine`, `DonationStateMachine`,
  `ReceiptStateMachine`)
- Receipt rendering pipeline
- Repository registry entries for `payment`, `donation`, `receipt`,
  `file_asset`, `currency`, `payment_provider`

## FSMs

Four handwritten FSMs (the kernel owns the most state machines):

- `App\Payments\Domain\StateMachines\PaymentStateMachine` — intent →
  authorized → captured → settled → refunded (with failure branches).
- `App\Payments\Domain\StateMachines\DonationStateMachine` — donation
  record lifecycle (initiated → paid → receipted → failed).
- `App\Payments\Domain\StateMachines\ReceiptStateMachine` — receipt
  generation state (pending → rendered → delivered → invalidated).
- Shared types: `StateTransitionEvent`, `StateTransitionResult` (the
  return-type envelope every Payments FSM uses).

Plus the consumed Runtime FSM:
- `App\Runtime\Failure\StateMachines\FailureStateMachine` —
  not Payments-owned; consumed via `FailureStateContract`.

Tests: `tests/Unit/Payments/Domain/StateMachines/`.

## Module class

**Intentionally absent.** `PaymentsModule.php` does not exist and is
**not** added by this restructure (Phase 2 decision recorded in
`/Users/kamii/.claude/plans/jazzy-bubbling-wigderson.md`).

**Why:** Payments is the densest cross-kernel hub — 6 inbound edges from
other kernels plus transitive dependencies into every infrastructure
kernel. A `ModuleContract::dependencies()` declaration would have to
list all 6 inbound contracts plus every transitive infrastructure
dependency, creating reverse-coupling that
`PaymentsServiceProvider::boot()` against `RepositoryRegistryContract`
already expresses more cleanly. The provider's `register()` is the
wiring surface; the Module contract is reserved for content kernels
that need cross-kernel contract discovery (Campaigns, Cms, Events,
Gallery — all read-only V1 consumers of other kernels).

## Tests

The largest test surface in the codebase:

- `tests/Unit/Payments/Domain/` — entities, value objects, DTOs, enums,
  state machines (per-FSM subdirectory).
- `tests/Unit/Payments/Infrastructure/` — adapter round-trips
  (`Adapters/`) + receipt rendering (`Receipts/`).
- `tests/Unit/Payments/Services/` — payment + idempotency service tests.
- `tests/Unit/Payments/Jobs/` — async job handler tests.
- `tests/Feature/Payments/Infrastructure/` — end-to-end payment flow
  tests against the in-memory SQLite backend.
- `tests/Feature/Payments/Receipts/` — receipt-rendering integration.
- `database/seeders/ProductionDonorProjectionSeeder.php` — transactional,
  rerunnable production projection and donor rollup rebuild; it is not
  part of the ordinary content seeder.
- `tests/Unit/Http/Requests/Payments/` — `RazorpayCheckoutRequest`
  unit tests. **Note:** this directory is moved to
  `tests/Unit/Payments/Http/Requests/` in Phase 4 of the restructure.