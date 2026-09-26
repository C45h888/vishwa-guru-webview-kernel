/**
 * MobileMenuKernel — canonical owner of the public site's mobile
 * navigation menu state.
 *
 * Consumers MUST go through this kernel rather than maintaining their own
 * `isOpen` flag. Reasons:
 *   1. Single source of truth — multiple components (header, dialog,
 *      escape-handler, focus-trap, route-close hook) all observe the same
 *      state without re-implementing it.
 *   2. Inertia auto-close on navigation start — wired once via
 *      `wireInertiaAutoClose()` from `PublicLayout.svelte`.
 *   3. Force-close path (`forceClosed()`) for cases where the FSM's
 *      transient animation states would otherwise flash the menu on/off
 *      (e.g. mid-navigation, viewport resize across the md breakpoint).
 *
 * FSM:
 *
 *     closed ──open()──► opening ──tick──► open
 *                                     ◄──cancel()──┐
 *     open   ──close()──► closing ──tick──► closed
 *                                     ◄──cancel()──┐
 *
 * The transient `opening`/`closing` states exist so M2's animation pass
 * can target them with CSS without re-shaping the kernel API. In M1 the
 * tick is a microtask (effectively instant) — the FSM shape is forward-
 * compatible with non-zero transition durations.
 *
 * External events also force-close:
 *   - Inertia `start` (route navigation)  → `wireInertiaAutoClose()`
 *   - Viewport crosses ≥ md                 → consumer's `$effect`
 */

import { router } from '@inertiajs/svelte';

export type MenuState = 'closed' | 'opening' | 'open' | 'closing';

class MobileMenuKernel {
	state: MenuState = $state('closed');

	// ── Public read API ──────────────────────────────────────────────

	/** Steady-state open. Use for `aria-expanded` and CTA labels. */
	get isOpen(): boolean {
		return this.state === 'open';
	}

	/** Steady-state closed. Convenience inverse of `isOpen`. */
	get isClosed(): boolean {
		return this.state === 'closed';
	}

	/**
	 * True for `opening` | `open` | `closing`. Use as the conditional
	 * gate for rendering the dialog (so the close transition can play).
	 */
	get isVisible(): boolean {
		return this.state !== 'closed';
	}

	/** True while a transition is in flight. M2 animation hooks target this. */
	get isAnimating(): boolean {
		return this.state === 'opening' || this.state === 'closing';
	}

	// ── Actions ──────────────────────────────────────────────────────

	open(): void {
		if (this.state !== 'closed') return;
		this.state = 'opening';
		queueMicrotask(() => {
			if (this.state === 'opening') this.state = 'open';
		});
	}

	close(): void {
		if (this.state !== 'open') return;
		this.state = 'closing';
		queueMicrotask(() => {
			if (this.state === 'closing') this.state = 'closed';
		});
	}

	toggle(): void {
		if (this.isVisible) this.close();
		else this.open();
	}

	/**
	 * Hard-close without playing the transition. Use on navigation start
	 * and on viewport changes that cross the mobile/desktop boundary.
	 */
	forceClosed(): void {
		this.state = 'closed';
	}
}

/** Singleton — see module doctrine above. */
export const mobileMenu = new MobileMenuKernel();

/**
 * Wire `mobileMenu.forceClosed()` to Inertia's global `start` event
 * (fires when a navigation begins, before the response arrives).
 *
 * Returns an unbind function for symmetric cleanup. The intended call
 * site is `PublicLayout.svelte`'s `<script>`, inside a top-level `$effect`
 * that runs once on mount and tears down on layout unmount.
 */
export function wireInertiaAutoClose(): () => void {
	return router.on('start', () => mobileMenu.forceClosed());
}
