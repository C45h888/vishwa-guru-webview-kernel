/**
 * `portal` — Svelte action that moves its host element to a different
 * DOM container at mount time.
 *
 * Why this exists in the mobile kernel:
 *   Any ancestor with `transform`, `filter`, `backdrop-filter`,
 *   `perspective`, `contain: paint`, or `will-change: transform`
 *   becomes the *containing block* for `position: fixed` descendants.
 *   The SiteHeader's inner div uses `backdrop-blur-md` (a
 *   backdrop-filter), so a `fixed inset-0` dialog rendered inside
 *   SiteNav would be constrained to that ~80px-tall header strip
 *   instead of the viewport — making the mobile menu inaccessible.
 *
 *   Portaling the dialog to `document.body` lifts it out of every
 *   ancestor stacking/containing context, so `fixed` positions
 *   relative to the viewport as the author intended.
 *
 * Contract:
 *   - On mount, the node is appended to the resolved target (default
 *     `document.body`).
 *   - If the action's parameter changes, the node is re-parented.
 *   - On destroy, the node is detached from whatever parent it is in.
 *   - The element reference returned by `bind:this` is unchanged —
 *     portal only moves the node, it does not replace it.
 *   - SSR-safe: if `document` is undefined, the action is a no-op
 *     (the node stays where Svelte put it in the template tree).
 */

import type { Action } from 'svelte/action';

export type PortalTarget = string | HTMLElement | undefined;

function resolveTarget(target: PortalTarget): Element | null {
	if (typeof document === 'undefined') return null;
	if (target === undefined) return document.body;
	if (typeof target === 'string') {
		return document.querySelector(target);
	}
	return target;
}

export const portal: Action<HTMLElement, PortalTarget> = (node, initial) => {
	let current: Element | null = null;

	function move(to: PortalTarget) {
		const next = resolveTarget(to);
		if (!next) return;
		if (node.parentNode !== next) {
			next.appendChild(node);
		}
		current = next;
	}

	move(initial);

	return {
		update(next: PortalTarget) {
			move(next);
		},
		destroy() {
			// If Svelte already removed the node (e.g. via {#if}), the
			// parentNode is null and this is a no-op. Otherwise detach
			// so a portaled element never outlives its component.
			if (current && node.parentNode === current) {
				current.removeChild(node);
			}
			current = null;
		},
	};
};
