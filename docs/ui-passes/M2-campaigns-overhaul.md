# Pass M2 — Campaigns `Show.svelte` overhaul

**Status:** Ready for VPS validation
**Branch:** `main`
**Date:** 2026-09-26
**Scope:** Structural layout overhaul of `/campaigns/{slug}`. Same data, same aesthetic, new flow. No backend changes.

---

## What changed

### New file structure (6 sections, was 10)

| # | Section | Component | Note |
|---|---|---|---|
| 1 | Hero | `CampaignHero.svelte` (new) | Full-viewport image with overlay; title + meta + donate CTA |
| 2 | About | inline in `Show.svelte` | Eyebrow + headline + prose; "Why it matters" folded in as the lead paragraph |
| 3 | Campaign details | inline in `Show.svelte` | Progress bar + target + status (raised amount, donor count) |
| 4 | Donate CTA | inline in `Show.svelte` | Single prominent CTA + receipt trust pill |
| 5 | Seva pill | `SevaPill.svelte` (new) | 3 connected steps with a saffron thread |
| 6 | Supports row | `SupportsRow.svelte` (new) | 4 icon items, replaces the banner image |

Quiet footer preserved: `Related campaigns`, `FAQ` (compacted), `Share` (compacted).

### What was removed

- "Why it matters" as a separate section — folded into About as the lead
- The 21:9 banner image for "Where every rupee goes" — replaced by an icon row
- The "Contribute" CTA panel (4th donate button) — collapsed into the single CTA
- Repeated "Donate now" CTAs at the bottom of each section — now appears once

### Aesthetic continuity (per @frontend-design)

| Token | Hex | Role |
|---|---|---|
| ivory | `#F4EEDC` | page background |
| rice | `#FAF6EC` | card / pill surface |
| saffron | `#E8743B` | primary CTA, eyebrow, brand |
| bronze | `#B45F2A` | hover, deep accent |
| bark | `#3A2E1F` | body text |
| mute | `#8A7E6A` | muted captions |

No new colours, no new fonts. All 6 tokens already in the design system.

### The signature element: the Seva Pill

A horizontal 3-step explainer that reads as a single connected object, not 3 cards:

```
[ 01 ─── 02 ─── 03 ]
  │       │       │
 choose  dedicate  receive
```

Step numbers are oversized and pale-saffron (`text-primary/30`). A thin saffron thread (`bg-primary/30`, 1.5px) connects them. On mobile the pill rotates to a vertical layout with a vertical thread.

### New shared components

| File | Path | Reusable by |
|---|---|---|
| `CampaignHero.svelte` | `resources/js/shared/components/` | future campaign-like surfaces |
| `SevaPill.svelte` | `resources/js/shared/components/` | any "process" explainer |
| `SupportsRow.svelte` | `resources/js/shared/components/` | any "what your money does" list |

---

## Test matrix

### Functional (desktop, 1440×900)

| # | Check |
|---|---|
| 1 | `/campaigns/{slug}` loads without console errors |
| 2 | Hero image fills viewport (100vh); no scroll needed to see it |
| 3 | Hero overlay: title, eyebrow, category pill, "Featured" pill (if applicable), state pill, date pill |
| 4 | Hero CTA "Donate to this cause" routes to `/donate?campaign={slug}` |
| 5 | Hero "All causes" routes to `/campaigns` |
| 6 | Trust badge row visible in the hero (Razorpay Secure, location) |
| 7 | About section: eyebrow, headline, prose, "Why it matters" lead paragraph |
| 8 | Campaign details: progress bar, target amount, raised amount, status |
| 9 | Donate CTA: single prominent button + receipt trust pill beside it |
| 10 | Seva pill: 3 steps, thread visible between numbers, no broken layout |
| 11 | Supports row: 4 icon items, each with an icon + label |
| 12 | FAQ accordion: expand/collapse works |
| 13 | Share: copy link works, WhatsApp link opens, mailto opens |
| 14 | Related campaigns: 3 cards at the bottom (when present) |

### Functional (mobile, 390×844)

| # | Check |
|---|---|
| 15 | Hero image still fills viewport |
| 16 | Hero overlay text legible (contrast against image) |
| 17 | Seva pill stacks vertically with vertical thread |
| 18 | Supports row is 2×2 grid |
| 19 | Hamburger menu still works (M1) |
| 20 | Body scroll-locks when mobile menu is open |
| 21 | All tap targets ≥ 44px |

### Admin

| # | Check |
|---|---|
| 22 | Logged in as admin: pencil overlay on hero, links to `/admin/campaigns/{id}/edit` |

### Reduced motion

| # | Check |
|---|---|
| 23 | No animations triggered (M1 has no animation; M2 will add) |
| 24 | Page fully usable with `prefers-reduced-motion: reduce` enabled |

### Visual

| # | Check |
|---|---|
| 25 | Desktop screenshot at 1440×900 |
| 26 | Mobile screenshot at 390×844 |
| 27 | Hero image not pixelated (object-fit: cover) |
| 28 | Spacing consistent with rest of site (24px / 16px / 12px rhythm) |

---

## Out of scope (deferred)

- No animations / motion polish (next pass — uses the M1 viewport kernel's `reducedMotion` flag)
- No copy changes — existing copy is the right copy, only the order changes
- No change to donate form, payment flow, or admin editor
- No change to other domains (Home, Events, Gallery)

---

## Rollback

`git revert` the merge commit on main, or:
```bash
ssh vps-lain "cd ~/webview && git pull origin main && npm run build && docker compose restart app worker"
```
