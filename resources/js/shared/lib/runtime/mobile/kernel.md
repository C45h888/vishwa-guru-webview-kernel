# Mobile Runtime Kernel (Frontend)

> One-screen navigation map for the **Mobile** runtime kernel.
> Mirrors the backend kernel doctrine defined in `agents.md`
> (§"Kernel.md Discipline") but scoped to browser-side runtime state
> for the public site's mobile UX.

Read order: Boundaries → Contracts → Providers → FSMs → Tests.

---

## Boundaries

**Owns**

- The mobile navigation menu FSM (closed / opening / open / closing).
- Reactive viewport flags (breakpoints, orientation, reduced-motion,
  coarse-pointer, hover-capability) for any JS consumer that needs them.
- Refcounted body-scroll-lock primitive for modal overlays.
- Refcounted focus-trap primitive (Tab cycle + restore-on-close).
- Inertia → mobile-menu auto-close wiring (`wireInertiaAutoClose`).

**Does NOT own**

- Visual design of the mobile menu (Pass M2 — animation, sheet polish,
  backdrop blur, hamburger morph).
- Admin overlay state — the admin kernel is a separate concern; if a
  future admin modal needs scroll-lock / focus-trap, it should call into
  the same primitives here (the contract is intentionally generic) but
  the menu FSM does not apply.
- Desktop hover affordances or desktop nav styling.
- iOS safe-area-inset **application** in CSS (M2 — once we know which
  chrome elements need to inset, e.g. sticky header under status bar).
- Touch gestures (swipe-to-close, pull-to-refresh) — M3+.

**Outbound edges**

- Into the `Shared` kernel for **no** contracts — the mobile kernel does
  not depend on any other frontend kernel. It is purely browser-API
  driven. If it later needs a server-side flag (e.g. "disable animations
  for low-bandwidth"), that contract lands here.
- Into the **Inertia runtime** (from `@inertiajs/svelte`) for the global
  `start` event. This is the only third-party boundary.

---

## Contracts

The public surface is `resources/js/shared/lib/runtime/mobile/index.ts`.
Components MUST import from there only.

| Export | Kind | Notes |
|---|---|---|
| `mobileMenu` | reactive singleton | Rune-backed FSM. See FSMs below. |
| `wireInertiaAutoClose()` | side-effect installer | Call from `PublicLayout`'s top-level `$effect`; returns the unbind. |
| `viewport` | reactive singleton | `isSm/Md/Lg/Xl`, `isMobile/Tablet/Desktop`, `reducedMotion`, `coarsePointer`, `hoverNone`, `isPortrait`. |
| `acquireBodyScrollLock()` / `releaseBodyScrollLock()` | refcounted primitives | Pair in same lifecycle. |
| `trapFocus(container)` / `releaseFocusTrap()` | refcounted primitives | Restores prior focus on release. |
| `MenuState` | type | `'closed' \| 'opening' \| 'open' \| 'closing'`. |

---

## Providers

There is no `MobileServiceProvider` class — frontend kernels have no
DI container. The mount point is **`PublicLayout.svelte`**:

- `wireInertiaAutoClose()` is called once from a top-level `$effect` (runs
  on mount, returns the unbind function which Svelte calls on unmount).
- `viewport` is constructed at module-load time via `MediaQuery` from
  `svelte/reactivity`; no manual setup needed.
- `mobileMenu` is also constructed at module-load time as a Svelte 5
  `$state` class — no manual setup needed.

If a future pass introduces per-page kernel overrides (e.g. a custom
breakpoint for the donate flow), they land as additional modules under
this directory and are mounted from `PublicLayout.svelte` or from the
relevant page's `+page.svelte` if Inertia exposes one.

---

## FSMs

`MobileMenuKernel` — handwritten 4-state FSM, modeled after the backend
`FailureStateMachine` (`app/Runtime/Failure/StateMachines/`).

```
   closed  ──open()──►  opening  ──tick──►  open
                                     ◄────cancel()────┐
   open    ──close()──► closing  ──tick──►  closed
                                     ◄────cancel()────┘
```

- **Events:** `open()`, `close()`, `toggle()`, `forceClosed()` (escape
  hatch for navigation + viewport changes).
- **Tick:** microtask transition from `opening → open` and
  `closing → closed`. In M1 the tick is instantaneous; M2 will key it
  to the CSS transition duration so the animation has time to play.
- **External force-closes:**
  - Inertia `start` event → `mobileMenu.forceClosed()` via
    `wireInertiaAutoClose()`.
  - Viewport crosses ≥ md while menu is open → consumer's `$effect`
    in `SiteNav.svelte` calls `forceClosed()`.

No tests exist yet for the FSM. Verification is manual on the VPS via
the matrix in `docs/ui-passes/M1-mobile-kernel.md`. When a frontend
test runner is introduced, the unit tests live under
`tests/Unit/runtime/mobile/` (mirroring the backend layout).

---

## Tests

Manual on the VPS until a frontend test runner is wired:

- **Plan:** `docs/ui-passes/M1-mobile-kernel.md` — test matrix covering
  iOS Safari, Android Chrome, and DevTools mobile emulation.
- **Regression:** before each subsequent UI pass, re-run the M1 matrix
  to ensure the kernel primitives haven't drifted.

When a test runner is introduced (Vitest + jsdom), the planned
structure is:

```
tests/Unit/runtime/mobile/
  menu.test.ts          # FSM transitions + force-closed semantics
  viewport.test.ts      # matchMedia flags + SSR fallback
  overlays.test.ts      # refcounted scroll-lock + focus-trap
```
