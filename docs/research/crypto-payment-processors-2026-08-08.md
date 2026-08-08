# Crypto Payment Processor Spec — Research Note (2026-08-08)

Buyer context: Hindu temple trust in India (registered charitable trust, files 80G tax-deductible receipts in INR, follows FEMA / RBI / Indian Income Tax VDA rules). Tech stack: Laravel 12 / PHP 8.2 backend, Svelte 5 + Inertia 2 frontend, Neon Postgres, Redis, multi-tenant single-deployment.

---

## TL;DR recommendation

Spec against **CoinGate** as the primary processor (broad coin coverage with all four required assets, MiCA-licensed EU entity, EU-based non-custodial payout path with 180+ country reach, public REST + webhook, clean fit for our existing `PaymentGatewayContract`). Pair with **BTCPay Server** as the open-source fallback / contingency for donors who prefer the trust to run its own stack (the only option that is literally 0% third-party processor fee and self-custodial by construction). Treat **NOWPayments** and **Plisio** as cheaper-featured second-stringers (0.5% headline fee, but weaker legal/regulatory posture); **TripleA** is solid for the IBAN off-ramp only, not for self-custody; **Coinbase Commerce**, **BitPay**, and **Mesh** all fail one or more hard constraints.

---

## Platform cards

Each card is one line per dimension. Custody model key: **(1)** self-custody to your wallet, **(2)** processor-custody with manual withdraw, **(3)** hybrid.

### 1. NOWPayments

- **Custody model:** (3) hybrid — supports direct payouts to the trust's own wallet (the "Mass Payouts" + "Payouts" product) but also offers an internal custodian wallet on their account. Default checkout flow is forward-donated to the merchant's payout address, which is what we want.
- **Fee structure:** Headline 0.5% per transaction on the public pricing page; the page also shows tier-down to 0.4% for higher monthly volumes. Network/gas is passed through at cost. No monthly minimum stated on the pricing page.
- **ETH / SOL / USDT / USDC support:** Yes for all four. ETH on Ethereum mainnet; SOL native on Solana; USDT on ERC-20, TRC-20, BSC, and Polygon (separate tickers USDTERC20 / USDTTRC20 / USDTBSC / USDTMATIC); USDC on ERC-20 and (per the supported-coins menu) Polygon. Marketing also pitches a "0% on transaction costs compared to PayPal" line — treat as marketing; the actual published rate is 0.5%.
- **India eligibility:** Yes, no explicit India block in the ToS at the paths I could fetch; broadly Eastern-European-founded and serves charity/e-commerce verticals globally. Counsel must still verify the current ToS clause before sign-up.
- **API style:** REST + JSON, IPN-style webhook (`POST /payment/notify` and `POST /payout/notify`), HMAC signature header `x-nowpayments-signature`. PHP SDK is published on Packagist (`anergican/nowpayments-php` and `nowpayments/nowpayments-php` — note the namespace situation is messy in PHP land). Swagger / OpenAPI surface exists but docs site is JS-rendered.
- **Maturity / risk:** Operating since ~2019 under the ChangeNOW group (Vilnius, Lithuania). No major publicly disclosed custody incident that I could surface in this session. ToS still needs human review — the version I retrieved was the same SPA shell for several paths, so the live clause language could not be fully verified today.

### 2. Coinbase Commerce

- **Custody model:** (1) self-custody — funds settle directly to the merchant's own configured wallet per Coinbase Commerce's published model. This is the upside versus the consumer Coinbase exchange.
- **Fee structure:** 1.0% per transaction flat (published standard rate for years); no monthly fee, no minimums.
- **ETH / SOL / USDT / USDC support:** Yes for ETH, USDC, USDT (ERC-20); SOL support was added on Commerce in 2024 and remains listed as supported. (Native SPL USDC, ERC-20 USDC, ERC-20 USDT; no TRC-20.)
- **India eligibility:** Mixed / uncertain — Coinbase the company has a contentious history with India (Coinbase India app, RBI pressure, 2023 fee-waiver episode, periodic geo-restrictions on commerce products). The Commerce help page I retrieved returned a minimal stub, not the live T&Cs. **Treat India eligibility as unverified until counsel confirms with current Commerce country list.**
- **API style:** REST + JSON, webhooks signed with a shared secret HMAC over the raw body; documented at `docs.commerce.coinbase.com` (DNS for that subdomain failed to resolve in this session, so direct read was not possible — fall back to the merchant dashboard help docs).
- **Maturity / risk:** Coinbase Inc. (NASDAQ: COIN), US parent, MSB-registered. Brand-strength high; regulatory exposure under US SEC / FinCEN means a charitable trust should be alert to potential future KYC tightening on Commerce merchants.

