/**
 * Mobile Runtime Kernel — public barrel.
 *
 * Components MUST import only from this barrel — internal files
 * (`menu.svelte.ts`, `viewport.svelte.ts`, `overlays.ts`) are
 * implementation details and may move.
 *
 * Kernel doctrine mirrors the backend kernel layout (see `kernel.md`):
 *   - Boundaries: see `kernel.md` §Boundaries.
 *   - Contracts: this file is the contract.
 *   - Providers: `PublicLayout.svelte` is the runtime mount point.
 *   - FSMs: `MobileMenuKernel` (closed/opening/open/closing).
 *   - Tests: `docs/ui-passes/M1-mobile-kernel.md` test matrix (manual
 *     on the VPS until a frontend test runner is wired).
 */

export { mobileMenu, wireInertiaAutoClose } from './menu.svelte';
export type { MenuState } from './menu.svelte';

export {
	acquireBodyScrollLock,
	releaseBodyScrollLock,
	trapFocus,
	releaseFocusTrap,
} from './overlays';

export { viewport } from './viewport.svelte';
