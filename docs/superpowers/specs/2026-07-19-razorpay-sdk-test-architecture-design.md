# Razorpay SDK Test Architecture — Design Spec

**Date:** 2026-07-19
**Status:** Awaiting review
**Owner:** Payments kernel
**Scope:** Phase 2 SDK integration validation

## Context

The Temple Trust runtime has the canonical Razorpay PHP SDK (`razorpay/razorpay 2.9.3`) integrated behind three Doctrine-aligned adapters (`RazorpayClient`, `RazorpayAdapter`, `RazorpayVerificationAdapter`). Existing unit tests use reflection-injected mocks — no test currently exercises the real SDK against the real Razorpay API. This means **we don't have proof the integration actually works against Razorpay** — only proof that our mocks are internally consistent.

The user has explicitly directed: "test the production code with the Razorpay SDK to see if the complete flow is working inside of the system ... encapsulated ... to basically invoke it and check if the complete flow is working inside of the code base with the use of shell script which runs with the docker simulation ground."

This spec adds a hermetic, end-to-end test harness that exercises the canonical SDK against Razorpay's live **test mode** API.

## Decisions locked in (from brainstorming Q&A)

| Q | Decision | Source |
|---|---|---|
| SDK realism | **Real Razorpay sandbox** (`rzp_test_*` keys against `api.razorpay.com`) | User 2026-07-19 |
| Test scope | **Medium** — full payment lifecycle with domain orchestration wired together (12 probes) | User 2026-07-19 |
| Credentials location | **`.env.testing`** (Laravel convention; gitignored; auto-loaded when `APP_ENV=testing`) | User 2026-07-19 |
| Captured-payment dependency | **Option A** — hardcoded Razorpay-published sample `pay_*` ID + fixture-based webhook payload templates | User 2026-07-19 |

## Goals (in scope)

1. **Prove the canonical Razorpay SDK works against the live test API** for every public method on `RazorpayClient` (createOrder, fetchOrder, fetchPayment, refundPayment, verifyWebhookSignature)
2. **Prove our Doctrine-aligned adapters correctly translate between Razorpay wire format and our domain types** (PaymentRequest ↔ Razorpay payload, Razorpay status string ↔ TransactionStatus, Razorpay webhook payload ↔ verification result)
3. **Prove the full domain flow works** — Donation entity → PaymentService → RazorpayClient → webhook signature verification → donation state transition
4. **Hermetic test harness** — runs from a single shell script inside the docker container, exits non-zero on any failure
5. **Safety guard** — refuses to run if `RAZORPAY_KEY_ID` doesn't start with `rzp_test_` (the current `.env` has `rzp_live_*` checked in)

## Non-goals (out of scope)

- **HTTP webhook transport** (Wide scope) — sending real HTTP POST to our own webhook endpoint. Probes call `PaymentService::handleWebhook()` directly.
- **PayPal probes** — separate task, same pattern.
- **Auto-cleanup of test data** on Razorpay dashboard — manual cleanup via dashboard.
- **Replacing `RazorpayClient::verifyWebhookSignature()` local HMAC with `Razorpay\Api\Utility::verifyWebhookSignature()`** — note as future consolidation, not blocking.
- **Fixing the `routes/webhook.php` middleware declaration gap** — out of scope; flagged as separate issue.

## Architecture

### Components

| Component | Path | Purpose |
|---|---|---|
| **Orchestrator** | `scripts/validate-razorpay.sh` | Bash wrapper: boots container with `APP_ENV=testing`, runs each probe, reports pass/fail, exits non-zero on any failure. Mirrors `scripts/validate-phase2.sh` pattern. |
| **Bootstrap** | `scripts/razorpay-probes/_bootstrap.php` | Loads Laravel app, guards against `rzp_live_*` keys + non-`testing` env, exposes `$state` map for cross-probe handoff |
| **Probes** (12) | `scripts/razorpay-probes/rz01-…rz12-*.php` | One PHP file per probe. JSON output to stdout. Exit 0 on pass, 1 on fail, 2 on FATAL guard failure |
| **Fixtures** | `scripts/razorpay-probes/fixtures/` | `sample-payment-id.txt`, `webhook-payment-captured.json` (canonical payload with `__ORDER_ID__`, `__PAYMENT_ID__`, `__AMOUNT__` placeholders) |
| **State files** | `scripts/razorpay-probes/state/` | `rz04-last-order-id.txt`, `rz09-last-pay-id.txt` — cross-probe handoff for IDs that the next probe needs |