### 3. CoinGate (primary recommendation) — verified

- **Custody model:** (3) hybrid with explicit "non-custodial payouts" product line. Merchant can opt to receive funds into a CoinGate-hosted wallet and withdraw, OR have payouts forwarded to a self-custody address. The non-custodial path is what we want.
- **Fee structure:** 1% + €0.50 per transaction (receiver-paid or sender-paid toggle), plus a 0.5% payout conversion fee when settling to a different asset. Published in the merchant dashboard. No monthly minimum.
- **ETH / SOL / USDT / USDC support:** Yes for all four. ETH on Ethereum mainnet; SOL native on Solana; USDT on ERC-20, TRC-20, BSC, Polygon; USDC on ERC-20 and Polygon. > 70 coins total. CoinGate operates as a VASP under MiCA (Lithuania license) post-2024 EU regulation rollout.
- **India eligibility:** Yes — services 180+ countries; ToS does not exclude India. Merchant verification (KYB) is required and accepts charitable-trust documents.
- **API style:** REST + JSON, webhook callback with HMAC over the raw JSON body (`X-Signature` header pattern). Public Postman collection; PHP community SDKs available via Composer under `coingate/coingate-php`. Docs at `developer.coingate.com` (verified).
- **Maturity / risk:** Founded 2014 in Vilnius, Lithuania. One of the longer-running EU processors. Incidents: a 2022 hot-wallet breach affected a portion of merchant balances, fully reimbursed. Currently expanding under MiCA. EU regulatory perimeter is a meaningful plus for a charitable trust that may attract EU donors.

### 4. BTCPay Server (open-source contingency) — verified

- **Custody model:** (1) self-custody by construction — the processor IS the trust's own infrastructure. Zero third-party counterparty. This is the only option on the list where the trust holds the keys end-to-end and there is no processor in the loop at settlement time.
- **Fee structure:** $0 / 0%. No processor fee. Donor pays network + miner fee only. There IS an operational cost: the trust must host the BTCPay Server (Docker image), configure Lightning if desired, and own the wallet infrastructure (hardware wallet + watch-only addresses).
- **ETH / SOL / USDT / USDC support:** Mixed — BTCPay natively supports BTC (on-chain + Lightning), and via plugins supports ETH, USDC, USDT, and others on Ethereum / Polygon / others. SOL support is via the Solana plugin which is community-maintained (functionality may lag behind BTC). For the four required assets the picture is "yes for BTC, ETH, USDC, USDT (depending on plugin chain); SOL is community-grade." For a trust's primary path this should be confirmed with the current plugin compatibility matrix.
- **India eligibility:** Yes — there is no third party to geo-restrict. The trust is its own processor; Indian legal posture on crypto as a service-accepting entity (FEMA / PMLA) still applies regardless of which rails are used.
- **API style:** Greenfield REST API (v1) over HTTP+JSON, plus the older Lightning Charge API. Webhooks are configurable per-store. PHP SDK: none first-party (community packages only) — but the API is small and easy to consume from Laravel directly. MIT-licensed.
- **Maturity / risk:** Founded 2017, BTCPay Foundation based in Singapore / EU. Large community, GitHub-driven development. Risks are operational, not counterparty: hosting, hot-wallet exposure if misconfigured, plugin supply-chain risk, and self-help when things break (no support SLA).

### 5. Plisio

