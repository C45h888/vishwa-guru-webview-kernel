# Vishwaguru Webview Kernel — Content Knowledge Base

Status: Working editorial baseline — founder narrative confirmed, legal facts pending  
Owner: Project team  
Purpose: Give every agent one reliable brief for understanding, reviewing, and improving the public copy.

## 1. Editorial objective

The site should present the trust as a credible, spiritually grounded, operationally serious charitable organisation. Its copy should feel warm and devotional without becoming vague, inflated, sentimental, or institutionally generic.

Every page should answer, in plain language:

1. Who is the trust?
2. What has it actually done?
3. What is it doing now?
4. What does the visitor or donor enable?
5. What evidence or accountability supports the claim?

## 2. Approved foundation narrative

The current working narrative is:

- Legal name: **Sri Vishwaguru Sri Sri Sriram Shishyavrundham Mahasamsthanam**.
- Public short name: **VSRSMS**.
- Spiritual leader and public figure: **Sri Ram Ram Das Guruji**.
- Guruji came from a teaching background and became a respected ritual and spiritual guide in Mysore.
- In 2012, Guruji received Power of Attorney for the trust and began the major service endeavour described below.
- The trust operated its children’s-care chapter from approximately 2007 to 2022.
- Across changing batches, approximately 300 children in need of care were housed, educated, and supported over that period.
- The children also received cultural and practical skills, including Bharatanatyam and broader life skills.
- That residential chapter is no longer active because the children completed their education and moved forward in life.
- The current project is a proposed three-acre healing and service campus near Nanjangud, outside Mysore.
- The land has been under discussion and may be reserved, but additional capital is required to complete the acquisition.
- The planned campus includes a Gaushala, a simple Shiva temple, Kamadhenu and Shiva-family shrines, and a healing environment for people recovering from addiction or serious personal difficulty.
- Future plans include cow-related farming and products for devotees, such as Arkha milk and other products, subject to the project becoming operational.
- The campaign will support land acquisition, construction, and later the care and welfare of the cows.
- The trust intends to organise events and fundraisers for the project.

The spiritual idea behind the project is the movement from **tamas to sattva**: creating a disciplined, compassionate environment where service, devotion, care for cows, and the presence of Shiva support healing and inner transformation.

These statements form the working editorial narrative. Legal, financial, tax, registration, and medical or rehabilitation claims still require documentation and specialist review.

## 3. Critical source-of-truth conflict

The repository currently contains two incompatible stories:

- `resources/js/domains/cms/homepage-fallbacks.ts` and `about-fallbacks.ts`: children’s care followed by the Gaushala and Shiva-temple land campaign.
- `database/seeders/ProductionSeeder.php`: an older temple-operations story involving daily pooja, Annadanam, priest training, temple restoration, 1998/2005/2012/2019/2024 milestones, and named trustees who do not match the fallback copy.

The newly supplied VSRSMS narrative supersedes the legacy temple-operations narrative for future copy work. The legacy seed content must be treated as obsolete until it is replaced or explicitly reclassified.

Interim rule: agents must not reintroduce the legacy temple-operations story. They should identify every affected source and update the canonical content source, seed data, and fallbacks together.

## 4. Audience and positioning

The site serves several audiences through separate campaigns and landing-page journeys:

- Devotees of Sri Ram Ram Das Guruji.
- General donors reached through Instagram and lead funnels.
- Bengaluru-based CSR companies and local institutional supporters.
- International supporters and diaspora devotees.

The core brand voice should balance spiritual depth with professional charitable clarity. Individual campaigns may adjust emphasis, but they must not change the underlying facts, project purpose, or accountability language.

## 5. Campaign and claims guardrails

- Use “approximately 300” rather than an exact number unless records support a precise figure.
- Use “children in need of care”, not “orphans”, unless a legal or case-record basis is supplied.
- Describe the land as “under discussion” or “being pursued”, not acquired or secured.
- Describe the campus as proposed or planned until construction has begun.
- Present addiction recovery as a planned healing and support environment. Do not claim clinical treatment, licensed rehabilitation, or guaranteed outcomes without qualified documentation.
- Present cow products as future plans, not existing products.
- Donation uses must be staged clearly: land acquisition and construction first; cow care and welfare after the campus becomes operational.
- Donor certificates and complimentary cow-related products should be described as intended future benefits and must be reviewed for operational, legal, tax, and fulfilment implications.
- A separate legal page will be commissioned by the project owner. Copy agents may reference it once approved, but must not invent compliance language.