### Probe inventory (12 probes)

| # | Probe | What it proves | Real HTTP? |
|---|---|---|---|
| **rz01** | `rz01-config-and-credentials.php` | `.env.testing` loaded; `RAZORPAY_KEY_ID` starts with `rzp_test_`; `RAZORPAY_KEY_SECRET` non-empty | No |
| **rz02** | `rz02-client-factory-resolves.php` | `RazorpayClientFactory::create()` returns a valid `RazorpayClient` with configured keys | No |
| **rz03** | `rz03-provider-metadata.php` | `RazorpayProviderAdapter` reports INR, priority=10, enabled when key set | No |
| **rz04** | `rz04-adapter-initialize-create-order.php` | `RazorpayAdapter::initialize($request)` → real `POST /v1/orders` → real `order_*` ID | **Yes** |
| **rz05** | `rz05-adapter-verify-fetch-order.php` | `RazorpayAdapter::verify($orderId)` fetches the just-created order; status maps to `TransactionStatus::INITIALIZED` | **Yes** |
| **rz06** | `rz06-error-translator.php` | `GatewayErrorTranslator::forInitialization()` correctly maps SDK exceptions (idempotency conflict, gateway rejected, SDK failure) | No (uses Throwable stubs) |
| **rz07** | `rz07-signature-round-trip.php` | `RazorpayVerificationAdapter::generateSignature()` → `verifySignature()` returns true; tampered payload returns false | No (local HMAC) |
| **rz08** | `rz08-webhook-payload-verify.php` | `verifyWebhook(headers, payload)` with a canonical `payment.captured` payload (built from rz04's order_id + sample pay_id) returns success with all expected fields populated | No (local HMAC + parse) |
| **rz09** | `rz09-fetch-payment-sample.php` | `RazorpayClient::fetchPayment($samplePayId)` against published Razorpay test payment ID | **Yes** |
| **rz10** | `rz10-refund-sample.php` | `RazorpayClient::refundPayment($samplePayId, [...])` issues a real test refund; `RazorpayAdapter::refund()` maps result to `TransactionStatus::REFUNDED` | **Yes** |
| **rz11** | `rz11-full-domain-flow.php` | Donation entity + PaymentService + RazorpayClient wired: create donation → initialize Razorpay order → verify webhook with real HMAC → state transition to CAPTURED | Mixed (real order + local verify) |
| **rz12** | `rz12-cleanup-report.php` | Lists leftover `order_*` / `pay_*` / `rfnd_*` IDs created during the run for manual dashboard cleanup | No |

### Data flow (rz04 as canonical example)

```
rz04-adapter-initialize-create-order.php
   ├─ _bootstrap.php: guard rzp_test_*, load .env.testing
   ├─ resolve PaymentGatewayContract from container
   │     └─ tagged pool filters by APP_ENV → RazorpayAdapter (always present)
   ├─ build PaymentRequest(amount=50000 minor, currency=INR, idempotencyKey, donorId)
   ├─ $adapter->initialize($request)
   │     ├─ RazorpayAdapter::initialize → RazorpayClient::createOrder([
   │     │     'amount' => 50000, 'currency' => 'INR', 'receipt' => '<idemp>',
   │     │     'notes' => ['donation_id' => '...', 'purpose' => '...'],
   │     │     'payment_capture' => 1
   │     │   ])
   │     ├─ RazorpayClient → new Api(rzp_test_*, secret)->order->create([...])
   │     ├─ HTTP POST https://api.razorpay.com/v1/orders
   │     │     └─ 200 OK {"id":"order_XXX","status":"created","amount":50000,...}
   │     └─ Result::success(GatewayResponseDTO(
   │           providerCode='razorpay', gatewayOrderId='order_XXX', amountMinor=50000,
   │           currency=Currency::INR, rawStatusString='created', rawResponse=[...]
   │         ))
   ├─ assert: gatewayOrderId starts with 'order_', amount === 50000, currency === 'INR'
   ├─ persist order_id → state/rz04-last-order-id.txt (for rz05, rz08, rz11)
   └─ output: {"probe":"rz04","status":"pass","gatewayOrderId":"order_XXX","durationMs":245}
```

### Bootstrap safety guard (non-negotiable)

```php
// scripts/razorpay-probes/_bootstrap.php
<?php
declare(strict_types=1);

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$keyId = (string) config('payments.providers.razorpay.key_id', '');
$env   = (string) config('app.env');

if (! str_starts_with($keyId, 'rzp_test_')) {
    fwrite(STDERR, "FATAL: refusing to run with non-test Razorpay key. "
        . "Set RAZORPAY_KEY_ID in .env.testing to a rzp_test_* value.\n");
    exit(2);
}

if ($env !== 'testing') {
    fwrite(STDERR, "FATAL: APP_ENV must be 'testing', got '{$env}'. "
        . "Run via scripts/validate-razorpay.sh which sets APP_ENV=testing.\n");
    exit(2);
}

return $app;
```

### Webhook fixture format

`scripts/razorpay-probes/fixtures/webhook-payment-captured.json`:

```json
{
  "entity": "event",
  "account_id": "acc_BWoXhV7QXocKl9",
  "event": "payment.captured",
  "contains": ["payment", "order"],
  "payload": {
    "payment": {
      "entity": {
        "id": "__PAYMENT_ID__",
        "entity": "payment",
        "amount": __AMOUNT__,
        "currency": "INR",
        "status": "captured",
        "order_id": "__ORDER_ID__",
        "method": "card",
        "description": "Test donation",
        "card_id": null,
        "bank": null,
        "wallet": null,
        "vpa": null,
        "email": "donor@example.in",
        "contact": "+919999999999",
        "notes": { "donation_id": "__DONATION_ID__" },
        "fee": 1180,
        "tax": 180,
        "error_code": null,
        "error_description": null,
        "created_at": __CREATED_AT__
      }
    },
    "order": {
      "entity": {
        "id": "__ORDER_ID__",
        "entity": "order",
        "amount": __AMOUNT__,
        "amount_paid": __AMOUNT__,
        "amount_due": 0,
        "currency": "INR",
        "receipt": "__RECEIPT__",
        "status": "paid",
        "attempts": 1,
        "notes": { "donation_id": "__DONATION_ID__" },
        "created_at": __CREATED_AT__
      }
    }
  },
  "created_at": __CREATED_AT__
}
```

The probe loads this file, replaces placeholders with real values from previous probes, computes the HMAC using the configured `RAZORPAY_WEBHOOK_SECRET`, then calls `verifyWebhook(['X-Razorpay-Signature' => $hmac], $json)`.

### Captured-payment dependency (rz09, rz10, rz11)

The `pay_*` ID used in rz09/rz10/rz11 comes from `scripts/razorpay-probes/fixtures/sample-payment-id.txt` — a stable Razorpay-published test payment ID. If Razorpay retires this ID, update the file. The probe documents this in its preamble comment.

```bash
# scripts/razorpay-probes/fixtures/sample-payment-id.txt
# Razorpay test-mode sample payment ID. If 404s, fetch a fresh one
# from https://dashboard.razorpay.com/app/payments (Test Mode) and replace.
pay_EXAMPLEDONOTUSE
```

### State handoff (cross-probe)

Probes that produce IDs needed by later probes write them to `state/`:

```
rz04 writes state/rz04-last-order-id.txt  →  read by rz05, rz08, rz11
rz09 writes state/rz09-last-pay-id.txt    →  read by rz10, rz11
```

A probe that depends on missing state fails fast with a clear message:

```
rz11-full-domain-flow.php
   FATAL: state/rz04-last-order-id.txt missing — run rz04 first
```

### Output format

Every probe emits exactly one JSON object to stdout, then exits:

```json
{
  "probe": "rz04",
  "status": "pass",
  "durationMs": 245,
  "checks": [
    { "name": "gatewayOrderId starts with 'order_'", "passed": true },
    { "name": "amount === 50000", "passed": true },
    { "name": "currency === INR", "passed": true }
  ],
  "evidence": {
    "gatewayOrderId": "order_XXX",
    "amountMinor": 50000,
    "currency": "INR",
    "providerCode": "razorpay",
    "rawResponse": { "id": "order_XXX", "status": "created", "amount": 50000, ... }
  }
}
```

On failure:

```json
{
  "probe": "rz04",
  "status": "fail",
  "durationMs": 245,
  "error": "amount mismatch: expected 50000, got 4900",
  "evidence": { ... }
}
```

Exit codes:
- `0` — pass
- `1` — fail (assertion or runtime error)
- `2` — FATAL guard failure (wrong key prefix, wrong env)

### Orchestrator (`scripts/validate-razorpay.sh`)

```bash
#!/usr/bin/env bash
set -euo pipefail

CONTAINER="${RAZORPAY_TEST_CONTAINER:-temple-trust-app-1}"
PROBES_DIR="scripts/razorpay-probes"

declare -a PROBES=(
  "rz01-config-and-credentials.php"
  "rz02-client-factory-resolves.php"
  "rz03-provider-metadata.php"
  "rz04-adapter-initialize-create-order.php"
  "rz05-adapter-verify-fetch-order.php"
  "rz06-error-translator.php"
  "rz07-signature-round-trip.php"
  "rz08-webhook-payload-verify.php"
  "rz09-fetch-payment-sample.php"
  "rz10-refund-sample.php"
  "rz11-full-domain-flow.php"
  "rz12-cleanup-report.php"
)

echo "=== Razorpay SDK Test Suite ==="
echo "Container: $CONTAINER"
echo ""

PASS=0; FAIL=0; FATAL=0
for probe in "${PROBES[@]}"; do
  start=$(date +%s%N)
  set +e
  output=$(docker exec -e APP_ENV=testing "$CONTAINER" \
    php "$PROBES_DIR/$probe" 2>&1)
  rc=$?
  set -e
  end=$(date +%s%N)
  duration_ms=$(( (end - start) / 1000000 ))

  if [ $rc -eq 0 ]; then
    echo "[PASS] $probe (${duration_ms}ms)"
    PASS=$((PASS+1))
  elif [ $rc -eq 2 ]; then
    echo "[FATAL] $probe (${duration_ms}ms) — $output"
    FATAL=$((FATAL+1))
  else
    echo "[FAIL] $probe (${duration_ms}ms) — $output"
    FAIL=$((FAIL+1))
  fi
done

echo ""
echo "=== Summary: pass=$PASS fail=$FAIL fatal=$FATAL ==="
[ $FAIL -eq 0 ] && [ $FATAL -eq 0 ]
```

## Configuration

### `.env.testing` (gitignored — never committed)

```bash
APP_NAME="Temple Trust"
APP_ENV=testing
APP_KEY=<generated>
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=neon
DATABASE_URL=<neon-test-branch-url>

CACHE_STORE=array
QUEUE_CONNECTION=sync

# Razorpay TEST MODE (rzp_test_* keys — must NEVER be rzp_live_*)
RAZORPAY_KEY_ID=rzp_test_XXXXXXXXXXXX
RAZORPAY_KEY_SECRET=your_test_key_secret
RAZORPAY_WEBHOOK_SECRET=your_test_webhook_secret
```

The actual test credentials are configured by the user — they live only in `.env.testing`.

### `composer.json` — no changes

The `razorpay/razorpay: ~2.0` constraint is already declared. `Razorpay\Api\Api` is already autoloaded.

## Error handling

| Failure | Probe behaviour |
|---|---|
| `rzp_test_*` key missing | FATAL: refuse to run, exit 2 (from `_bootstrap.php` guard) |
| `APP_ENV != testing` | FATAL: refuse to run, exit 2 (from `_bootstrap.php` guard) |
| Network error to `api.razorpay.com` (timeout, DNS, refused) | Probe captures SDK exception; outputs JSON `status=fail` with `error`; exit 1 |
| Razorpay returns 4xx/5xx | Probe captures `Razorpay\Api\Errors\Error`; asserts `GatewayErrorTranslator` mapping; exit 1 |
| `RazorpayClient::verifyWebhookSignature` mismatch (rz07) | Probe FAILS — signature logic is broken, exit 1 |
| State file missing (rz05 needs rz04's order_id) | Probe FATAL with clear message: "state/rz04-last-order-id.txt missing — run rz04 first"; exit 2 |
| Sample payment ID 404s (rz09, rz10) | Probe FAIL with message: "Sample payment ID retired — update fixtures/sample-payment-id.txt" |

## Critical files (existing)

### Must read before implementing

- `app/Payments/Infrastructure/Adapters/Razorpay/RazorpayClient.php` — the ONLY place `new Razorpay\Api\Api(...)` appears. All probes invoke methods on this class.
- `app/Payments/Infrastructure/Adapters/Razorpay/RazorpayAdapter.php` — `PaymentGatewayContract` impl. Probes assert payload shape matches `/v1/orders` schema.
- `app/Payments/Infrastructure/Adapters/Razorpay/RazorpayVerificationAdapter.php` — `PaymentVerificationContract` impl. Webhook verification probes use this directly.
- `app/Payments/Infrastructure/Adapters/Common/GatewayErrorTranslator.php` — error mapping. rz06 probes this.
- `app/Payments/Contracts/PaymentGatewayContract.php` — interface that probes assert against.
- `app/Payments/Contracts/PaymentVerificationContract.php` — interface that webhook probes assert against.
- `config/payments.php` — config keys `payments.providers.razorpay.*` (the canonical source; `services.razorpay.*` is dead config).
- `scripts/phase-2-*.php` — existing probe pattern (JSON output, exit codes, run individually).
- `scripts/validate-phase2.sh` — existing orchestrator pattern to mirror.
- `composer.json` — `razorpay/razorpay: ~2.0` already declared.
- `vendor/razorpay/razorpay/src/Api.php` — SDK source: `new Api($key, $secret, $oauthToken=null)`; `protected static $baseUrl = 'https://api.razorpay.com'`.
- `vendor/razorpay/razorpay/src/Utility.php` — SDK source for signature verification format: `hash_hmac('sha256', $payload, $secret)`.

### Must create (16 files)

| File | Lines (est.) | Purpose |
|---|---|---|
| `scripts/validate-razorpay.sh` | ~70 | Orchestrator (mirrors `validate-phase2.sh`) |
| `scripts/razorpay-probes/_bootstrap.php` | ~30 | Shared bootstrap + safety guard |
| `scripts/razorpay-probes/rz01-config-and-credentials.php` | ~50 | Probe 01 |
| `scripts/razorpay-probes/rz02-client-factory-resolves.php` | ~50 | Probe 02 |
| `scripts/razorpay-probes/rz03-provider-metadata.php` | ~50 | Probe 03 |
| `scripts/razorpay-probes/rz04-adapter-initialize-create-order.php` | ~80 | Probe 04 |
| `scripts/razorpay-probes/rz05-adapter-verify-fetch-order.php` | ~70 | Probe 05 |
| `scripts/razorpay-probes/rz06-error-translator.php` | ~80 | Probe 06 |
| `scripts/razorpay-probes/rz07-signature-round-trip.php` | ~60 | Probe 07 |
| `scripts/razorpay-probes/rz08-webhook-payload-verify.php` | ~90 | Probe 08 |
| `scripts/razorpay-probes/rz09-fetch-payment-sample.php` | ~70 | Probe 09 |
| `scripts/razorpay-probes/rz10-refund-sample.php` | ~80 | Probe 10 |
| `scripts/razorpay-probes/rz11-full-domain-flow.php` | ~120 | Probe 11 |
| `scripts/razorpay-probes/rz12-cleanup-report.php` | ~60 | Probe 12 |
| `scripts/razorpay-probes/fixtures/webhook-payment-captured.json` | ~50 | Canonical payload template |
| `scripts/razorpay-probes/fixtures/sample-payment-id.txt` | ~5 | Stable Razorpay test payment ID |
| `.env.testing.example` | ~25 | Template (committed; user fills in actual keys) |
| `.gitignore` | +3 lines | Add `.env.testing` |

**Total: ~1130 lines net (17 new files + 1 gitignore edit).**

File count breakdown:
- 1 orchestrator shell script
- 1 shared bootstrap PHP file
- 12 probe PHP files
- 2 fixture files (JSON + txt)
- 1 `.env.testing.example` template
- 1 `.gitignore` edit (add `.env.testing`)

## Findings (research surfaced)

These are real findings from research; they get noted in the spec but **are not fixed here** unless directly blocking:

1. **Live keys checked in** (`rzp_live_*` in `.env`) — addressed by the safety guard in `_bootstrap.php`. The probes cannot run against live gateway.
2. **`routes/webhook.php` middleware gap** — documents `webhook-dedupe` middleware in the docblock but the `Route::post('/razorpay', ...)` declaration doesn't actually apply it. Not blocking for this spec (we don't hit HTTP transport). **Separate fix.**
3. **`services.razorpay.*` is dead config** — production code reads only `payments.providers.razorpay.*`. **Separate cleanup.**
4. **`RazorpayClient::verifyWebhookSignature()` does local HMAC instead of delegating to `Razorpay\Api\Utility::verifyWebhookSignature()`** — both implementations produce the same result; consolidation is a future cleanup.
5. **`RazorpayClientFactory` and `RazorpayProviderAdapter` have no dedicated unit tests** — indirectly covered by `EnvBindingMatrixTest`. **Separate test coverage task.**

## Verification (after implementation)

End-to-end verification after the 17 files are created:

1. **Static checks**
   - `composer dump-autoload` — clean
   - `vendor/bin/pint --test scripts/razorpay-probes/ scripts/validate-razorpay.sh` — clean
   - `vendor/bin/phpstan analyse scripts/razorpay-probes/` — clean

2. **Individual probe runs**
   ```bash
   docker exec -e APP_ENV=testing temple-trust-app-1 \
     php scripts/razorpay-probes/rz01-config-and-credentials.php
   # Expected: {"probe":"rz01","status":"pass",...}
   ```

3. **Full suite via orchestrator**
   ```bash
   ./scripts/validate-razorpay.sh
   # Expected: === Summary: pass=12 fail=0 fatal=0 ===
   ```

4. **Safety guard smoke**
   ```bash
   docker exec -e APP_ENV=production temple-trust-app-1 \
     php scripts/razorpay-probes/rz01-config-and-credentials.php
   # Expected: FATAL: APP_ENV must be 'testing' — exit 2
   ```

5. **Razorpay dashboard verification** (manual)
   - Log into https://dashboard.razorpay.com → Test Mode → Orders
   - Confirm orders created by rz04 and rz11 appear with `receipt=<idempotency_key>`

6. **Doctrine compliance spot-check**
   - `grep -rn 'throw new' scripts/razorpay-probes/` → only in catch blocks
   - `grep -rn 'new Api(' scripts/razorpay-probes/` → 0 hits (probes use `RazorpayClient`, never `new Api` directly)
   - `grep -rn 'rzp_live' scripts/razorpay-probes/ .env.testing.example` → 0 hits

## Risks & Mitigations

| Risk | Mitigation |
|---|---|
| `.env` has live keys checked in — accidentally running probes with `.env` instead of `.env.testing` could create real orders | `_bootstrap.php` guard: refuses to run unless key starts with `rzp_test_` AND `APP_ENV=testing`. Exit 2 on violation. |
| Razorpay test-mode API rate limits | Probes are sequential and slow (one at a time); 12 probes ≪ typical rate limit. If a probe gets rate-limited, it fails clearly and the user retries. |
| Sample payment ID retired by Razorpay | rz09 fails with a clear message pointing to the fixture file; user updates `fixtures/sample-payment-id.txt`. |
| Real test orders accumulate on Razorpay dashboard | Acceptable for sandbox. rz12 prints a summary of created IDs for manual cleanup via dashboard. |
| Test data contaminates Neon DB | `.env.testing` can point to a separate Neon branch (`ep-noisy-mountain-test`) — recommended. Documented in `.env.testing.example`. |
| Network failure mid-suite | Orchestrator continues to next probe (doesn't `set -e`); rz12 reports what was completed. |
| `RAZORPAY_WEBHOOK_SECRET` mismatch (signature probe fails) | rz07 fails clearly: "expected HMAC vs actual HMAC mismatch — check RAZORPAY_WEBHOOK_SECRET in .env.testing" |
| Probe assumes container name `temple-trust-app-1` | Orchestrator respects `$RAZORPAY_TEST_CONTAINER` env var for override. |

## Open Questions

None at spec time. All major decisions resolved from the brainstorming Q&A.

## What This Spec Does NOT Cover

1. **HTTP transport for Razorpay webhooks** (Wide scope). Out of scope.
2. **PayPal probes** (separate task — same pattern).
3. **Auto-cleanup of test data** on Razorpay dashboard. Manual via dashboard.
4. **Fixing `routes/webhook.php` middleware gap**. Separate fix.
5. **Removing dead `services.razorpay.*` config**. Separate cleanup.
6. **Consolidating `RazorpayClient::verifyWebhookSignature()` with SDK `Utility`**. Future consolidation.
7. **Adding unit tests for `RazorpayClientFactory` and `RazorpayProviderAdapter`**. Separate test coverage task.
8. **CI integration** (GitHub Actions running the probe suite on PRs). Future ops work.
9. **Distributed tracing** of probe timings. Future ops work.
10. **Per-provider kill-switch** for the probes (`RAZORPAY_PROBES_ENABLED=false`). YAGNI.
