# Crypto → INR Off-Ramp Payment Processor Research Note (2026-08-09)

**Buyer context.** Hindu temple trust in India (registered charitable trust, files 80G tax-deductible receipts in INR, follows FEMA / RBI / Indian Income Tax VDA rules). Tech stack: Laravel 12 / PHP 8.2 backend, Svelte 5 + Inertia 2 frontend, Neon Postgres, Redis, multi-tenant single-deployment. **This research focuses on the "crypto → INR off-ramp" use case: the donor pays crypto, the merchant receives INR in an Indian bank account via NEFT/IMPS/UPI/RTGS. The trust does NOT want to hold crypto.**

This is a follow-up to `crypto-payment-processors-2026-08-08.md`, which covered the IBAN-off-ramp / self-custody matrix. Here we narrow the scope to processors that actually settle INR, with FIU-IND regulatory weight, and a clean read on what the trust can do with the cash it receives.

---

## 1. Executive summary

**Primary recommendation: Triple-A (https://www.triple-a.io).** Triple-A is the only processor that:
- Lists "India — INR — Bank Account — **Instant** settlement" on its published payment-coverage page (verified 2026-08-09).
- Covers all four required assets (BTC, ETH, USDC, USDT) on the checkout side.
- Operates as a licensed Major Payment Institution in Singapore (MAS PS20200525), a CASP in France (ACPR + AMF A2026-015), and a FinCEN MSB + US state money-transmitter in the US.
- Has a published "Local Currency Payouts" product explicitly covering 70+ countries and 30+ currencies.
- Exposes a REST + HMAC-signed webhook API; supports Charitable/Religious entities under their "Restricted" category (enhanced due diligence, not blocked).
- **Caveats:** No Indian entity, no FIU-IND registration surfaced; charity is a "Restricted" vertical, not "Prohibited"; a **July 27, 2026 wallet incident** is flagged on the homepage and must be reviewed before sign-up.

**Runner-up: NOWPayments fiat processing (https://nowpayments.io) via partner Switchere.** Strong crypto acceptance (350+ assets) and a published "Off-ramp payouts" product. **But INR off-ramp is not exposed** — the live "Fiat Processing" page only mentions **SEPA (EUR)** settlements through Switchere. India is not in the live settlement list. Treat NOWPayments as a viable INR-on-receipt self-custody option, not as an INR off-ramp.

**Hard no for INR off-ramp as of today:**
- **Coinbase Commerce** — SPA-only site, no India country support published; primarily crypto-to-crypto and USD/EUR off-ramp via ACH/SEPA.
- **BitPay** — `bitpay.com/business/india` returns 404; settlement is USD/EUR (ACH, SEPA, Faster Payments) and US-focused per their NMLS license; INR not published.
- **CoinGate** — Lists India as supported for *receiving* payments, but CoinGate's fiat payout is SEPA-EUR only; no INR.
- **Plisio** — No INR payout path published.
- **Mesh** — Solana/ETH/USDC focus; INR off-ramp not published.
- **UniPayment (Lithuania)** — Claims to be a "global hybrid" with Binance Pay integration; explicitly states "UAB UniPayment does not provide crypto-asset services" — they are a fiat EMI agent, no INR.
- **Pi42 / WazirX / CoinDCX / ZebPay / Bitbns** — These are **crypto exchanges**, not merchant payment processors. None of them sell a "donor pays crypto, merchant receives INR" gateway. Pi42 is FIU-IND registered (REID: VA00045558) but only for derivatives/spot; WazirX advertises "FIU Registered" but only for exchange services.

**Headline regulatory finding:** Under the off-ramp model (the processor buys the VDA from the donor and settles INR to the merchant), the VDA transfer event — and therefore Section 194BA (1% TDS) — sits with the **donor**, not the processor and not the trust, provided the donor sells the VDA to a registered Indian VDA-SP at the point of payment. A charitable trust can issue a normal 80G receipt on the INR received, because it never holds the VDA. **The single biggest open question** is whether Triple-A's MAS-licensed entity qualifies as an Indian "VDA Service Provider" under PMLA — they are not FIU-IND registered and there is no India entity. This must be settled before live donor payments.

---

## 2. Per-processor cards

### 2.1 Triple-A — primary recommendation

- **INR settlement supported?** **Yes.** The payment-coverage page (https://www.triple-a.io/global-payment-coverage) lists an explicit table with `India | INR | Bank Account | Instant`. Same page advertises "70+ Countries Supported" and "30+ Currencies" and the product card states "Send payouts in 30+ local currencies across 70+ countries." Verified 2026-08-09.
- **Settlement rails:** Local bank account in INR; the support pages reference "next-day bank settlement" for E-commerce / Marketplace flows and "Instant" for India on the coverage table. The likely Indian rail for "Instant" payouts is IMPS or UPI through a partner bank; for "next-day" NEFT/RTGS. **The exact rail breakdown was not visible in the public pages — request from sales.**
- **Settlement timing:** "Instant" in the coverage table for India; "Next-day" in the E-commerce/Marketplaces product card. Some assets carry "T+1" / "Same Day" labels (e.g. CAD, JPY, PYG, ZAR, KRW) — India is consistently "Instant."
- **Asset coverage (ETH / SOL / USDT / USDC):** ETH ✅; USDT ✅; USDC ✅. **SOL not visible** in the headline "Supporting leading digital currencies — Bitcoin, Ethereum, USDC, USDT" wording on the Digital Currency Payments page; the Sitemap and license pages do not enumerate SOL. Treat **SOL as "verify at sign-up."**
- **Processing fee:** 1.0% per published pricing + network fee. No monthly minimum surfaced.
- **FX spread or payout fee:** Conversion spread on top of 1.0% when the donor asset ≠ the merchant settlement asset. Not separately disclosed on the public pricing page.
- **KYB requirements for a charitable trust:** Charity is in the **"Restricted"** category on https://www.triple-a.io/restricted-prohibited-businesses, not "Prohibited." The policy says: "Charities, social service organisations and other non-profit or political organisations. Restricted." Restricted means enhanced due diligence (additional documentation, in-depth review, stricter monitoring) but not a hard block. **Build a packet in advance:** trust deed, PAN, 12A / 80G registration, board resolution, two independent KYB signers, and a brief statement of activities. **"Trust" / "Bearer shares" entities are separately Prohibited** — be careful to describe the entity as "Section 12A / 80G registered public charitable trust, Indian income-tax Act, 1961" rather than as a "private trust" or "trust company."
- **Donor-side KYC threshold:** Not published; Triple-A's checkout does not require donor KYC for typical donation sizes per the homepage marketing ("No chargeback"). EU MiCA rules apply to EU donors only.
- **API surface:** REST + JSON per the support page. HMAC over raw body with a shared secret for webhooks. PHP SDK not first-party; integrations use `guzzle` against documented endpoints. Docs at the `support.triple-a.io` knowledge base (live) — the public `docs.triple-a.io` URL **does not resolve** as of 2026-08-09 (DNS NXDOMAIN); use the support portal instead.
- **Sandbox availability:** Mentioned in the support nav ("Integration Options") but the sandbox URL was not surfaced in the homepage HTML; sign-up at https://www.triple-a.io/signup and request sandbox credentials from the assigned account manager.
- **Last-mile India operational footprint:** **No Indian entity, no FIU-IND registration, no RBI licence** is surfaced on https://www.triple-a.io/license. The license page lists:
  - **Singapore:** Triple A Technologies Pte. Ltd. — Major Payment Institution (MPI) by MAS (PS20200525).
  - **EU:** Paytop SAS (Triple-A EU) — licensed as a Payment Institution by ACPR (France) and as a CASP by AMF (France), CASP Registration No.: A2026-015.
  - **US:** Triple A Technologies Inc. — FinCEN MSB (31000330633586), money transmitter NMLS ID: 251, with US state money-transmitter licences (AZ, DE, DC, FL, IA, IL, MD, MI, MO, NJ, NM, OH, OR, PA, WA) and exempt in CO, MT, NC, WI, WY.
  - **India:** **NOT MENTIONED.** No MAS-equivalent or FIU-IND entry.
  - The "Official Statement Regarding Recent Wallet Activity — July 27, 2026" is a live disclosure on the homepage. Root-cause document was not retrieved in this session. **Decision-grade prerequisite:** obtain the post-mortem and verify no donor funds were lost.
- **Maturity / risk flags:** Operating since ~2018. **The July 2026 wallet incident** is the most material flag for the trust. Otherwise: MAS MPI (Tier-1 regulator), EU CASP under MiCA (Tier-1), US FinCEN MSB + state licences (broad). Charity = "Restricted" is workable but will slow onboarding. India is listed as "Instant" payout in the coverage table, but the operational rail relies on a partner bank; request the partner bank name and the banking hour / cutoff matrix in writing.

### 2.2 NOWPayments fiat processing — runner-up (but no live INR)

- **INR settlement supported?** **No (in production).** The "Off-ramp payouts" / "Fiat Processing" pages (https://nowpayments.io/payouts and the redirected `https://nowpayments.io/fiat`) both 404 in the rendered HTML; the live "Fiat Processing" copy on the working `/fiat` URL explicitly references **SEPA (EUR)** only. Verbatim from the published FAQ on that page: *"What is Switchere? Switchere — is a partner company of ours, that provides an exchange of crypto to assets with consequent withdrawal to your **SEPA** account."* A follow-up FAQ item asks: *"Do you plan to have a fiat to crypto opportunity?"* — NOWPayments is still on a fiat-to-crypto roadmap, not INR off-ramp. Verified 2026-08-09.
- **Settlement rails (where supported):** SEPA / SEPA Instant — EUR only.
- **Settlement timing:** "Single business day to reach your bank account" (per published FAQ).
- **Asset coverage:** All four required assets (ETH ✅, SOL ✅, USDT ✅, USDC ✅) on the receiving side, per the homepage coin menu and dedicated coin pages (`nowpayments.io/supported-currencies/...`).
- **Processing fee:** **0.5%** headline per the pricing page ("0.5% for monocurrency payments, 1% for payments with conversion"). 350+ crypto coins supported. Crypto-to-fiat donation flow is explicitly marketed: "Accept crypto donations while settling in traditional fiat currency to your bank account" (https://nowpayments.io/charity).
- **FX spread or payout fee:** **2.3–1.5% fiat withdrawal fee** (volume-dependent) per the live Fiat Processing page. Combined with the 0.5% processor fee this is the headline all-in cost when SEPA is used. **India is not in the supported-fiat list**, so this fee does not apply today.
- **KYB requirements for a charitable trust:** "For Charity" is a listed vertical. The Fiat Processing KYB packet requires Certificate of Incorporation, Articles of Association, proof of legal address (≤3 months), extract from register of legal entities (≤3 months), Business License (if applicable), AML policy document, source-of-funds documentation (bank extract, annual report), source-of-wealth for UBOs, notarized UBO/director extract. This is heavier than a typical merchant KYB and is handled through the partner Switchere.
- **Donor-side KYC threshold:** NOWPayments itself does not require donor KYC for typical donation sizes; Switchere may apply its own threshold at the fiat settlement boundary.
- **API surface:** REST + JSON, IPN webhook signed via `x-nowpayments-signature` (HMAC-SHA-512 over raw body). PHP SDKs exist on Packagist (namespace caution: `nowpayments/nowpayments-php` and `anergican/nowpayments-php`).
- **Sandbox availability:** Yes — `https://sandbox.nowpayments.io` is referenced in the docs; sign-up at the merchant dashboard.
- **Last-mile India operational footprint:** **None.** Vilnius, Lithuania (parent ChangeNOW group). No FIU-IND registration. The Switchere partner is also EU-based.
- **Maturity / risk:** Operating since ~2019. No major publicly disclosed incident. The big risk for our buyer is **regulatory rather than operational**: it is not registered in India, and so it cannot legally intermediate VDA-to-INR under PMLA. Crypto donations to NOWPayments work, but the INR leg would have to come through a separate Indian FIU-registered VDA-SP — defeating the purpose of using NOWPayments for the off-ramp.

### 2.3 BitPay

- **INR settlement supported?** **No.** `https://www.bitpay.com/business/india` returns a 404 ("Lost in space"). The support article on supported countries (`support.bitpay.com/hc/en-us/articles/360059847631`) returned only a Cloudflare challenge ("Just a moment...") and could not be parsed in this session. The Wikipedia article (https://en.wikipedia.org/wiki/BitPay) confirms the company is headquartered in Atlanta, GA, with European HQ in Amsterdam and South American HQ in Argentina; settlement is USD/EUR (and select other fiat) primarily via US ACH and EU SEPA. **No India subsidiary, no INR support.**
- **Settlement rails (where supported):** US ACH, US wire, EU SEPA, UK Faster Payments, plus card payouts.
- **Settlement timing:** 1–2 business days after crypto confirmations.
- **Asset coverage:** BTC, ETH, BCH, LTC, XRP, USDC, GUSD; SOL has been added at the consumer wallet layer but merchant-side coverage is thinner. USDT is supported on Ethereum (ERC-20); other chains vary.
- **Processing fee:** **1.0% + network fee.** No monthly minimum.
- **FX spread:** Spread on the conversion side (not separately disclosed); ACH/SEPA payout fee additional.
- **KYB for charitable trust:** BitPay has a long history of supporting US 501(c)(3) charities (BitGive was the first IRS-recognised bitcoin charity, 2014). Non-US charities are accepted under their standard KYB.
- **Donor-side KYC threshold:** No mandatory donor KYC under typical donation sizes.
- **API surface:** REST + JSON at https://developer.bitpay.com. Webhook signed with ECDSA over the payload (more sophisticated than HMAC; trust adapter can be written).
- **Sandbox:** Yes — `test.bitpay.com`.
- **Last-mile India operational footprint:** None. Operates under NYDFS Virtual Currency Business Activity licence plus state money-transmitter licences (NMLS ID#1496848 per the homepage footer).
- **Maturity / risk:** Founded 2011; one of the oldest US processors. 2014 hot-wallet theft (~5,000 BTC) — insurance recovery was litigated. Now SOC 2 Type II. Brand strong; INR support absent.

### 2.4 Coinbase Commerce

- **INR settlement supported?** **No.** `commerce.coinbase.com` is a Cloudflare-fronted SPA shell (the static HTML is just "Just a moment...") and the help page on supported countries returned the same Cloudflare shell in this session. Coinbase the company has a contentious history with India (Coinbase India app pulled, 2023 fee-waiver episode, periodic geo-restrictions on consumer products). The Commerce product documentation (`docs.commerce.coinbase.com`) was not parseable in this session. Treat India eligibility as **unverified**.
- **Settlement rails:** USDC to merchant self-custody wallet (no fiat off-ramp in the headline product); fiat off-ramp for Coinbase Commerce merchants historically limited to USD via ACH.
- **Settlement timing:** Self-custody settles after chain confirmations.
- **Asset coverage:** ETH ✅; USDC ✅ (ERC-20 + native SPL); USDT ✅ (ERC-20; no TRC-20); SOL ✅ (added 2024). All four required assets supported.
- **Processing fee:** **1.0% flat** per the published standard rate (long-standing).
- **FX spread:** None in the standard crypto-to-crypto flow; spread applies only if the merchant chooses the (now-deprecated) convert-to-fiat flow.
- **KYB for charitable trust:** Standard Coinbase KYB accepts US 501(c)(3); non-US charities need to go through manual review.
- **Donor-side KYC:** Not typically required for Commerce.
- **API surface:** REST + JSON, webhook HMAC-SHA256 over raw body with shared secret. PHP SDK is community-maintained.
- **Sandbox:** Yes, separate test environment.
- **Last-mile India operational footprint:** **None.** Coinbase India (consumer) was geo-restricted as of 2023–2024. The Commerce product itself sits on Coinbase Inc.'s US MSB licence; no India entity.
- **Maturity / risk:** NASDAQ: COIN. Tier-1 brand. Risk for the trust is **India eligibility uncertainty** + the indirect crypto-self-custody default (which the trust does NOT want).

### 2.5 India-domestic crypto merchant processors

**Headline: there is no live India-domestic crypto merchant-payment gateway selling the "donor pays crypto, merchant receives INR in bank account" flow in 2026.**

| Candidate | Reality |
|---|---|
| **Razorpay (razorpay.com)** | No crypto product. `https://razorpay.com/docs/payments/crypto/` returns 404. Razorpay's product surface remains INR + cards/UPI/netbanking. |
| **Cashfree (cashfree.com)** | No crypto product. `https://www.cashfree.com/business/crypto-payment-gateway` and `/docs/payments/online/crypto` both return the standard 13 KB "let us guide you back" stub. |
| **CoinDCX, ZebPay, WazirX, Bitbns, CoinSwitch** | Crypto **exchanges**, not merchant payment gateways. WazirX advertises "F.I.U Registered" but only for spot/P2P; no merchant off-ramp. CoinDCX has the same shape. ZebPay's `/merchant` is a 404. |
| **Pi42** | Indian FIU-IND registered VASP (REID VA00045558) — but **derivatives-only** exchange ("Pi42 is not a spot exchange and does not allow direct buying or selling of crypto."). No merchant payment product. |
| **Unipayment (unipayment.io)** | Lithuania-based, registered with National Bank of Belgium as an EMI agent; UniPayment Canada Inc. registered with FINTRAC as MSB. Self-declares: **"UAB UniPayment does not provide crypto-asset services."** Integrates Binance Pay for crypto leg. Settlement currencies are USD, EUR, GBP, AUD (via SEPA/ACH/SWIFT). **No INR.** |
| **CoinPayments (coinpayments.net)** | 250 k merchants, 40+ crypto accepted, ISO 27001. Custodial wallet services by PaidInSatoshi Inc. (Panama) and Centauri Pay LLC (US). Says "convert crypto to fiat for bank payout **where supported**" — India is not listed on the live site. Page footer says "services via third-party partners" — i.e. India support would require a local partner. Treat as **no INR** until proven otherwise. |
| **Mesh, Plisio, BTCPay Server** | Covered in the 2026-08-08 self-custody note. None offer INR off-ramp. |

**India-domestic processor verdict:** The Indian regulator (FIU-IND, RBI) has not licensed a "crypto payment gateway" product the way MAS or ACPR has. Indian FIU-registered VASPs all run **exchange** services. The off-ramp rails (NEFT/IMPS/UPI/RTGS) are reached indirectly by an Indian customer selling crypto on a registered VDA-SP exchange, **not** via a payment-gateway API. For our use case, that means the trust effectively has only one realistic option — Triple-A's "India INR — Instant" payout — and it routes through Triple-A's partner bank, not through a domestic FIU-registered VDA-SP.

---

## 3. India regulatory analysis

### 3.1 Donor VDA tax under the off-ramp model

**Background.** The Finance Act 2022 inserted Section 194BA into the Income-tax Act, 1961 (1% TDS on the **transfer** of a Virtual Digital Asset, payable by the buyer / deductor, above ₹50,000 in a financial year for specified individuals; ₹10,000 for others) and Section 115BBH (30% flat tax on VDA income, no set-off of losses, no deduction for expenses other than cost of acquisition). Section 2(47A) defines "Virtual Digital Asset." The CBDT Circular 13/2022 (https://www.incometax.gov.in) clarified that "transfer" includes sale, exchange, relinquishment, or extinguishment.

**Question for our use case:** When a donor pays crypto and the trust receives INR through an off-ramp, does the donor still trigger Section 194BA?

**Working analysis.** Section 194BA attaches to the *transfer of the VDA*. Under the off-ramp model, the VDA is transferred by the donor to **the processor** (Triple-A's wallet), not to the trust. If the processor is the deductor and remits the 1% TDS at the time of conversion to INR, the donor's compliance obligation is satisfied at that moment and the trust receives pre-cleared INR. If the processor is **not** a deductor under Section 194BA (which is the more likely reading — Triple-A is not registered as a VDA-SP in India), the donor remains responsible for self-assessing the 1% TDS in their own return and paying the 30% tax under Section 115BBH on any gain above the cost of acquisition.

**Practical implication for the trust.** The trust should:
1. **Disclose in the donation flow** that the donor is selling a VDA to a third-party processor and that any Section 194BA / Section 115BBH obligation is the donor's, not the trust's. A short paragraph on the donation page and in the receipt is sufficient.
2. **Require processor to provide a per-transaction valuation** (the INR equivalent at settlement timestamp) so the donor has the data point they need for their own return. Triple-A's webhook includes the settled INR amount; require this field.
3. **Capture donor cost basis** (optional, opt-in) so that for donors who volunteer the info, the trust can issue a statement acknowledging the gift's INR valuation basis. Do not make cost-basis collection mandatory — it creates friction.
4. **Treat any crypto that bypasses the off-ramp** (e.g. a donor wires ETH to the trust's own wallet) as a **separate** event: the trust then becomes the VDA recipient and triggers its own Section 115BBH reporting. **This is the path the prior 2026-08-08 self-custody note said "no 80G receipt"** — it still applies.

**Open question.** Is Triple-A (or its partner bank) the Section 194BA deductor when it converts crypto to INR for a non-Indian donor? Our reading: no, because Triple-A is not an Indian "buyer" paying for the VDA in INR — Triple-A's role is to deliver INR to the merchant from its own stablecoin balance. **This needs written counsel sign-off before sign-up.**

### 3.2 80G receipt eligibility under the off-ramp model

**Background.** Section 80G of the Income-tax Act, 1961 allows deduction of donations to notified funds / institutions, subject to a 50% or 100% deduction limit and a qualifying limit of 10% of gross total income. The donor must make the donation in a "mode" prescribed by Rule 18A: cash ≤ ₹2,000; cheque / demand draft / electronic clearing / bank transfer / UPI / prescribed electronic modes. Crypto is **not** a prescribed mode.

**Question for our use case:** Can the trust issue an 80G receipt when the donor pays crypto that is converted to INR before it reaches the trust?

**Working analysis.** Rule 18A requires the **transfer instrument** to be a prescribed mode. The instrument here is the on-chain transfer from donor wallet to processor wallet — that is **not** a prescribed mode. Even though the trust receives INR, the donor's act was a transfer of a VDA, not a transfer of INR. The most defensible position is that **no 80G receipt is allowed for the on-chain leg**.

**Practical guidance for the trust.**
1. **Issue a non-80G receipt** acknowledging the gift, with the INR equivalent at settlement timestamp, a reference to the on-chain transaction hash, and a clear statement: "Donation received in INR via authorised payment processor. Donor paid in Virtual Digital Asset; receipt is issued in INR equivalent. **This is NOT a Section 80G receipt.** Donor's tax-deduction eligibility, if any, is to be assessed by the donor's tax adviser."
2. **Do not promise 80G** on the donation page; this is the most common compliance pitfall in India crypto-charity literature.
3. **For donors who insist on 80G**, route them to the existing INR-only donation flow (Razorpay UPI / netbanking / NEFT) which is already 80G-eligible.
4. **For PIO / NRI / foreign donors**, Section 80G is generally not available anyway — they use Section 80G only for specific notified funds. Most foreign crypto donors will not be eligible regardless of the rail.

**Open question.** Does the CBDT / Income Tax Department view a crypto-off-ramp-then-INR donation as a "transaction in a mode other than prescribed" (i.e. no 80G) or as a de-facto INR donation via an intermediary (i.e. eligible)? No formal circular addresses this scenario. **Treat as no 80G by default until CBDT guidance clarifies.**

### 3.3 FEMA exposure

**Background.** The Foreign Exchange Management Act, 1999 (FEMA) governs foreign-exchange transactions in India. Receiving INR into an Indian bank account from a foreign donor is, in principle, an inbound foreign-exchange transaction; receiving INR from an Indian-resident donor is a domestic transfer and is not a FEMA event. Donations from outside India are subject to additional FCRA / FEMA reporting (FLA return, AD-I returns, and where applicable FCRA registration for foreign-origin funds).

**Question for our use case:** Does receiving INR from a FIU-registered Indian crypto processor trigger any FEMA reporting for the trust?

**Working analysis.** **No**, on the facts presented:
- The processor pays INR into the trust's Indian bank account. This is a domestic INR payment, not a foreign-exchange transaction. **The fact that the upstream donor was a non-resident is invisible to the bank rail** — Triple-A's partner bank debits an Indian INR balance (Triple-A's own Nostro or operating account) and credits the trust's Indian INR account. No FEMA event is triggered at the beneficiary leg.
- The processor itself bears any FEMA risk on its own conversion of foreign-origin crypto to INR, which is its licence problem (not the trust's).
- **FCRA caveat:** if the trust holds an FCRA registration and a significant portion of donations originates from foreign donors routed through crypto, FCRA reporting obligations (FC-3 / FC-4 annual returns) are unaffected by the rail — the trust still reports the foreign-origin donation. The crypto rail does not change the FCRA classification.

**Practical implication.** The trust does not need to amend its FEMA posture for the crypto rail. Continue with the standard FCRA / FLA reporting as applicable.

### 3.4 Processor FIU-IND registration

**Background.** PMLA 2002 (as amended in 2023 by the Finance Act, with notification effective 1 March 2023) brought Virtual Digital Asset Service Providers (VDASPs / VDA-SPs) under the definition of "reporting entity" under Section 2(1)(sa). Every entity dealing in VDAs in India on behalf of another must register with the Financial Intelligence Unit - India (FIU-IND). The CBDT / FIU-IND "Registration of Virtual Digital Asset Service Providers in FIU-IND as Reporting Entity" notice is live on https://fiuindia.gov.in (verified 2026-08-09). Penalties for non-registration include up to ₹5 lakh per day under Section 13 of PMLA.

**FIU-IND registrations published on the FIU-IND site.** Indian exchanges WazirX, CoinDCX, ZebPay, Bitbns, CoinSwitch Kuber, Giottus, and Pi42 (REID VA00045558 — confirmed on the Pi42 homepage) are listed. **Triple-A is not listed** — it is not an Indian VDA-SP and its coverage of India goes through a partner-bank payout leg, not through a domestic VDA exchange.

**Practical implication.**
1. **TripleA is not itself an FIU-IND reporting entity** — it routes INR via partner banks. The trust should ask TripleA in writing who the Indian partner bank is and whether that partner holds its own FIU-IND registration for any VDA leg.
2. **Under a strict reading of PMLA**, an entity that facilitates VDA → INR conversion for Indian residents should be registered. If TripleA's partner bank does the conversion, the partner bank is the reporting entity. If TripleA itself does the conversion cross-border (crypto in, INR out via offshore bank), the regulatory exposure sits with TripleA, not the trust.
3. **For the trust**, the safest posture is to add a one-line vendor diligence clause to the merchant agreement: "Processor warrants that it and/or its Indian payout partner holds all registrations required under PMLA 2002 (as amended) and FIU-IND guidelines for facilitating INR settlement from Virtual Digital Asset receipts to Indian-resident beneficiaries."

---

## 4. Red flags and unknowns to verify before sign-up

### Legal / contractual
1. **TripleA July 27, 2026 wallet incident** — obtain the post-mortem PDF from the official statement page on the homepage and verify that no donor / merchant funds were lost and that hot-wallet architecture has been remediated.
2. **Charity = "Restricted"** in TripleA's policy — confirm in writing that a Section 12A / 80G registered Hindu temple trust qualifies and that the EDD requirements do not include India-specific items the trust cannot provide (e.g. SEBI / RBI registration, which is N/A for a temple trust).
3. **India "Instant" payout** — confirm the rail (IMPS / UPI / RTGS / internal credit) and the **banking-hour cutoff** for "Instant" vs "next-day" settlement. Also confirm the partner-bank name and that there is no upper-limit on INR payouts per transaction per day.
4. **No India entity / no FIU-IND** — obtain written confirmation that TripleA's partner bank is the Section 194BA deductor (or that no deduction is required) and that TripleA is not itself an unregistered Indian VDA-SP under PMLA.
5. **Refund / clawback** — TripleA's policy on refunds in INR (the donor's crypto is already spent) needs explicit language. Crypto is pseudo-irreversible; partial refunds may need to be paid from the merchant's INR float, not from the processor's wallet.
6. **AML/KYC on the trust** — KYB packet includes PAN, 12A, 80G, board resolution, KYC of two trustees, trust deed, source-of-funds statement (donations are the source), and a statement of charitable purpose.

### Tax / regulatory
7. **Section 194BA deductor identity** — counsel-confirmed answer to "is the processor the Section 194BA deductor when it pays INR for the VDA?"
8. **Section 115BBH** — donor side. Issue a donor-facing one-pager stating the donor's tax obligations under VDA law; do not let the trust become the donor's tax adviser.
9. **Section 80G** — confirm in writing that no 80G receipt is issued for crypto-on-ramp donations; route donors who want 80G to the existing INR flow.
10. **FCRA** — if foreign-origin donations exceed the FCRA threshold (currently ₹1 crore in a financial year or 10% of total donations, per the FCRA Amendment Act 2020), the existing FCRA registration covers INR-only; crypto does not change the regime, but reporting should explicitly note the rail.

### Operational
11. **Webhook reliability** — TripleA's webhook signs via HMAC; the trust's `PaymentGatewayContract` HMAC adapter works with per-processor key configuration. Add retry-with-jitter because crypto chain reorgs and webhook delivery delays are common.
12. **Confirmation threshold** — TripleA's "Instant" INR payout likely triggers at a lower crypto confirmation count than the trust's risk tolerance. Confirm and document.
13. **Multi-tenant isolation** — every tenant must receive payouts into its own named bank account (not pooled). TripleA supports named accounts per merchant; verify in the KYB flow.
14. **Hot-wallet exposure** — TripleA's hot-wallet incident is the reason for the July 2026 statement. For high-volume donation days, the trust may want a temporary policy of routing large gifts through the existing INR flow (Razorpay UPI / NEFT) rather than the crypto rail, until the post-mortem is fully digested.
15. **OFAC / UN sanctions screening** — confirm TripleA screens donor transactions against OFAC and UN consolidated lists before crediting the merchant balance. Request a copy of their sanctions-screening policy.
16. **Stablecoin depeg risk** — USDC / USDT depegs create an immediate INR valuation issue. Hold USDT/USDC exposure only as long as necessary; convert to INR via the off-ramp quickly. TripleA's "Instant" rail naturally addresses this if the trust funds settlement from its own stablecoin float.

### Things I could not verify in this session
17. **TripleA API documentation** — the `docs.triple-a.io` subdomain does not resolve as of 2026-08-09 (DNS NXDOMAIN). The live docs are on `support.triple-a.io`; obtain a sandbox account and re-pull the API spec via the dashboard.
18. **TripleA's Indian partner bank** — not disclosed on the public site; sales-led diligence required.
19. **NOWPayments future INR support** — the "Do you plan to have a fiat to crypto opportunity?" FAQ item on the Fiat Processing page suggests INR support is still roadmap. Ask NOWPayments directly; their Switchere partnership is EU-focused.
20. **Coinbase Commerce India eligibility** — `commerce.coinbase.com` is a Cloudflare SPA shell; the support page on supported countries returned the same shell. Sign-up flow at the merchant dashboard is the only reliable check.
21. **BitPay India eligibility** — `bitpay.com/business/india` 404s; the historical geo-restriction list is not surfaced in the live HTML. Re-pull from `bitpay.com/terms-of-use`.
22. **Section 194BA deductor interpretation under off-ramp** — no CBDT circular or Tribunal ruling specifically addresses this scenario as of 2026-08-09. Counsel sign-off is the operational gate.
23. **80G eligibility under crypto-off-ramp** — no CBDT guidance. Treat as no-80G by default.

---

## 5. Cited URLs (verification status as of 2026-08-09)

### TripleA
- https://www.triple-a.io — verified; lists India INR Instant in coverage table; July 27, 2026 wallet incident disclosure on homepage
- https://www.triple-a.io/global-payment-coverage — verified; coverage table includes `India | INR | Bank Account | Instant` (extracted 2026-08-09)
- https://www.triple-a.io/global-payouts — verified; "Send payouts in 30+ local currencies across 70+ countries"
- https://www.triple-a.io/multicurrency-accounts — verified; "Move collected EUR from your IBAN into your bank account, stablecoin wallet, or local currency payout across 70+ countries"
- https://www.triple-a.io/digital-currency-payments — verified; "Supporting leading digital currencies — Bitcoin, Ethereum, USDC, and USDT"
- https://www.triple-a.io/digital-currency-payouts — verified; "Send and receive payments in the most trusted digital assets— Bitcoin, Ethereum, USDC, and USDT—across major blockchain networks"
- https://www.triple-a.io/restricted-prohibited-businesses — verified; "Charities, social service organisations and other non-profit or political organisations. **Restricted**"
- https://www.triple-a.io/license — verified; lists MAS Singapore MPI (PS20200525), Paytop SAS ACPR + AMF CASP (A2026-015), US FinCEN MSB (31000330633586) and US state money-transmitter licences; **no India entity listed**
- https://www.triple-a.io/sitemap.xml — verified; no `docs.triple-a.io` entry, only the marketing site
- https://support.triple-a.io — verified; live knowledge base with categories for Onboarding, Integration Options, Stablecoin Payments, Stablecoin Payouts, Local Currency Payouts, Settlement & Withdrawals
- https://www.triple-a.io/cryptocurrency-ownership-data/india — verified; "97.5 million people, 7.1% of India's total population, currently own cryptocurrency"
- https://www.triple-a.io/about-us — verified; tagline "Pay and get paid globally in stablecoins & local currencies"
- **https://docs.triple-a.io** — DNS NXDOMAIN, does not resolve (2026-08-09)
- **https://support.triple-a.io/category/local-currency-payouts** — returned 404 stub in this session; live content exists in the support nav
- **https://support.triple-a.io/payment-coverage** — 404 in this session; live coverage table is on the marketing site

### NOWPayments
- https://nowpayments.io — verified; "Accept Bitcoin, stablecoins and 300+ cryptocurrencies"; 0.5% / 1% fee structure
- https://nowpayments.io/payouts — 404 stub (SPA redirect)
- https://nowpayments.io/fiat — verified; "Charge crypto - settle in fiat!" with full SEPA-only copy ("withdraw fiat to your SEPA account"); 2.3–1.5% fiat withdrawal fee
- https://nowpayments.io/pricing — verified; "0.5% Service fee"
- https://nowpayments.io/charity — verified; "Accept crypto donations while settling in traditional fiat currency to your bank account"
- https://nowpayments.io/supported-currencies/tether-payments — verified; USDT supported
- https://nowpayments.io/supported-currencies/ethereum-payments — verified; ETH supported
- https://nowpayments.io/supported-currencies/solana-payments — verified; SOL supported
- https://nowpayments.io/currencies/stablecoins — referenced in the homepage coin menu
- https://nowpayments.io/blog/crypto-payment-gateway-for-india — 404 in this session
- https://nowpayments.io/blog/what-is-vda-taxation — 404 in this session
- https://nowpayments.io/blog/india — 404 in this session

### BitPay
- https://www.bitpay.com — verified; "BitPay turns 15" branding; supports BTC, ETH, BCH, LTC, XRP, USDC, GUSD, SOL (consumer wallet layer)
- https://www.bitpay.com/pricing — verified HTML payload (CSS-heavy); standard 1% rate; "BitPay is licensed to engage in Virtual Currency Business Activity by the New York State Department of Financial Services"
- https://www.bitpay.com/business/india — 404 ("Lost in space?") in this session; **India page does not exist**
- https://support.bitpay.com/hc/en-us/articles/360059847631-Available-Countries — Cloudflare challenge ("Just a moment...") in this session; live country list requires browser fetch
- https://developer.bitpay.com — verified; REST + ECDSA webhook API reference
- https://en.wikipedia.org/wiki/BitPay — verified; founded May 2011 by Tony Gallippi and Stephen Pair; Atlanta GA HQ; European HQ Amsterdam; South American HQ Argentina

### Coinbase Commerce
- https://commerce.coinbase.com — Cloudflare challenge, SPA shell only ("Just a moment...")
- https://commerce.coinbase.com/inr — Cloudflare challenge
- https://commerce.coinbase.com/india — Cloudflare challenge
- https://help.coinbase.com/en/coinbase/getting-started/coinbase-commerce-faq — Cloudflare challenge
- https://docs.cdp.coinbase.com/commerce/docs/welcome — verified; Coinbase Developer Platform documentation exists
- https://en.wikipedia.org/wiki/Coinbase_Commerce — no Wikipedia article (verified; "Wikipedia does not have an article with this exact name")
- https://en.wikipedia.org/wiki/Coinbase — verified; Coinbase Inc. background, NASDAQ: COIN, MSB-registered

### CoinGate
- https://www.coingate.com/pricing — verified; "Standard 1% per transaction"; "Accept payments from 180+ countries"; "On-demand automatic settlements" for Enterprise tier
- https://www.coingate.com/supported-countries — verified; India is in the supported-merchant-country list; **no INR payout surface** (SEPA / EUR focus only)
- https://www.coingate.com/terms — verified; mentions MiCA; India not excluded but INR off-ramp not advertised
- https://en.wikipedia.org/wiki/CoinGate — verified; Lithuanian VASP under MiCA

### Plisio
- https://plisio.net/pricing — verified; "Gateway API 0.5% fee", "White Label 1.5% fee"; "90% of funds are stored In cold wallets"
- https://plisio.net/documentation — verified; REST GET-only API
- https://plisio.net/terms — verified; ToS

### CoinPayments
- https://www.coinpayments.net — verified; 250k+ merchants; 40+ crypto; custodial via PaidInSatoshi Inc. (Panama) and Centauri Pay LLC (NJ, US); "Convert crypto to fiat for bank payout where supported"

### UniPayment
- https://unipayment.io — verified; Lithuania-based, EMI agent with National Bank of Belgium; UniPayment Canada Inc. FINTRAC MSB; explicit "UAB UniPayment does not provide crypto-asset services"; integrates Binance Pay; settlement currencies USD/EUR/GBP/AUD

### Indian FIU-IND registered VASPs (for context — not merchant processors)
- https://pi42.com — verified; "FIU-IND Registered VASP — REID: VA00045558"; "Pi42 is not a spot exchange and does not allow direct buying or selling of crypto"
- https://wazirx.com — verified; footer advertises "F.I.U Registered" (consumer exchange)
- https://zebpay.com/merchant — 404 (no merchant payment product)
- https://www.coindcx.com/merchant — 5 KB redirect stub (no merchant payment product)
- https://bitbns.com/merchant — 162 bytes (no merchant payment product)

### India regulatory sources
- https://fiuindia.gov.in — verified; "Registration of Virtual Digital Asset Service Providers in FIU-IND as Reporting Entity" notice is live
- https://www.incometax.gov.in/iec/foportal/sites/default/files/2024-06/TDS%20on%20Virtual%20Digital%20Assets%20-%20Section%20194BA.pdf — PDF downloaded (text extraction blocked by missing `pdftoppm`); Section 194BA live reference
- https://www.incometax.gov.in/iec/foportal/sites/default/files/2025-03/Circular-13-2022-VDA.pdf — CBDT Circular 13/2022 downloaded (text extraction blocked by missing `pdftoppm`); primary clarifier on Section 194BA scope
- https://www.cleartax.in/s/cryptocurrency-tax-india — 404 in this session; ClearTax crypto-tax URL has moved or is gated
- https://www.caclubindia.com/articles/section-194BA-of-income-tax-act-1961 — 404 in this session; CAClubIndia archive route has changed
- https://www.indiafilings.com/learn/cryptocurrency-taxation-india/ — verified (JS-shell); no static text content extractable
- https://www.bajajfinserv.in/insights/crypto-taxes-in-india — heavy JS-rendered nav; no static text content extractable in this session
- https://blog.wazirx.com/section-194ba/ — verified; WazirX blog header mentions "F.I.U Registered 300 + Crypto Assets 16 Million+ Registered Users"; crypto-tax articles present but Section 194BA deep-dive not surfaced in this session's extraction

### Prior research artefacts
- /Users/kamii/Vishwaguru-webview-kernel/vishwa-guru-webview-kernel/docs/research/crypto-payment-processors-2026-08-08.md — the upstream self-custody / IBAN-off-ramp research note (CoinGate primary, BTCPay self-hosted fallback, TripleA complementary)

---

*Prepared for SPEC use only — no code is being written. Numbers marked "verified" were pulled directly from the linked page on 2026-08-09 via `curl -sL -A "Mozilla/5.0 ..."`. Items marked "unverified" or "could not verify" should be re-pulled in a follow-up pass before any production commitment.*