## 6. Public copy state (current as of the latest copy pass)

The previous "Initial copy diagnosis" listed the issues that triggered the public copy pass. Most of those issues have since been resolved. The remaining items are listed under §11.

### What the copy pass fixed

- The organisation name is now used as the formal legal name in the footer, the visit address, and the receipt title; the short brand VSRSMS is used in the header and as the homepage hero headline. The hero subtitle carries the full legal name on a single follow-up line.
- The About page now states the two chapters clearly: the children's-care chapter (2007–2022, approximately 300 children in need of care) and the current ask (the land acquisition for the proposed three-acre healing and service campus near Nanjangud, outside Mysore).
- The About page stats use "approximately 300" and "15+" years; the placeholder dash and "Multiple" values have been removed.
- The visit address is now Nanjangud, Karnataka (outside Mysore), not Mysore.
- The placeholder trustee "Sri-ram Ramdas" has been replaced with the canonical spiritual leader "Sri Ram Ram Das Guruji", with the role "Spiritual Leader, VSRSMS". The second trustee card now states that the board roster will be published once it is formally registered (no invented names).
- The timeline has been replaced with the four correctly-dated milestones: 2007, 2012 (Guruji receives Power of Attorney), 2022 (children's-care chapter concludes), 2026 (land acquisition campaign launches). The legacy 1998/2005/2019/2024 entries are gone.
- The campaign fallbacks library (`resources/js/shared/lib/campaign-fallbacks.ts`) has been replaced with three current-narrative categories — `land_acquisition`, `gaushala_build`, `cow_care_future` — and the legacy `maintenance`, `general`, `diwali` fallbacks have been rewritten to align with the new narrative. The "is this a live payment?" test-mode FAQ has been removed; the 80G hard claim has been softened to the contract-blessed phrasing.
- The homepage hero subtitle, the homepage story, the homepage programs, the homepage trust panel, and the homepage donate CTA have all been rewritten to align with the new narrative.
- The donate page now leads with "Support the land acquisition campaign" rather than "Donate". The donor information card description has been softened to the contract-blessed 80G phrasing.
- The campaigns index PILLARS have been replaced with the three fundraising stages. The campaigns index editorial copy and bottom CTA have been rewritten.
- The events `event-slides.ts` has been replaced with three new-narrative editorial slides (children's-care chapter, current land acquisition, planned campus construction). The legacy festival calendar labels (Varalakshmi Vratam, Cultural Evenings, Brahmotsavam) are no longer presented as the page's editorial story.
- The footer social links are now honestly disabled (cursor-not-allowed, aria-disabled, rel="nofollow") and expressed as "Facebook (coming soon)" etc. The Placeholder legal links now point to /contact until the legal page is reviewed and approved.
- The BottomCtaBand defaults now read "Support the land acquisition campaign" and "Your contribution moves the proposed healing and service campus one step closer.“ The legacy default "Join our mission of seva / daily pooja, annadanam, temple maintenance” is gone.
- The TrustBadgeRow now reads "Razorpay Secure · Nanjangud, Karnataka" instead of "Razorpay Secure · Registered Trust".
- The Contact page hero now reads "Contact the trust" and the body asks for "donations, events, CSR enquiries, project updates, and future volunteering or support" instead of "seva bookings".
- The ProductionSeeder ABOUT_PAGE about_page_content has been rewritten to mirror the new About fallback. The meta_description has been replaced with the new narrative summary. The sample campaign description has been reset to administrative support.

### Residual issues (tracked in §11)

- The assets pipeline has not changed: the public photos still need to be staged and wired so the gradient placeholders disappear. The CMS media ids referenced in the fallback files are still null; that is correct — the page renders the gradient fallback until assets are wired.
- The contact_information table currently has no office address beyond the placeholder; the About visit.address and the Visit card on the About page render the placeholder text. This is expected until the trust office is operational.
- The “Legal & Compliance” page is not yet published. The footer link routes to /contact until the legal page is reviewed and approved.

## 7. Editorial voice

Use:

- Calm confidence; specific, verifiable statements.
- Spiritual language supported by visible work.
- “The trust”, “our work”, and “the current project” consistently.
- “Seva” with a brief explanation on first use for general audiences.
- Direct CTAs: “Learn about the project”, “See how donations are used”, “Make an offering”.

Avoid:

- Grand claims such as “transforming lives” without evidence.
- Legal or tax assertions without approved documentation.
- Vague donor promises such as “every rupee changes everything”.
- Unverified names, dates, counts, institutional relationships, or impact figures.
- Switching between temple operations, children’s care, and the Gaushala project without explaining the relationship.

## 8. Required content model for each page

Each public page should be reviewed against this structure:

| Element | Requirement |
| --- | --- |
| Promise | One clear sentence describing the page’s value |
| Proof | Verified history, activity, people, numbers, or records |
| Present action | What the trust is doing now |
| Visitor action | One primary next step |
| Trust signal | Registration, receipts, reporting, contact, or other verified accountability |
| Metadata | Search description consistent with the page and approved narrative |

## 9. Core agent workflow

### Phase A — Discover

1. Inventory all user-facing copy in Svelte components, fallback modules, seeders, CMS records, campaign data, routes, metadata, and receipt/donation views.
2. Create a claim ledger: claim, source file, page/surface, status (`verified`, `needs confirmation`, `legacy conflict`, or `placeholder`).
3. Identify duplicated copy and determine whether each instance is canonical, fallback, generated, or obsolete.

### Phase B — Align

1. Obtain or confirm the approved organisational narrative.
2. Decide the single current fundraising purpose.
3. Resolve conflicts before polishing sentences.
4. Mark facts that need legal, trustee, tax, or finance approval.

### Phase C — Rewrite

1. Write the message hierarchy first: hero, one-sentence explanation, proof, current project, accountability, CTA.
2. Rewrite page by page in the voice above.
3. Prefer concrete language and short paragraphs.
4. Keep formal legal identity available, but use a readable short form in headings where appropriate.

### Phase D — Implement

1. Update the canonical CMS/seed source.
2. Update JS fallbacks from the same approved content model.
3. Remove or replace stale hard-coded strings.
4. Keep donation allocation, receipts, campaign state, and copy aligned.

### Phase E — Verify

1. Search for legacy phrases and contradictory claims.
2. Render and inspect homepage, About, campaign, donation, contact, footer, receipts, and metadata.
3. Check mobile line lengths, heading clarity, CTA visibility, and accessibility text.
4. Run the relevant type, build, and test checks.
5. Record unresolved factual questions rather than silently guessing.

## 10. Agent handoff format

Every copy agent should return:

- Surfaces reviewed.
- Claims found and their status.
- Contradictions discovered.
- Proposed copy changes with rationale.
- Facts requiring human approval.
- Files changed.
- Verification performed.

## 11. Immediate next work

1. Confirm which of the two narratives is authoritative. (Done — VSRSMS new narrative is canonical per the development contract.)
2. Build the claim ledger for all public copy. (Done — see the public copy state report that accompanies the rewrite.)
3. Reconcile `ProductionSeeder.php` with the approved fallback/CMS content. (Done — ABOUT_PAGE and sample campaign description updated; meta_description replaced.)
4. Rewrite homepage and About copy as the first controlled editorial pass. (Done — homepage-fallbacks.ts and about-fallbacks.ts are now aligned with the new narrative, and the page components that consume them have been updated.)
5. Audit donation and campaign language against actual payment/accounting behaviour. (Partial — donor-benefit and 80G language softened to the contract-blessed phrasing. A full audit against live payment/accounting behaviour is still pending and is the next material pass.)
6. Wire the public photo assets so the gradient placeholders disappear (asset pipeline work, not a copy pass).
7. Publish the "Legal & Compliance" page once the project owner has reviewed and approved the legal copy.
8. Publish the full board roster once the trust office has formally registered it.
9. Translate the new narrative to the languages the four primary audiences need (Kannada for the local audience, Hindi for the national audience, plus English for diaspora and international supporters). Not in scope for this pass.