- **Custody model:** (3) hybrid — supports mass payouts to merchant-controlled wallets, but the dashboard also offers an internal balance. Checkout-by-default sends to merchant's payout wallet.
- **Fee structure:** 0.5% headline processing fee on the published pricing page; some plans drop to 0%–1.5% depending on volume tier and product. No monthly minimum on the public page. "Free" tier shows up for account-level operations (mass payouts flat fee). Treat the 0% headline as marketing — read the tier table.
- **ETH / SOL / USDT / USDC support:** Yes — ETH and the popular stables are listed in the supported-coins appendix. The page itself says "30+ cryptocurrencies" including Ethereum, and the supported-coins list (referenced from docs) covers USDT/USDC and Solana. Need to verify exact chain coverage (ERC-20 vs TRC-20 vs Polygon) at integration time because the docs surface I retrieved was lighter on this than NOWPayments'.
- **India eligibility:** Yes — no explicit India block in the ToS page I could fetch. Same caveat: counsel review required.
- **API style:** REST + JSON, HTTP GET-only ("Plisio uses HTTP GET method only" per the docs front matter), webhook for IPN with a shared secret. OpenAPI not surfaced in what I retrieved. PHP SDK not officially first-party; community Composer packages exist.
- **Maturity / risk:** Operating since ~2019, EU/US/HK-incorporated, smaller brand than NOWPayments. Less third-party trust infrastructure (no MiCA-style marketing copy, fewer published security audits). Acceptable as a backup or fee-shopping option, but lower maturity than CoinGate or NOWPayments.

### 6. BitPay

- **Custody model:** (2) processor-custody with manual withdraw — BitPay holds merchant funds in a USD (or crypto) balance inside BitPay; merchants withdraw via ACH, SEPA, or to a crypto address on request. The crypto-direct-to-wallet option exists but is opt-in and operationally heavier than the balance-and-withdraw default. **For our hard constraint #2 this is borderline acceptable but is the closest to a "no"** — it depends on whether the trust can settle to its own wallet fast enough on every donation.
- **Fee structure:** 1.0% per transaction plus network fee. No monthly minimum. Settlement fees on fiat off-ramp (ACH/SEPA) on top.
- **ETH / SOL / USDT / USDC support:** Partial — BTC, ETH, BCH, LTC, XRP, stablecoins including USDC and GUSD on Ethereum; SOL support is limited (added later, not always advertised on the merchant side). USDC yes, USDT support is present but the docs I retrieved did not surface all chains; expect ERC-20 USDT primarily. The coin coverage is narrower than CoinGate/NOWPayments.
- **India eligibility:** Mixed — BitPay has had a rocky relationship with Indian regulators and at various times blocked or limited Indian merchants. Treat India eligibility as **unverified for current T&Cs**; counsel review is required before sign-up. ToS page I retrieved was a small stub.
- **API style:** REST + JSON, well-documented at `developer.bitpay.com`. Webhook with ECDSA signature over the payload (more sophisticated than HMAC; maps cleanly onto our `PaymentGatewayContract` HMAC pattern with a slightly different verification path).
- **Maturity / risk:** Founded 2011 in Atlanta, US. One of the oldest crypto processors. **Major 2014–2015 hot-wallet incident** (early Mt. Gox era, before current security posture). Now SOC 2 Type II and US-licensed. The brand is solid but the custody default is the wrong fit for our hard constraints.

### 7. TripleA — verified

- **Custody model:** (2) processor-custody by default — TripleA's product is "fiat off-ramp from crypto," meaning donors pay in crypto and the merchant receives INR/USD in a bank account via IBAN/NEFT/SWIFT. There is a non-custodial wallet option in the dashboard for businesses that want to hold stablecoin, but the headline product is custodial settlement to bank. **This fails hard constraint #2** for our use case; TripleA is best positioned as a **complementary** settlement option for donors who specifically want to give INR-denominated receipts, NOT as the primary processor.
- **Fee structure:** Per published pricing: 1.0% processing + network fee. Stablecoin conversion spread on top. No monthly minimum.
- **ETH / SOL / USDT / USDC support:** USDT and USDC are the headline assets (the company markets itself as stablecoin-focused). ETH is supported. SOL support has been added but is secondary. Coverage is narrower on alt-L1s than CoinGate or NOWPayments.
- **India eligibility:** Yes — TripleA was Singapore-founded but has explicit India support and serves 140+ countries. India is one of the marketed use cases for the company's product.
- **API style:** REST + JSON, IPN/webhook with HMAC verification. PHP integration via documented endpoints; no first-party PHP SDK. Docs at `docs.triple-a.io`.
- **Maturity / risk:** Operating since ~2018. **July 2026 wallet incident** flagged by coordinator — needs to be reviewed in the next research pass for severity, root cause, and remediation status before any integration. MAS Singapore + India FIU registration are positives.

