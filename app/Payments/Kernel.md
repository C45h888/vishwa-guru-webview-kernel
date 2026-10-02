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
anonymous donations remain unlinked. It also records per-donation Terms
acceptance, Privacy Notice acknowledgment, and separately optional email-
marketing consent. The densest cross-kernel hub in the system.

**Does NOT own:** the cause-side (`Campaigns` — what the money is for),
the static content that hosts the donation form (`Cms`), or the auth
identity making the payment (Phase 4 `Auth`).

**Outbound edges (6 inbound cross-kernel edges — see doctrine note below):**
- Into Campaigns via `App\Campaigns\Contracts\CampaignsQueryContract`
  (orchestrator needs to know the target of an intent).
- Into Cms via `App\Cms\Contracts\PublicMediaQueryContract` (receipt
  rendering needs the campaign banner / brand block).
- Into Shared for identifier generation (`EntityId`), configuration
  (`ConfigurationContract`), current legal-policy versions
  (`App\Shared\Policies\LegalPolicyVersions`), and the Redis-backed
  idempotency contract.
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
| ReceiptGenerationContract | `App\Payments\Contracts\ReceiptGenerationContract` | Implemented by `App\Payments\Receipts\ReceiptSubstrate` (the receipt substrate package: data → types → design workers) |
| CampaignQueryContract | `App\Payments\Contracts\CampaignQueryContract` | **Shape A bridge** — owned by Payments; Cms consumes |
| FailureStateContract | `App\Payments\Contracts\FailureStateContract` | Payments consumes Runtime's failure surface |

Plus internal contracts under `App\Payments\Domain\Repositories\` and
`App\Payments\Infrastructure\Adapters\`.

**Receipt generation binding.** Receipt creation is a consequence of
payment validation, not of a specific capture path. On a successful
transition (verify callback, reconcile, or webhook), the orchestrator
dispatches the domain event
`App\Payments\Domain\Events\PaymentValidated` after the capture commits.
`App\Payments\Services\ReceiptIssuanceCoordinator` listens, and dispatches
`App\Payments\Jobs\GenerateReceiptJob` onto the dedicated `receipts`
queue (owned by the `receipts-worker` container). `receipts:reconcile`
(scheduled by the `scheduler` container) backfills any miss. Delivery at
launch is the success-page PDF download; email is a later pass.

**Receipt substrate package** (`app/Payments/Receipts/`) — the canonical
receipt generation surface. `ReceiptSubstrate` is the parent that holds
all generation logic (assembly, the `verify80G()` certification function,
money/date/FY/address formatting, donee identity); semantic work is
split across worker boundaries that import their logic from the parent:

- `Workers/DataWorker.php` — pure transport of data (payment, donation,
  campaign, existing receipt rows). No logic.
- `Workers/TypesWorker.php` — builds the typed `ReceiptDocument`
  (`App\Payments\Domain\DTOs\ReceiptDocument`) — the single snapshot the
  designed PDF renders AND the persisted receipts row is issued from.
- `Workers/DesignWorker.php` — compiles
  `resources/views/receipts/design/` (template + tokenised style layer)
  into PDF bytes. Design lives only there; generation never sees CSS.
- `Workers/WorkerCadence.php` — per-worker timeout budget + bounded
  retries (config `receipts.workers`), so stage failures fall into the
  queue-level retry ladder instead of hanging it.

Supersedes (absorbed, files deleted): `ReceiptRenderer`,
`ReceiptFormatter`, `Receipt80GValidator`, `ReceiptPdfGenerator`,
`StubReceiptGenerator`, and the loose `resources/views/receipts/*.blade.php`
templates. The design file system is `resources/views/receipts/design/`.

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
  (`Adapters/`) + receipt support primitives (`Receipts/`: allocator,
  storage, amount-in-words, Form 10BD).
- `tests/Unit/Payments/Receipts/` — receipt substrate package
  (`ReceiptSubstrateTest`: generation logic + verify80G + formatting +
  document-is-persisted-snapshot guarantee; `Workers/WorkerCadenceTest`,
  `Workers/DesignWorkerTest`).
- `tests/Unit/Payments/Services/` — payment + idempotency service tests.
- `tests/Unit/Payments/Jobs/` — async job handler tests.
- `tests/Feature/Payments/Infrastructure/` — end-to-end payment flow
  tests against the in-memory SQLite backend.
- `tests/Feature/Payments/Receipts/` — receipt-rendering integration.
- `database/seeders/ProductionDonorProjectionSeeder.php` — transactional,
  rerunnable production projection and donor rollup rebuild; it is not
  part of the ordinary content seeder.
- `tests/Unit/Payments/Http/Requests/RazorpayCheckoutRequestTest.php` — current policy versions, required Terms/Privacy acknowledgments, and optional marketing consent validation.
- `tests/Feature/Payments/Infrastructure/DonationRepositoryTest.php` — checkout policy and optional marketing-consent persistence round-trip.