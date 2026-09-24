/**
 * ViewportKernel — reactive browser-side viewport / preference flags.
 *
 * Built on `MediaQuery` from `svelte/reactivity` (Svelte 5.7+). The class
 * wires a `matchMedia` listener under the hood and exposes a reactive
 * `.current` boolean — every consumer re-runs automatically when the match
 * state changes.
 *
 * Doctrine:
 *   - Tailwind breakpoints: sm 640 / md 768 / lg 1024 / xl 1280 / 2xl 1400.
 *     The flags here mirror those for use in JS (CSS still wins for layout).
 *   - Flags are SSR-safe: MediaQuery's `fallback` is honored when `window`
 *     is undefined, so the kernel can be imported at module scope.
 *   - Consumers MUST import `viewport` from this module — never instantiate
 *     a second MediaQuery for the same query (each instance re-registers
 *     a listener on the browser's MediaQueryList).
 *   - This is a singleton by design. M2 may add per-component viewport
 *     scoping (e.g. a custom breakpoint for a hero) but those MUST live in
 *     a sibling kernel under the same directory.
 */

import { MediaQuery } from 'svelte/reactivity';

class ViewportKernel {
	// min-width breakpoints. `_mql.current` is true when viewport ≥ breakpoint.
	#sm = new MediaQuery('(min-width: 640px)', false);
	#md = new MediaQuery('(min-width: 768px)', false);
	#lg = new MediaQuery('(min-width: 1024px)', false);
	#xl = new MediaQuery('(min-width: 1280px)', false);

	// preferences / capabilities
	#reducedMotion = new MediaQuery('(prefers-reduced-motion: reduce)', false);
	#coarsePointer = new MediaQuery('(pointer: coarse)', false);
	#hoverNone = new MediaQuery('(hover: none)', false);
	#portrait = new MediaQuery('(orientation: portrait)', true);

	get isSm(): boolean {
		return this.#sm.current;
	}
	get isMd(): boolean {
		return this.#md.current;
	}
	get isLg(): boolean {
		return this.#lg.current;
	}
	get isXl(): boolean {
		return this.#xl.current;
	}

	// Convenience composites — used by the public layout chrome and any
	// JS that needs a coarse mobile/tablet/desktop classification rather
	// than a single breakpoint test.
	get isMobile(): boolean {
		return !this.#md.current;
	} // < 768
	get isTablet(): boolean {
		return this.#md.current && !this.#lg.current;
	} // 768–1023
	get isDesktop(): boolean {
		return this.#lg.current;
	} // ≥ 1024

	get reducedMotion(): boolean {
		return this.#reducedMotion.current;
	}
	get coarsePointer(): boolean {
		return this.#coarsePointer.current;
	}
	get hoverNone(): boolean {
		return this.#hoverNone.current;
	}
	get isPortrait(): boolean {
		return this.#portrait.current;
	}
}

/**
 * Singleton — see module doctrine above. Components import this directly;
 * never re-instantiate.
 */
export const viewport = new ViewportKernel();