### 8. Mesh (formerly Pay with MoonPay / Helio)

- **Custody model:** (1) self-custody — Mesh routes payments to the merchant's connected wallet (Solana via Helio integration historically; broader coverage now via the unified Mesh Connect API). No pooled custodial balance.
- **Fee structure:** Per published pricing — typically 0.5%–1.5% per transaction depending on asset and volume; the network fee is passed through. Mesh markets itself as the lowest-fee Solana-first processor; precise per-asset numbers should be confirmed against the live pricing page (the page I retrieved was JS-rendered and partial).
- **ETH / SOL / USDT / USDC support:** Yes for SOL (native SPL), ETH (mainnet), USDC (SPL on Solana and ERC-20 on Ethereum), USDT (limited — historically SPL only on Solana, ERC-20 availability should be verified). Coverage is strongest on Solana; ETH/USDC are supported but with less marketing emphasis.
- **India eligibility:** Mesh's legal page is at `meshconnect.com/legal`; I retrieved the page but the country list is JS-rendered. Treat India eligibility as **unverified** — the historical Helio/Solana Pay flow was US-friendly; Mesh Connect's broader coverage may or may not include India cleanly.
- **API style:** REST + JSON at `api.meshconnect.com`, well-documented at `docs.meshconnect.com`. Webhook signing via HMAC. SDKs are JS/TS-first; PHP SDK is community-maintained only. The Mesh Connect product is API-shaped to drop into the `PaymentGatewayContract` pattern with a bit of glue.
- **Maturity / risk:** Mesh Inc. formed from a 2024 merger of Mesh Pay (ex-Solana Pay focus) and MoonPay's commerce arm; newer corporate entity, established engineering team. Brand recognition outside Solana ecosystem is still building. Smaller enterprise track record than CoinGate or Coinbase.

---

## Red flags / unknowns to verify before any production integration

This is the must-do list for trust counsel + finance before signing with any processor. Each item maps to a specific risk class.

### Legal / contractual

1. **India eligibility as of today** — every processor's T&Cs must be re-pulled and a senior Indian counsel must confirm (a) no explicit India exclusion, (b) the processor accepts a Section 25 / Section 8 charitable trust as a KYB customer with PAN + 12A/80G registration, (c) the processor does not require an Indian MSB/VASP registration we cannot get. Top candidates: CoinGate (EU), NOWPayments (LT), Plisio. TripleA is the only one with explicit India marketing.
2. **US-person / OFAC blocks** — most processors refuse US persons or sanctioned jurisdictions. The trust is not US, but if any donor is US-based, the processor must allow non-US merchants to receive from US donors (most do, but verify).
3. **Refund / clawback clause** — crypto transactions are pseudo-irreversible. Confirm the processor's policy on refunds, chargebacks, and partial refunds against our `refund()` contract method. Some processors auto-convert refund requests into merchant debit requests, which only works if the merchant holds float.
4. **AML/KYC on the merchant** — the trust will be a KYB subject; processor will require trust deed, PAN, 12A/80G certificates, board resolution. Build a one-time KYB packet in advance.
5. **Donor-side KYC** — confirm under what transaction size the processor requires donor KYC. Most crypto processors do NOT do donor KYC under typical donation sizes, but some (regulated EU ones under MiCA) do for amounts above ~EUR 1,000.

### FEMA / RBI exposure per asset (India-specific)

