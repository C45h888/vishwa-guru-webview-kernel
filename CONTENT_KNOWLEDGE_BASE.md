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

## 6. Initial copy diagnosis

### Message clarity

- The organisation name is extremely long and dominates the hero. It is appropriate as the formal legal name, but not as the primary emotional headline on every surface.
- The hero explains the current ask, but the visitor must work to understand the relationship between the trust’s historic work, the current project, and the donation.
- “Second chapter” is a useful organising idea, but it needs a short proof point immediately beside it.

### Credibility and proof

- Several claims are specific but unsupported in the copy: 18 years, multiple establishments, children supported, power of attorney, registered status, and exact programme history.
- The About page currently uses placeholder-like values such as “—”, “Multiple”, and generic trustee roles. These weaken confidence and should be replaced with verified numbers or removed.
- Tax language must be precise. “Tax-deductibility status will be confirmed at the time of each donation” is not a substitute for stating the actual legal position where known.
- The site should distinguish clearly between completed work, ongoing work, planned work, and fundraising targets.

### Tone and readability

- The voice is thoughtful but often over-compressed and abstract: “operating doctrine”, “the visible primary actor”, “care that continues across years”, and similar phrases sound authored rather than immediately understood.
- Long paragraphs carry several ideas at once. Public-facing sections should use shorter paragraphs, concrete verbs, and one primary idea per block.
- “Sacred”, “devotion”, “seva”, “dharma”, and “care” are meaningful brand terms, but repeated use without concrete detail can make the copy feel ornamental.
- “The children are raised, not processed” is memorable but risks sounding adversarial or making a claim about other institutions. Prefer affirmative language focused on the trust’s practice.

### Conversion and donor experience

- Donation CTAs are clear but the surrounding copy needs stronger specificity: what is being acquired, what stage the project is in, what a contribution supports, and how accountability works.
- “Every donation through this site goes to this work” is a high-stakes allocation claim and must match payment, campaign, receipt, and accounting behaviour.
- Campaign copy and homepage copy currently disagree about whether donations support temple operations or the land acquisition project.

### Information architecture

- Navigation labels are conventional, but the content hierarchy should make the current campaign discoverable from the homepage, About page, and donation flow.
- Empty social links and placeholder gallery/event language should not appear as if they are active public destinations.
- Metadata should describe the actual approved narrative, not the legacy temple-operations story.

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

1. Confirm which of the two narratives is authoritative.
2. Build the claim ledger for all public copy.
3. Reconcile `ProductionSeeder.php` with the approved fallback/CMS content.
4. Rewrite homepage and About copy as the first controlled editorial pass.
5. Audit donation and campaign language against actual payment/accounting behaviour.
