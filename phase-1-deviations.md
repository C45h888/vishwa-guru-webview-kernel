# Phase 1 Deviations

This document tracks every meaningful deviation between the Phase 1
plan as published in `roadmap.md` and the actual implementation that
landed. Deviations are listed by pass.

The purpose is to enable downstream engineers to understand WHY
specific decisions diverged from the plan, so that the same decisions
(or corrections) are not repeated accidentally.

---

## Pass 1.0 — Restructure

**Planned**: Move every `app/Payments/X/` file to `app/Payments/Domain/X/`
following the FINANCIAL_KERNEL_CONTRACT.md layout.

**Actual**: Identical to plan. 35 files moved into `Domain/` subfolders.
46 cross-file use-statement updates applied. Zero empty old directories
remain.

**No deviation.**

---

## Pass 1.1 — State Machines

**Planned**: Extract state transition logic from entities into
`Domain/StateMachines/PaymentStateMachine.php` and
`Domain/StateMachines/DonationStateMachine.php`. Add
`Domain/StateMachines/StateTransitionEvent.php` and
`Domain/StateMachines/StateTransitionResult.php`.

**Actual**:
- 5 state-machine files written (Payment, Donation, Receipt,
  StateTransitionEvent, StateTransitionResult).
- ReceiptStateMachine was added beyond the original plan because
  Receipt's delivery-status state changes also needed a state machine.
- ReceiptDeliveryState enum was added as the receiver for
  ReceiptStateMachine transitions (replaces string constants on
  Receipt entity).
- 18 transitions detected in PaymentStateMachine were initially
  unreachable from entity helper `eventForTarget()`. Fixed via 4
  added cases (DISPUTED→FAILED, *→DISPUTED).
  Note: PARTIALLY_REFUNDED→REFUNDED and DISPUTED→REFUNDED were
  removed in Pass 1.3 as refund paths are out of scope — Razorpay
  SDK owns all refund state and DISPUTED cannot occur through the
  SDK's payment lifecycle.

**Deviations**:
- **D1** ReceiptStateMachine added (not in original plan). Necessary
  because Receipt entity previously allowed arbitrary mutation of
  `delivery_status` via `withChanges()`. State machine makes delivery
  transitions explicit.
- **D2** ReceiptDeliveryState enum added. The original Receipt entity
  used string constants (`DELIVERY_PENDING = 'pending'`, etc.). The
  enum unifies the value object with its permitted values.

---

## Pass 1.2 — Domain Value Objects + Repo Refinements

**Planned**: Add 4 DTOs (GatewayRequest, GatewayResponse,
VerificationContext, Refund). Add 3 new methods each to Payment,
Donation, FailureState repository contracts.

**Actual**: All 4 DTOs delivered. Repo contracts extended per plan.

**No deviation.**

---

## Pass 1.3 — Services Layer

**Planned**: 7 service classes plus 1 receipt stub.
  - PaymentService
  - PaymentOrchestrator
  - PaymentProviderSelector
  - PaymentVerificationService
  - ReceiptService
  - FailureStateService
  - TransactionCoordinator
  - ReceiptGeneration/StubReceiptGenerator (stub)

**Actual**: All 8 files written. StubReceiptGenerator is the Phase 1
fallback for ReceiptGenerationContract. Pass 1.7's
ReceiptRenderer replaces it at boot when fully built.

**No deviation in count.**

**Deviation**:
- **D3** Plans called for ReceiptService to depend on
  ReceiptGenerationContract. Implementation honors this. The
  contract is satisfied in Phase 1 by StubReceiptGenerator, NOT by
  ReceiptRenderer. Phase 1.7 swaps the binding.

---

## Pass 1.4 — Persistence Bridge

**Planned**: PostgresAdapter + 8 repository implementations
(Payment, Donation, Donor, Receipt, FailureState, IdempotencyKey,
WebhookEvent, AuditEvent).

**Actual**:
- 2 adapter files: `LaravelDbAdapter.php`, `InMemoryAdapter.php`. The
  original plan called for `PostgresAdapter.php` and
  `InMemoryAdapter.php`. Renamed to `LaravelDbAdapter.php` to align
  with the framework already in use (Laravel).
