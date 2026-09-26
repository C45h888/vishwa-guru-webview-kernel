# Pass M1 — Mobile runtime kernel + mobile menu functional baseline

**Status:** Ready for VPS validation
**Branch:** `feat/auto-20260923-872bfbcf`
**Date:** 2026-09-24
**Scope:** Functional baseline only — no animation polish, no visual redesign.

---

## What shipped

### New files

| Path | Purpose |
|---|---|
| `resources/js/shared/lib/runtime/mobile/menu.svelte.ts` | `MobileMenuKernel` — closed/opening/open/closing FSM with `open/close/toggle/forceClosed`. `wireInertiaAutoClose()` bridges to Inertia's `start` event. |
| `resources/js/shared/lib/runtime/mobile/viewport.svelte.ts` | `viewport` — reactive breakpoint flags (sm/md/lg/xl), composites (mobile/tablet/desktop), preferences (reduced-motion, coarse-pointer, hover-none), orientation. Built on `svelte/reactivity`'s `MediaQuery`. |
| `resources/js/shared/lib/runtime/mobile/overlays.ts` | Refcounted `acquire/releaseBodyScrollLock` and `trap/releaseFocusFocus`. Scrollbar-width compensated on lock to prevent desktop layout shift. |
| `resources/js/shared/lib/runtime/mobile/index.ts` | Public barrel — components import only from here. |
| `resources/js/shared/lib/runtime/mobile/kernel.md` | Kernel landing page (mirrors backend `Kernel.md` discipline). |
| `docs/ui-passes/M1-mobile-kernel.md` | This file. |

### Edited files

| Path | Change |
|---|---|
| `resources/js/shared/components/SiteNav.svelte` | Drop local `$state`. Consume `mobileMenu` + `viewport`. Render desktop/mobile branches via `{#if isDesktop}`. Add Escape-to-close, body-scroll-lock lifecycle, focus-trap install, viewport-cross close. |
| `resources/js/shared/components/SiteHeader.svelte` | Single SiteNav mount (was duplicated). Desktop Donate CTA moved into SiteNav's desktop cluster. |
| `resources/js/shared/components/PublicLayout.svelte` | `wireInertiaAutoClose()` mounted via top-level `$effect` (browser-only, runs once). |

---

## Test matrix — manual on the VPS

For each combination: open the menu, exercise the matrix, then close.

### Test environments

1. **iOS Safari** — physical iPhone (iOS 17+) if available, else BrowserStack/Sauce.
2. **Android Chrome** — physical Pixel, else Android Studio emulator at 411×891.
3. **Desktop Chrome DevTools mobile emulation** — iPhone 12 Pro (390×844), Pixel 5 (393×851).
4. **Reduced-motion** — macOS System Settings → Accessibility → Display → Reduce motion ON, OR DevTools → Rendering → Emulate CSS media feature `prefers-reduced-motion: reduce`.
5. **Desktop keyboard only** — disconnect mouse, exercise with Tab/Shift+Tab/Escape.

### Functional checks (every environment)

| # | Action | Expected |
|---|---|---|
| 1 | Tap hamburger. | Dialog opens (full-screen overlay). `aria-expanded="true"`. Background no longer scrolls. Focus is on the first focusable element (the Close button). |
| 2 | While menu open, scroll the page with finger / wheel. | Background does not scroll. |
| 3 | Tap a navigation link (e.g. Campaigns). | Menu closes. Page navigates. Scroll position restored to top (or preserved per Inertia default). |
| 4 | With menu open, press Escape. | Menu closes. Focus returns to the hamburger trigger. Background scroll restored. |
| 5 | With menu open, tap the in-dialog "Close" button. | Same as Escape. |
| 6 | With menu open, tap outside the dialog (there should be no outside — the overlay covers full screen, but tap the dimmed chrome header bar). | Menu stays open (no outside-click dismissal by design — full-screen dialog). |
| 7 | With menu open, Tab repeatedly. | Focus cycles within the dialog: Close → Home → Campaigns → … → Donate → loops to Close. Never escapes to the header logo or footer. |
| 8 | With menu open, Shift+Tab from the Close button. | Focus moves to the last focusable element (Donate). |
| 9 | Open menu on mobile, rotate device to landscape (still < md). | Menu stays open. Layout reflows. Background still locked. |
| 10 | Open menu on mobile, then resize browser window past 768 px (DevTools or split-screen). | Menu force-closes immediately on the viewport cross. Desktop nav appears. |
| 11 | Navigate between pages (Inertia `start` fires). | Menu (if open) closes before the page transition. Background scroll restored. |
| 12 | Reduce-motion ON. Open menu, close menu. | Transitions are still instant (no flash, no jank). |
| 13 | On desktop (≥ md), no hamburger should be visible. | Desktop nav + Donate CTA visible. Resizing down to mobile makes hamburger appear. |
| 14 | On mobile, no desktop nav should be visible. | Hamburger visible. |
| 15 | Reduced-motion OFF. Open menu. | (M1) No animation — instant. (M2 will add slide + fade.) |

### Regression checks (other surfaces)

| # | Surface | Check |
|---|---|---|
| 16 | Home page (`/`) | Renders identically to pre-M1. No console errors. |
| 17 | Campaigns index (`/campaigns`) | Renders. Inertia navigation works (no full reload). |
| 18 | Gallery lightbox | Open lightbox. Tab cycles inside. Escape closes. Background does not scroll while open. (Lightbox still uses its own keydown; M2 may migrate it to kernel primitives.) |
| 19 | Donate flow (`/donate`) | Form renders. Razorpay script loads. Submission works. |
| 20 | Admin login (`/login`) | Renders. No menu visible (admin uses AdminLayout, not PublicLayout). |
| 21 | Footer | Renders correctly at all breakpoints. |

---

## Console-error gate

Open DevTools console. Test matrix above. **No errors and no warnings** (other than known third-party noise from Razorpay/Google Fonts). If any console error references `mobileMenu`, `viewport`, `acquireBodyScrollLock`, or `trapFocus` — file it as a P0 bug for this pass.

---

## Known limitations (deferred to later passes)

- **No animation polish** — dialog appears/disappears instantly. M2 will add slide-down + fade. The kernel FSM already has transient `opening/closing` states for this.
- **iOS safe-area-inset** — dialog covers the full screen including the home-indicator area on iPhones with notches. M2 will add `env(safe-area-inset-*)` padding to the dialog's bottom CTA.
- **Touch gestures** — no swipe-down-to-close. M3+.
- **Hydration flash on viewport switch** — desktop users may see a one-frame hamburger before MediaQuery hydrates. Acceptable for M1; M2 may add a pre-hydration CSS guard.
- **No frontend test runner yet** — all checks are manual. Vitest will land in a follow-up infra pass; the planned test layout is in `kernel.md` §Tests.

---

## Reporting back

For each failing check, please capture:

1. Environment (device + browser + version).
2. Repro steps.
3. Expected vs actual behavior.
4. Console errors / warnings (full text).
5. Screenshot or screen recording if visual.

Findings → I'll fold into M2 (animation + visual polish + safe-area).