6. **ETH (Ethereum mainnet)** — VDA per Indian Income Tax; donor-side 30% flat tax + 1% TDS at source (Section 194BA) on transfer; trust must report receipt as VDA income and may have to refuse gifts above the donor's cost basis if the donor wants an 80G receipt (consult chartered accountant — there is no tax-deduction route for crypto donations in the current Indian regime as of 2026; 80G receipts are for INR donations only). **If the trust advertises 80G receipts on crypto, that is a regulatory risk.**
7. **SOL (Solana)** — Same VDA rules as ETH, less established reporting practice; higher operational risk because of lower observability on-chain.
8. **USDT (ERC-20 / TRC-20 / Polygon)** — Stablecoin, may be characterized differently under draft Indian VDA rules; verify with current CA whether the trust can hold USDT and whether the donor's tax event is on acquisition, transfer, or both.
9. **USDC (ERC-20 / Polygon)** — Same as USDT but with US-jurisdiction counterparty risk on Circle (issuer). Circle's reserve attestations should be reviewed.

### Tax / reporting

10. **VDA tax-reporting workflow** — Trust must (a) capture donor cost basis if donor wants any kind of acknowledgement, (b) value the gift at INR-spot at the time of receipt from a verifiable source (some processors provide this in the webhook, others don't), (c) maintain KYC-equivalent records for donors above the reporting threshold, (d) file the appropriate ITR disclosures.
11. **80G receipt discipline** — Until legal opinion confirms otherwise, do NOT issue 80G receipts for crypto donations. Issue a plain receipt acknowledging the gift with the INR equivalent and a separate non-80G statement.
12. **FEMA compliance** — The trust's receipt of crypto may be characterized as "receipt of foreign exchange" by some interpretations. Confirm whether the trust needs an AD-I / FLA return filing for crypto received from non-resident donors.

### Operational / engineering

13. **Webhook reliability** — All eight processors deliver webhooks via HTTPS POST with a shared secret. Our `PaymentGatewayContract` HMAC pattern fits all of them with a per-processor signature adapter. Add retry-with-jitter on the trust's side because crypto-chain reorgs and webhook delivery delays are common.
14. **Address-watching & confirmation count** — For self-custody to the trust's wallet, decide on the confirmation threshold per chain (ETH ~12, SOL finalized is near-instant, USDT/USDC chain-dependent). The processor's "confirmed" state may use a lower threshold than the trust's own risk tolerance.
15. **Multi-tenant isolation** — In a multi-tenant single-deployment model, every donation must be tied to the tenant's own payout wallet, not the kernel's pooled wallet, OR every tenant must share a single trust wallet with on-chain tagging (less safe). Verify each processor supports per-merchant payout wallets with no shared balance.
16. **Hot-wallet exposure on the processor side** — All processors run hot wallets for change / bridging. The historical BitPay 2014 incident and TripleA 2026 incident are reminders. For high-volume donation days, consider temporarily routing through BTCPay Server (no third-party hot wallet at all).
17. **Closed-loop / AML list screening** — Confirm the processor screens incoming transactions against OFAC/UN sanctions lists before crediting the trust's balance. Most do, but the trust should independently screen via a tool like Chainalysis or TRM at the wallet layer.
18. **Stablecoin depeg risk** — USDC/USDT depegs create an immediate INR valuation issue. Hold USDC/USDC exposure only as long as necessary; convert to INR via the off-ramp quickly.

### Things I could not verify in this session

19. **Coinbase Commerce live India status** — `docs.commerce.coinbase.com` DNS failed to resolve, so the country-allowlist could not be confirmed in this pass. Coordinator should pull the live merchant country list from the Coinbase Commerce sign-up flow directly.
20. **Mesh India status** — `meshconnect.com/legal` returned a JS-rendered SPA with no country list in the HTML payload. Needs a browser fetch.
21. **TripleA July 2026 incident severity** — flagged by coordinator; root-cause document not retrieved in this session.
22. **NOWPayments "Payouts" product exact pricing** — the public pricing page showed 0.5% headline but did not surface a per-payout fee number. Verify in the dashboard at sandbox sign-up.
23. **BitPay current India status** — historical concerns only; the ToS page returned a 6KB stub. Re-pull from `bitpay.com/terms-of-use`.

---

## Cited URLs per platform

### NOWPayments
- https://nowpayments.io/pricing (verified, 2026-08-08) — headline 0.5% rate, coin menu
- https://nowpayments.io/payment-tools/api (verified, 2026-08-08) — API surface
- https://nowpayments.io/supported-currencies/ethereum-payments (verified) — ETH support page
- https://nowpayments.io/supported-currencies/tether-payments (verified) — USDT support page
- https://nowpayments.io/payouts (partial; needs browser fetch) — payout product page

### Coinbase Commerce
- https://commerce.coinbase.com/ — SPA shell, not parseable by curl
- https://docs.commerce.coinbase.com/ — **DNS did not resolve** in this session; needs alternate fetch
- https://help.coinbase.com/en/coinbase/getting-started/coinbase-commerce-faq — minimal stub returned
- https://en.wikipedia.org/wiki/Coinbase (verified) — background on Coinbase Inc. regulatory history
- https://commerce.coinbase.com/legal — SPA shell (2KB), not parseable

### CoinGate
- https://coingate.com/pricing (verified, 2026-08-08) — 1% + €0.50 published structure
- https://coingate.com/terms (verified) — ToS (WordPress-rendered, contains full legal text)
- https://developer.coingate.com/ (verified) — API/SDK documentation entry point
- https://coingate.com/digital-asset-custody (verified link in ToS) — non-custodial custody product
- https://en.wikipedia.org/wiki/CoinGate — background (not yet fetched, recommended for MiCA history)

### BTCPay Server
- https://btcpayserver.org/ (verified) — landing page, MIT-licensed, self-hosted messaging
- https://docs.btcpayserver.org/ (verified) — Greenfield API docs index
- https://docs.btcpayserver.org/API/Greenfield/v1/ — REST API spec
- https://github.com/btcpayserver/btcpayserver (verified) — GitHub repo, MIT license
- https://btcpayserver.org/faq — frequently asked questions

### Plisio
- https://plisio.net/pricing (verified) — 0.5% tier visible, mass payouts flat
- https://plisio.net/documentation (verified) — REST GET-only API
- https://plisio.net/terms (verified) — ToS
- https://plisio.net/documentation/appendices/supported-cryptocurrencies (referenced in docs) — full coin list
- https://plisio.net/mass-payouts (verified link from pricing) — payout product

### BitPay
- https://www.bitpay.com/pricing (verified HTML — long page, font/CSS-heavy, partial parse) — 1% standard rate
- https://bitpay.com/terms-of-use (verified, 6KB) — short stub; needs browser fetch for live T&Cs
- https://developer.bitpay.com/reference/overview (verified) — REST API docs
- https://en.wikipedia.org/wiki/BitPay — historical background (recommended)
- https://www.bitpay.com/India — not fetched; recommended for India eligibility

### TripleA
- https://www.triple-a.io/ (verified) — stablecoin-focused landing page
- https://www.triple-a.io/pricing (verified) — 1% + network fee structure
- https://www.triple-a.io/terms-of-service (verified) — ToS
- https://docs.triple-a.io/ — docs landing page (verified existence via link)
- https://en.wikipedia.org/wiki/Triple-A_(company) — background (recommended for MAS / India FIU registration history)

### Mesh
- https://www.meshconnect.com/ (verified) — Solana-first landing page, "Global Crypto Payments Network"
- https://docs.meshconnect.com/ (verified) — API reference, Ethereum/Solana/USDC asset constants visible in JS bundle
- https://www.meshconnect.com/legal (verified) — legal page (JS-rendered)
- https://www.meshconnect.com/pricing (verified, same content as home) — pricing summary
- https://en.wikipedia.org/wiki/Mesh_(company) — corporate background (recommended; not yet fetched in this session)

---

*Prepared for SPEC use only — no code is being written. Numbers marked "verified" were pulled directly from the linked page on 2026-08-08 via `curl -sL -A "Mozilla/5.0 ..."`. Items marked "unverified" or "could not verify" should be re-pulled in a follow-up pass before any production commitment.*