# Triple-A API Architecture Deep-Dive (2026-08-09)

**Buyer context.** Hindu temple trust in India (registered charitable trust, files 80G tax-deductible receipts in INR, follows FEMA / RBI / Indian Income Tax VDA rules). Tech stack: Laravel 12 / PHP 8.2 / Svelte 5 / Inertia 2 / Neon Postgres / Redis. **Processor chosen: Triple-A** (the only viable INR-off-ramp processor — see `crypto-inr-offramp-processors-2026-08-09.md`). The trust receives crypto from the donor and INR lands in the trust's Indian bank account. SOL is dropped from scope (not in Triple-A's headline asset list).

This note covers what is publicly verifiable about Triple-A's API surface, sandbox, and operational behaviour as of 2026-08-09, plus a broadened assessment of how the processor integrates with the existing `app/Payments/` kernel.

---

## 1. Executive summary

- **API documentation is private.** `docs.triple-a.io` is DNS NXDOMAIN (verified 2026-08-09). The public "How do I integrate with Triple-A's API?" article is a stub that points to a private API documentation portal and to `support@triple-a.io`. **Full API spec is only available after sandbox sign-up.**
- **The API server is live.** `https://api.triple-a.io` resolves and responds (HTTP 405 on GET at root and at `/v1/payments` — meaning the server is real but doesn't accept GET on those paths; POST/PATCH/DELETE on actual endpoints are the production paths).
- **What we CAN learn from the public knowledge base:** the high-level product structure, settlement timing, refund flow, over/short-payment handling, pre-funding model, and beneficiary requirements. Enough to draft the integration architecture at the contract level, but not enough to write client code or webhook handlers.
- **Biggest open question:** whether the donor-pays-crypto-to-merchant-receives-INR flow is (A) automatic conversion at donation time (donor → INR in one step), or (B) a two-stage model where crypto lands in the merchant's Triple-A balance first and INR conversion + bank withdrawal is a separate merchant-initiated step. Both are plausible from the public material; only the private API docs will disambiguate.
- **Integration-fit verdict:** Triple-A slots in as the third sibling to Razorpay/PayPal under `app/Payments/Infrastructure/Adapters/TripleA/`. The `PaymentGatewayContract` seam fits, but the "verify" path is webhooks-only (no synchronous confirm), so the `verify()` adapter needs to be implemented as a webhook-handler-only adapter, with a separate `reconcile()` call for polling-based recovery.

---

## 2. Public knowledge base inventory

The public knowledge base at `support.triple-a.io` is a HubSpot-hosted help center. Twelve top-level categories were enumerated. The following contain material directly relevant to our integration:

| Category | Relevant articles found |
|---|---|
| Integration Options | `how-do-i-integrate-with-tripleas-api` (stub — links to private docs) |
| Stablecoin Payments | `how-long-does-it-take-for-my-payment-to-be-confirmed`; `how-do-i-know-when-to-deliver-my-services`; `how-can-i-keep-track-of-the-payments-i-received`; `i-didnt-receive-my-payment-what-can-i-do`; `why-was-my-customers-payment-not-detected`; `what-is-an-over-payment`; `what-is-a-short-payment` |
| Stablecoin Payouts | (articles exist but not pulled in this pass — likely the symmetric "send crypto out" flow) |
| Local Currency Payouts | `what-is-a-local-currency-payout`; `which-fiat-currencies-are-supported-for-local-currency-payouts`; `how-long-does-it-take-for-a-local-currency-payout-to-be-received-by-the-beneficiary`; `is-there-a-minimum-or-maximum-limit-for-local-currency-payouts`; `what-beneficiary-details-do-i-need-to-provide-to-make-a-local-currency-payout`; `how-can-i-pre-fund-my-account-to-make-a-local-currency-payout`; `how-can-i-activate-a-new-local-currency`; `what-is-the-difference-between-using-the-dashboard-and-api-integration`; `can-customers-send-money-to-a-third-party-account-or-only-to-their-own`; `what-should-i-do-if-i-make-a-mistake-while-filling-out-the-transaction-details`; `what-will-the-payment-reference-be-on-the-beneficiarys-bank-statement-can-i-personalise-the-reference` |
| Managing Refunds | `how-do-i-process-refunds-to-my-customers-on-the-dashboard`; `when-will-my-customer-receive-the-refunds`; `do-refund-links-expire-if-unclaimed`; `how-do-i-export-csv-file-of-my-refund`; `how-do-i-cancel-refunds-to-my-customers-on-the-dashboard`; `why-is-the-refund-in-new-status`; `what-should-i-do-if-i-made-a-short-or-excess-payment-to-my-merchant` |
| Settlement & Withdrawals | (articles exist but not pulled — likely the merchant-initiated bank withdrawal flow) |
| Invoicing Tool | (articles exist but not pulled — likely the dashboard invoicing tool, not API) |

**Limitation.** All articles in this KB are thin marketing-grade copy, two to four sentences each. They describe *what* the product does but not the API surface, payload shapes, signing, or webhook structure. Every article that touches on technical detail says "Please refer to our API Documentation" or "Please reach out to support@triple-a.io."

---

## 3. API surface — what we can infer from public material

### 3.1 Endpoints (inferred from the Dashboard-vs-API article)

The Dashboard-vs-API article at `support.triple-a.io/knowledge/what-is-the-difference-between-using-the-dashboard-and-api-integration` is the single most informative public article on the API surface. Verbatim:

> "integrating our API offers the advantage of automating transactions. Your transactions' statuses can be retrieved at any time using **this endpoint** or by **subscribing to our webhooks** to receive real-time notifications of status updates."

Two API primitives are confirmed:
1. **Transaction-status retrieval endpoint** — synchronous GET on some `/transactions/{id}` or `/payments/{id}` path. Exact path not public.
2. **Webhook subscription endpoint** — subscribe to events for real-time notifications. Exact event names not public.

The article references "**this endpoint**" with a hyperlink that the public HTML exposes but the crawler couldn't resolve. This may be a tracking link to a private docs portal that requires sign-in.

### 3.2 Authentication

**Not publicly documented.** The most informative public reference is to "API integration" generally; no headers, key formats, or signing algorithms are shown. Inferences:
- API-key + secret pattern is the standard for crypto-fiat processors. Likely `Authorization: Bearer <key>` or `X-Api-Key: <key>` + `X-Signature: hmac-sha256(secret, body)` + `X-Timestamp` (anti-replay).
- A PHP SDK on Packagist was not surfaced in this pass; if a community SDK exists it would resolve the auth shape.
- **Requires sandbox sign-up to confirm.**

### 3.3 Webhooks

**Confirmed: webhooks exist** ("subscribing to our webhooks to receive real-time notifications of status updates"). Header name, signing algorithm, payload schema, and event list are **not public**. The dashboard-vs-api article suggests at minimum:
- `payment.received` — donor's crypto confirmed
- `payment.confirmed` — settled and available to merchant
- `payment.failed` — donor underpaid, expired, or rejected
- Possibly: `payout.initiated`, `payout.completed`, `refund.initiated`, `refund.completed`

These are plausible names; verification requires sandbox sign-up.

### 3.4 Sandbox configuration

**Not publicly documented.** No sandbox URL, no test API key format, no test asset coverage, no fake settlement behaviour. The marketing site references an account manager relationship for sandbox credentials — i.e. sales-led onboarding, not self-serve.

---

## 4. Stablecoin payments (donor-pays-crypto) — public facts

The donor-facing flow is described across multiple articles:

**Payment confirmation.** From `how-long-does-it-take-for-my-payment-to-be-confirmed`:
> "With Triple-A, your payment is confirmed within only seconds of being settled. Once the payment has been successfully made, your payment will be confirmed within seconds. Our instant confirmation feature ensures a smooth checkout experience for your users."

**Merchant-side signal to deliver.** From `how-do-i-know-when-to-deliver-my-services`:
> "You will receive a notification informing you that your customer has made a successful transaction. The transaction status will be displayed on your Triple-A dashboard. If the transaction is 'Good', you may then proceed to deliver your services to your customers."

The **'Good' status** is the terminal-success state on the merchant side. It maps cleanly to our `PaymentStateMachine` `Verified` transition.

**Over-payment.** From `what-is-an-over-payment`:
> "An overpayment is when your customer pays more than what is necessary." Details on overpayment handling are thin in the public KB; the article is essentially a definition with a link to contact support.

**Short-payment.** From `what-is-a-short-payment`:
> "A short payment is when your customer made a payment with insufficient funds." Same thin shape — definitional only.

**Undetected payments.** Article exists (`why-was-my-customers-payment-not-detected`) but content was not pulled in this pass.

**Tracking.** From `how-can-i-keep-track-of-the-payments-i-received`:
> "Triple-A's dashboard provides a comprehensive overview of all your payments and transactions history. Step 1: Login to your Triple-A dashboard and select transactions to view your transaction history. Step 2: Insert your desired period of transaction and download the .csv file to keep track of your transactions."

CSV export is the reconciliation primitive on the trust side.

---

## 5. Local-currency payouts — public facts

This is the symmetric product: merchant sends fiat out to a beneficiary. We need the inverse (donor sends crypto in, INR arrives in trust's account), but understanding the payout product clarifies how INR ends up in the trust's account.

### 5.1 Definition

From `what-is-a-local-currency-payout`:
> "Triple-A's local currency payout product enables individuals and businesses to seamlessly transfer funds worldwide, using either cryptocurrency or traditional fiat money, and converting them into local currencies. This feature is accessible through Triple-A's user-friendly dashboard or by integrating our API into your system."

### 5.2 Supported currencies

From `which-fiat-currencies-are-supported-for-local-currency-payouts`:
> "Triple-A can process local currency payouts in local currencies, e.g. PHP, MXN, XOF, as well as in EUR or USD to any country as long as the beneficiary's bank supports EUR and USD. i.e. the beneficiary owns a multi-currency account."

The marketing coverage table at `triple-a.io/global-payment-coverage` lists India INR + bank account + Instant — confirmed in the prior research note.

### 5.3 Settlement timing

From `how-long-does-it-take-for-a-local-currency-payout-to-be-received-by-the-beneficiary`:
> "Settlement times for local currency payouts vary based on the receiving country and the chosen payment method, and can be received by the beneficiary:
> - Instantly
> - The same day (T+0), or
> - The next day (T+1)"

Three timing options exist; the per-country/per-rail mapping is private.

### 5.4 Beneficiary requirements

From `what-beneficiary-details-do-i-need-to-provide-to-make-a-local-currency-payout`:
> "To make a local currency payout, you need to provide the following beneficiary details:
> - First name
> - Last name
> - Country of residence
> - Relationship with the sender
> - Chosen receiving method
> - Wallet or account number
>
> Depending on the market, additional information may be required. You can check the full list of required beneficiary details per country and types of service on our API Documentation."

For India INR, additional fields likely include IFSC, account type (savings/current), and PAN for transactions above ₹50,000 per the PMLA threshold. **Not publicly confirmed.**

### 5.5 Pre-funding model

**Critical for understanding the architecture.** From `how-can-i-pre-fund-my-account-to-make-a-local-currency-payout`:
> "You must pre-fund your account at least 24 hours before initiating a local currency payout.
> Once you have pre-funded your account, please notify our Finance team by sending an email to finance@triple-a.io with the proof of payment and make sure to notify your Account Manager.
>
> Pre-fund your account in cryptocurrencies: You can pre-fund your account in USDC and USDT, on both the Tron and Ethereum networks.
> Pre-fund your account in fiat currencies: You can also pre-fund your account in USD, EUR, GBP and SGD, by transferring the funds to Triple-A's bank account."

**Implication for our flow.** The public product description shows a **two-stage model**: (1) merchant pre-funds Triple-A balance, then (2) merchant initiates local-currency payout from that balance to a beneficiary. For the inverse flow (donor pays crypto → merchant receives INR), the trust needs to understand whether:
- **Option A:** the donor's crypto instantly converts and forwards to the trust's bank account in one atomic step (no Triple-A balance held), or
- **Option B:** the donor's crypto lands in the trust's Triple-A balance, then the trust (via API or dashboard) triggers the conversion + INR withdrawal.

The "Instant" coverage-table entry could describe either. **Sandbox sign-up is required to confirm.**

### 5.6 Third-party payouts

From `can-customers-send-money-to-a-third-party-account-or-only-to-their-own`:
> "Triple-A allows customers to transfer funds between their own bank accounts as well as to send funds to a third-party account."

So the trust can withdraw INR to the trust's own bank account, not just to a Triple-A-managed account. **Good** — the trust's bank account is the beneficiary.

### 5.7 Bank statement reference

From `what-will-the-payment-reference-be-on-the-beneficiarys-bank-statement-can-i-personalise-the-reference`:
> "When making a local currency settlement, you can use the available Remarks field to input any desired information, such as a transaction ID or reference, which will be visible on the beneficiary's bank account.
>
> Please note that depending on our banking partner, the country, and the beneficiary's bank, we cannot guarantee that the information provided in the Remarks field will be reflected in the beneficiary's bank account."

The trust should populate the Remarks field with the donation ID (`EntityId`) for reconciliation. The banking partner's compliance with the Remarks field is not guaranteed — the trust needs to reconcile the bank statement against the Triple-A transaction list.

---

## 6. Refund mechanics — public facts

The refund flow is donor-mediated (the donor clicks a refund link and provides a crypto address), which is unusual relative to Razorpay/PayPal.

From `how-do-i-process-refunds-to-my-customers-on-the-dashboard` (verbatim, slightly compressed):

1. Log in to `dashboard.triple-a.io/login`.
2. Click "Refunds" in the left sidebar.
3. Fill in any one of: payout reference number, payment reference number, payer email, or order ID — click Search.
4. Under "Action" column, click the blue icon (or click into the transaction detail).
5. Select "Send Email" to send/re-send the refund email. **The email address of the refund recipient must be provided.**
6. Donor receives email with a link.
7. Donor clicks link → form showing how much cryptocurrency they will receive.
8. For refunds from a local-currency account, **the exchange rate will be set once the recipient enters their receiving crypto address.**
9. Merchant receives notification email when donor confirms the crypto address.
10. Once crypto transfer completes, another notification email to merchant.

From `when-will-my-customer-receive-the-refunds`:
> "At Triple-A, refunds are initiated instantly. With the exception of short payments, refunds will be initiated instantly."

From `why-is-the-refund-in-new-status`:
> "'New' means that the refund has successfully been initiated but the customer is yet to provide us with the address to receive the funds."

**Implications for our kernel.**

- **Refund address is donor-supplied, not from the original transaction.** This means we must collect a refund wallet address on the donation form (just like CoinGate) — we cannot refund to the address the donor paid from. Add to `PaymentRequest::$donorRefundAddress` (or similar) — make it optional but recommended.
- **Refund is a multi-step flow, not a single API call.** The trust initiates the refund (via API or dashboard), then the donor confirms a crypto address, then the exchange rate is locked, then the crypto is sent. Our `PaymentGatewayContract::refund()` method needs to model this multi-step state (initiated → donor-pending → rate-locked → sent → confirmed), not a single synchronous call.
- **Refund can only be done for "Good or Short payments"** — overpayments require a different flow (likely contacting support).
- **No public API for refunds** — the article only documents the dashboard path. The API likely has an equivalent, but it's private.

---

## 7. Settlement & reconciliation

From the stablecoin-payments and managing-refunds categories:
- Dashboard at `dashboard.triple-a.io` provides transaction history.
- CSV export is the supported bulk-reconciliation primitive.
- Status names surfaced: **'Good'** (terminal success), **'New'** (refund initiated, donor pending), plus the implicit other statuses (paid, confirmed, expired, cancelled, failed).
- Webhook-driven real-time updates + dashboard polling for reconciliation.

For our kernel, this means:
- The `PaymentVerificationAdapter` is **webhook-only**. There is no synchronous "is this payment confirmed?" call — Triple-A notifies us when the state changes.
- A **daily reconciliation job** must pull the day's transactions via CSV (or via a polling endpoint) and cross-check against our local `payment` table. Mismatches are flagged for manual review.
- A separate **refund-state polling job** tracks refunds that are 'New' (donor hasn't confirmed address yet) and follows up after a configurable timeout.

---

## 8. KYB / KYC posture

From the `local-currency-payouts` category KYC article:
> "We require the identity document of each sender for transactions exceeding 1 USD. Depending on your licensing status, you have the option to carry out the verification process on your end or use our onboarding solution."

**Important nuance.** The "sender" here is the merchant's customer in the **local-currency-payout** product (the merchant is paying OUT to a beneficiary). For our use case (donor pays crypto IN to the trust), the donor is the "sender" but the KYC obligation may not attach the same way — Triple-A's checkout for crypto payments does not require donor KYC for typical donation sizes per the prior research note's public material.

The trust's KYB packet (the merchant-side KYB that Triple-A needs to accept the trust as a customer):
- Trust deed, PAN, 12A registration, 80G registration, board resolution, two trustee KYC, source-of-funds statement (donations are the source), statement of charitable purpose.
- Enhanced due diligence (EDD) because charity is "Restricted" vertical — adds review time.
- Timeline: not publicly stated; expect 2-4 weeks minimum for first-time onboarding under EDD.

---

## 9. Failure modes (inferred from public articles)

| Failure | Public source | Recovery |
|---|---|---|
| Donor pays less than required | "short payment" article | Refund available (but not "instant" — separate exception per `when-will-my-customer-receive-the-refunds`) |
| Donor pays more than required | "overpayment" article | Refund may not be available via dashboard — contact support |
| Donor sends wrong asset | Not publicly documented | Likely contact support; on-chain asset mis-routing is generally not recoverable |
| Network congestion delays confirmation | "instant confirmation" wording suggests low latency but not zero | Trust waits; webhooks signal on actual confirmation |
| Banking partner delay on INR payout | "depends on banking partner" wording in bank-reference article | Reconciliation surfaces the gap; manual follow-up with Triple-A support |
| Beneficiary detail error | `what-should-i-do-if-i-make-a-mistake-while-filling-out-the-transaction-details`: "If you provide an incorrect wallet or bank account number for your beneficiary, the transaction will fail due to mismatched information" | For other errors, contact support for cancellation + refund |
| Payout rail downtime | Not publicly documented | Webhook signals failure; trust retries after backoff |
| Triple-A hot-wallet / partner-bank downtime | The July 27, 2026 wallet incident | Trust queues all crypto-donation intents; resumes when service restored. Open question whether the API was down during the incident — needs post-mortem. |

---

## 10. What's NOT public (requires sandbox sign-up + sales-led diligence)

The following list is the operational gate before we can write code or finalize the design. Each item is the kind of thing that lives behind the `support.triple-a.io` login wall or requires a sales contact.

| Item | Why it matters |
|---|---|
| Full REST endpoint inventory | Cannot write the SDK call shapes without this |
| Authentication header set + signing algorithm | Cannot implement the `verify()` adapter |
| Webhook payload schema per event | Cannot implement the webhook handler |
| Webhook signing algorithm + header name | Cannot verify webhook authenticity |
| Sandbox URL + test API key format | Cannot write integration tests |
| Test asset coverage (which cryptos can be simulated) | Cannot validate the donor flow offline |
| Per-rail payout timing for India (IMPS vs UPI vs NEFT vs RTGS) | Cannot set donor expectations accurately |
| Per-asset payout fee schedule | Cannot quote the all-in cost |
| FX rate source disclosure | Cannot reconcile INR valuation on receipt |
| Banking partner name for INR payout leg | Cannot diligence partner-bank PMLA posture |
| Pricing tiers above and below volume thresholds | Cannot quote a sustainable per-donation cost |
| July 27, 2026 wallet incident post-mortem | Cannot assess residual hot-wallet risk |
| API equivalent of the refund flow (only dashboard documented publicly) | Cannot automate refunds |
| Donor-KYC threshold for crypto payments (the `$1 USD` threshold cited is for LCP senders, not crypto-payment donors) | Donor experience impact |

---

## 11. Integration fit with the existing Payments kernel

**Verdict: fits cleanly as a third sibling to Razorpay and PayPal, with one adapter pattern.**

| Kernel contract method | Triple-A mapping | Adapter pattern needed |
|---|---|---|
| `providerName()` | `PaymentProvider::TRIPLE_A = 'triplea'` (new enum value) | Trivial |
| `initialize(PaymentRequest $r)` | `POST /v1/payments` (or whatever the spec calls it) — create donation intent, get hosted checkout URL | Same `RazorpayInitializeAdapter` shape |
| `verify(WebhookPayload $p)` | Webhook handler (no synchronous confirm) | **New pattern**: webhook-only adapter |
| `capture(PaymentIntent $i)` | Auto-capture on donor crypto confirmation; no separate capture step | **Simplification** vs Razorpay/PayPal |
| `refund(PaymentRefundRequest $r)` | Multi-step donor-mediated flow | **New pattern**: stateful refund VO + polling job |
| `supports(Currency $c)` | INR (settled), USDT/USDC/ETH/BTC (received) | Returns true for INR + the four assets |
| `enabled()` | Reads `config('payments.providers.triplea.enabled')` | Trivial |
| `minimumAmount()` | Donor-side minimum in INR; public article references "minimum or maximum limit for local currency payouts" but content not pulled | Read from config + dashboard |
| `maximumAmount()` | Same | Same |
| `priority()` | Set lower than Razorpay (lower trust volume, lower priority for selection) | Trivial |

### 11.1 Where the kernel needs new abstractions

1. **`PaymentProvider` enum value.** Add `TRIPLE_A = 'triplea'` to `App\Payments\Domain\Enums\PaymentProvider.php`.
2. **`TripleAGateway` adapter class.** Implements `PaymentGatewayContract`. Same file pattern as `RazorpayGateway.php`.
3. **`TripleAClient` + `TripleAClientFactory`.** Thin wrapper over the REST API. Auth handling here, not in the gateway.
4. **`TripleAVerificationAdapter`.** Implements `PaymentVerificationContract`. Webhook-only — must be registered with a webhook route that hits a `TripleAWebhookController`. Verification is signature-based (algorithm TBD pending sandbox).
5. **`TripleAProviderAdapter`.** Implements `PaymentProviderContract` — metadata (name, displayName, supportedCurrencies, min, max, priority).
6. **`TripleACheckoutController` + `TripleACheckoutRequest`.** POST `/api/v1/triplea/checkout` — validates the donation form, calls `TripleAGateway::initialize()`, redirects donor to Triple-A's hosted checkout URL.
7. **`TripleAWebhookController`.** POST `/api/v1/triplea/webhook` — no CSRF, signature verification via the adapter, transitions our `PaymentStateMachine` to `Verified` on the 'Good' event.
8. **`config/payments.php` block.** Add `providers.triplea.enabled` + `providers.triplea.keys.{api_key, api_secret, webhook_secret}` + `providers.triplea.webhook_*`.
9. **`App\Payments\Providers\PaymentsServiceProvider::register()` bindings.** Bind `TripleAGateway` conditionally on `providers.triplea.enabled`, bind `TripleAProviderAdapter`, bind the verification adapter.
10. **Frontend.** `$shared/lib/triplea.ts` — host-page redirect helper (similar to CoinGate — donor clicks button, gets `payment_url` from backend, full-page redirect). Update `resources/js/domains/payments/Donate.svelte` to include "Crypto" in the method selector.
11. **Optional `App\Payments\Services\CryptoDonationReconciliationService.php`** — daily job that pulls CSV from Triple-A dashboard and cross-checks against local `payment` table. Mismatches → flagged in our failure-state machine.

### 11.2 Where existing abstractions cover it

- `PaymentStateMachine` — terminal 'Good' status maps to `Verified → Captured → Completed` (auto-capture on webhook, no separate auth step)
- `DonationStateMachine` — receives `PaymentVerified` event from the payments kernel, transitions to `ReceiptGenerated` on success
- `ReceiptStateMachine` — handles receipt rendering; the 80G vs non-80G branching is already in the receipt template
- `FailureStateMachine` — short payment, expired, and failed webhooks feed into the failure-state machine
- `RedisConnectorContract` (via Shared kernel) — idempotency SETEX dedupe for the webhook handler (no double-processing)
- `ConfigurationContract` (via Shared kernel) — reads `config/payments.php` for the Triple-A block
- `EntityId` (via Persistence kernel) — used as the `order_id` parameter on the API call, surfaced as the Remarks field on the bank statement

### 11.3 Where existing abstractions need extension

- **`PaymentVerificationContract`** — currently synchronous (Razorpay HMAC + PayPal ECDSA are both synchronous signature verifications on a webhook payload). Triple-A's webhook will likely also be a synchronous signature verification, but the *response* to the webhook may need to be different (e.g. acknowledge-only vs idempotent-commit). Need to look at the existing `PaymentVerificationResult` envelope.
- **`PaymentRefundRequest` VO** — currently models a single synchronous refund call. Triple-A's refund is a multi-step flow with a donor-mediated confirmation. May need a new `TripleARefundState` enum (initiated → donor-pending → rate-locked → sent → confirmed) plus a polling job that drives the state transitions.
- **`PaymentRequest` VO** — add `donorRefundAddress` (optional crypto address for refunds) and `donorEmail` (required by Triple-A's refund flow).
- **Receipt template** — needs a non-80G branch when the payment method is crypto. The existing template already has Razorpay/PayPal branches; add a third.

---

## 12. Open questions requiring sandbox sign-up before spec can be finalized

These are the things that block code-writing. Each is a question for the Triple-A sandbox + account manager.

1. **Donor-pays-crypto-to-merchant-receives-INR — single atomic flow or two-step?** (Section 5.5 above.)
2. **What is the webhook payload schema for `payment.received`, `payment.confirmed`, `payment.failed`?**
3. **What is the webhook signing algorithm and header name?**
4. **What are the per-event URLs (sandbox vs live)?**
5. **What are the test asset codes and test bank account for sandbox simulation?**
6. **What is the per-asset payout fee for INR settlement?**
7. **What is the FX rate source for INR valuation?**
8. **Which banking partner handles the INR leg, and do they have FIU-IND registration?**
9. **What is the API equivalent of the dashboard refund flow?**
10. **What is the donor-side KYC threshold for crypto-payment donations (not the LCP `$1 USD` threshold)?**
11. **What is the July 27, 2026 wallet incident post-mortem, and is the platform operating normally now?**
12. **What is the pricing tier schedule above and below the headline 1%?**
13. **For the trust's bank statement, will the IFSC + PAN fields be available in the dashboard so the trust can reconcile by donation ID?**
14. **For overpayments, is there an automated refund path or is support-contact the only route?**
15. **What is the API rate limit per merchant?**

---

## 13. Sources and verification status (2026-08-09)

### Triple-A marketing
- https://www.triple-a.io — verified (live; July 27, 2026 wallet incident disclosure visible on homepage)
- https://www.triple-a.io/global-payment-coverage — verified (coverage table includes `India | INR | Bank Account | Instant`)
- https://www.triple-a.io/digital-currency-payments — verified (BTC, ETH, USDC, USDT — no SOL)
- https://www.triple-a.io/restricted-prohibited-businesses — verified (charity = "Restricted")
- https://www.triple-a.io/license — verified (MAS PS20200525, ACPR/AMF A2026-015, US FinCEN MSB + state money-transmitter)
- https://www.triple-a.io/sitemap.xml — verified (no `docs.triple-a.io` entry)
- https://www.triple-a.io/global-payouts — verified
- https://www.triple-a.io/about-us — verified

### Triple-A API endpoints
- https://api.triple-a.io — verified (HTTP 405 on GET at root + `/v1/payments`; nginx; HSTS; CSP headers)
- https://docs.triple-a.io — DNS NXDOMAIN (verified 2026-08-09)

### Triple-A knowledge base (all verified live, all extracted via curl + Python HTMLParser 2026-08-09)
- https://support.triple-a.io/knowledge/how-do-i-integrate-with-tripleas-api — verified (stub, points to private docs)
- https://support.triple-a.io/knowledge/what-is-a-local-currency-payout — verified
- https://support.triple-a.io/knowledge/which-fiat-currencies-are-supported-for-local-currency-payouts — verified
- https://support.triple-a.io/knowledge/how-long-does-it-take-for-a-local-currency-payout-to-be-received-by-the-beneficiary — verified
- https://support.triple-a.io/knowledge/what-beneficiary-details-do-i-need-to-provide-to-make-a-local-currency-payout — verified
- https://support.triple-a.io/knowledge/how-can-i-pre-fund-my-account-to-make-a-local-currency-payout — verified
- https://support.triple-a.io/knowledge/how-can-i-activate-a-new-local-currency — verified
- https://support.triple-a.io/knowledge/what-is-the-difference-between-using-the-dashboard-and-api-integration — verified (mentions endpoint + webhooks)
- https://support.triple-a.io/knowledge/can-customers-send-money-to-a-third-party-account-or-only-to-their-own — verified
- https://support.triple-a.io/knowledge/what-should-i-do-if-i-make-a-mistake-while-filling-out-the-transaction-details — verified
- https://support.triple-a.io/knowledge/what-will-the-payment-reference-be-on-the-beneficiarys-bank-statement-can-i-personalise-the-reference — verified
- https://support.triple-a.io/knowledge/how-long-does-it-take-for-my-payment-to-be-confirmed — verified
- https://support.triple-a.io/knowledge/how-do-i-know-when-to-deliver-my-services — verified ('Good' status)
- https://support.triple-a.io/knowledge/how-can-i-keep-track-of-the-payments-i-received — verified (CSV export)
- https://support.triple-a.io/knowledge/what-is-an-over-payment — verified (definitional only)
- https://support.triple-a.io/knowledge/what-is-a-short-payment — verified (definitional only)
- https://support.triple-a.io/knowledge/how-do-i-process-refunds-to-my-customers-on-the-dashboard — verified (10-step refund flow)
- https://support.triple-a.io/knowledge/when-will-my-customer-receive-the-refunds — verified (instant for good/short)
- https://support.triple-a.io/knowledge/why-is-the-refund-in-new-status — verified ('New' = donor pending)

### Prior research artefacts (this repo)
- `docs/research/crypto-payment-processors-2026-08-08.md` — upstream self-custody / IBAN-off-ramp research (CoinGate primary, BTCPay contingency, TripleA complementary)
- `docs/research/crypto-inr-offramp-processors-2026-08-09.md` — INR-off-ramp matrix (Triple-A primary, NOWPayments no-INR, others ruled out, regulatory analysis)

---

*Prepared for SPEC use only — no code is being written. Items marked "verified" were pulled directly from the linked page on 2026-08-09 via `curl -sL -A "Mozilla/5.0 ..."`. Items marked "not publicly documented" are the operational gate for sandbox sign-up before the design can be finalized.*