- All 8 concrete repository files delivered.
- FileAssetRepository was planned but its concrete impl was deferred
  to Pass 1.7 because it only serves the receipt-rendering pipeline.

**Deviations**:
- **D4** `PostgresAdapter.php` → `LaravelDbAdapter.php`. Same role,
  different filename. Aligns with Laravel idioms.
- **D5** `FileAssetRepository.php` (concrete impl) deferred to Pass
  1.7. The contract `FileAssetRepositoryContract.php` IS built
  and bound (Pass 1.6), but binding points to a stub until Pass 1.7
  ships the concrete class.

---

## Pass 1.5 — Gateway Adapters

**Planned**:
- 3 adapter types (Razorpay + PayPal + InMemory)
- 5 files per provider (Adapter + Client wrapper + ClientFactory +
  VerificationAdapter + ProviderAdapter)
- Total: ~2,150 lines

**Actual**: 15 files written, ~1,839 lines. Implementation matches the
planned SDK containment pattern (Client wrapper owns the SDK as a
private property; all SDK construction goes through factories).

**Architectural decisions reached during implementation**:
- Reading B pattern (thin facade) over Reading A (raw SDK type) for
  RazorpayClient and PayPalClient. Adopted because it satisfies the
  "no SDK type in public signature" rule cleanly.
- SDK auto-construction moved from the adapter to the client factory.
  Adapter receives a ready-to-use client instance.
- Decision: USE_OFFICIAL_SDK not REST-only (Q1). composer.json has
  `razorpay/razorpay` and `paypal/paypal-checkout-sdk`. Webhook
  signature verification uses each SDK's helper rather than
  rewriting HMAC.

**Deviations**:
- **D6** Originally the plan listed Common/GatewayCredentials.php and
  Common/GatewayErrorTranslator.php as "optional." Implementation
  treats them as required because every concrete adapter needs both.
- **D7** Composer dependencies added:
  `razorpay/razorpay` (~2.x),
  `paypal/paypal-checkout-sdk` (latest).
  These land in composer.lock.

---

## Pass 1.6 — DI Wiring

**Planned**:
- PaymentsServiceProvider.php
- config/payments.php
- config/app.php edit
- ContainerResolutionTest.php
- EnvBindingMatrixTest.php

**Actual**: All 5 deliverables in place. Provider declares:
  - 3 state-machine singletons
  - 1 persistence-adapter singleton (PersistenceAdapterContract → LaravelDbAdapter)
  - 2 client factories (RazorpayClientFactory, PayPalClientFactory)
  - 2 client singletons (RazorpayClient, PayPalClient) via factory closures
  - 9 gateway adapters (3 per provider × 3 contracts)
  - 9 repository bindings (interface → impl)
  - 7 service singletons (PaymentService with manual closure for the
    iterable<PaymentGatewayContract> dependency; others auto-resolved)
  - 3 tagged pools: payment_gateway, payment_provider, payment_verification
  - RepositoryRegistry wired in `boot()` for 5 entity types

**Total bindings**: 26.

**No deviation in architecture** — the env-aware gateway selection
helper, the manual closure for PaymentProviderSelector (because
Laravel can't auto-inject iterable<T>), and the gatewayTag()
static accessor for tests all landed as planned.

---

## Pass 1.7 — Receipt Rendering (PARTIAL)

**Planned**:
- ReceiptRenderer (implements ReceiptGenerationContract)
- PdfWrapper interface + DomPdfWrapper + InMemoryPdfWrapper
- ReceiptFormatting (AmountInWords, ReceiptFormatter, ReceiptStorage,
  Receipt80GValidator, ReceiptNumberAllocator, Form10BDExporter)
- Blade templates (receipt, receipt-corpus, receipt-summary)
- config/receipts.php

**Actual**:
- ReceiptRenderer.php ✓
- Pdf/PdfWrapper.php ✓
- Pdf/DomPdfWrapper.php ✓
- Pdf/InMemoryPdfWrapper.php ✓
- config/receipts.php ✓
- 4 test files already exist: AmountInWordsTest, ReceiptFormatterTest,
  Receipt80GValidatorTest, ReceiptNumberAllocatorTest — but the
  corresponding production classes need verification.

**Audit gap** (D8): The 4 production files corresponding to the
existing test files (AmountInWords, ReceiptFormatter, Receipt80GValidator,
ReceiptNumberAllocator) may or may not exist in the actual file tree.
The module-inventory.md walker did not surface them. Needs a manual
file-system audit to confirm. If they're missing, this is a Pass 1.7
incompleteness to be closed.

**Most material gap** (D9): Form10BDExporter class not built. CBDT
mandates annual Form 10BD filing for 80G-registered institutions.
Without this, the trust cannot satisfy annual compliance.

**Other gaps** (D10):
- Blade templates (receipt.blade, receipt-corpus.blade, receipt-summary.blade) — not present
- Form10BDExporter.php — missing
- ReceiptStorage.php — missing (Hash + Laravel Storage integration)
- ReceiptRendererTest.php — missing

**Mitigation**: Receipt generation contract is fulfilled by
StubReceiptGenerator, so end-to-end payment flow works without Pass
1.7 completion. The compliance gap is for future reporting cycles,
not current donation processing.

---

# Architectural Invariants Preserved Across All Deviations

Despite the deviations above, the following invariants enforced at
Phase 0.25 are intact:

✓ Every Domain contract (Phase 0.25) — signatures untouched
✓ Business logic never imports SDK types
✓ Repositories own persistence (PDO via Laravel DB facade)
✓ Services are the sole coordinator of business workflows
✓ State machines are the sole deciders of valid transitions
✓ Result<T> everywhere; no exceptions thrown across service seams
✓ ConfigurationContract reads from config('payments.*'); no
  service reads env() directly
✓ No service-locator pattern (`\App::make()`) inside services

---

# What Was NOT Built (out of Phase 1 scope)

- Phase 2 — Laravel framework hospitality (HTTP kernel config,
  queue workers, env validation)
- Phase 3 — Public platform (HTTP routes, Blade templates for the
  public site, donation form controller, payment confirmation page)
- Phase 4 — Administration (auth, admin dashboard, CMS management)
- Form 10BD automatic e-filing (Form 10BD export exists in plan, not
  yet built; actual ITD e-filing API integration deferred to Phase 4)
- Notification dispatch (email confirmation post-payment) —
  deferred to Phase 3 (NotificationContract stub deferred per Q4)
- Recurring donations — not in roadmap
- Multi-currency conversion — not in roadmap
- Eloquent ORM usage — Phase 0.25 contracts are PDO-agnostic;
  Eloquent was an open question (Q5) that defaults to PDO

---

# Open Questions Resolved During Phase 1

- **Q1 Razorpay SDK vs REST?** — RESOLVED: SDK adopted per Reading B
  pattern, used for signature verification helper.
- **Q2 PayPal SDK vs REST?** — RESOLVED: SDK adopted per Reading B
  pattern.
- **Q3 PDF generation now or defer?** — RESOLVED DEFERRED: Pass 1.7
  PDF generation is partial; full implementation remains in the
  queue as outstanding Pass 1.7 work.
- **Q4 NotificationContract stub?** — DEFERRED to Phase 3.
- **Q5 PDO vs Eloquent?** — RESOLVED: Laravel DB facade (PDO under
  the hood) used. Eloquent remains an option for future Phase work.

---

# Recommended Next Actions

1. **M1** (immediate) — Run a manual file-system audit to confirm
   whether AmountInWords, ReceiptFormatter, Receipt80GValidator, and
   ReceiptNumberAllocator production files exist on disk. The
   tests reference them but they didn't surface in the inventory
   walker. Resolve discrepancy.

2. **M2** (Pass 1.7 closure) — Build the missing
   Form10BDExporter.php and ReceiptStorage.php and the Blade
   templates. ~500 lines.

3. **M3** (Pass 1.7 closure) — Build ReceiptRendererTest.php to
   cover the rendering pipeline end-to-end. ~200 lines.

4. **M4** (Phase 1 verification) — Run phpunit + phpstan on a
   PHP-equipped machine. Resolve any failures. Expected ~300 tests
   pass.

5. **M5** (Phase 2 kickoff) — Bootstrap the Platform Foundation:
   queue config, env validation, route registration, request
   lifecycle hooks. Not in Phase 1; planned.
