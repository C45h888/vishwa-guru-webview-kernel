/**
 * Overlay primitives — body-scroll-lock and focus-trap for modal overlays.
 *
 * Both are *refcounted* so concurrent overlays (e.g. lightbox opens on
 * top of mobile menu — possible if we add lightbox-to-fullscreen later)
 * do not double-lock or prematurely unlock.
 *
 * Consumers:
 *   - `SiteNav.svelte` (mobile menu)
 *   - `Lightbox.svelte` (gallery) — currently manages its own state;
 *     M2 may migrate it to use these primitives for consistency.
 *
 * Lock contract:
 *   - `acquire → release` must be paired within the same lifecycle.
 *   - `release` is a no-op when the count is already zero (defensive).
 *   - On the final release, the body's prior `overflow` and `paddingRight`
 *     are restored verbatim (including empty string).
 *   - On acquire, the scrollbar width is measured and compensated via
 *     `padding-right` so desktop layouts don't reflow when the scrollbar
 *     disappears.
 *
 * Focus-trap contract:
 *   - On `trapFocus(container)`, the previously focused element is
 *     remembered and focus moves into the container.
 *   - `Tab` and `Shift+Tab` cycle within focusable descendants.
 *   - On `releaseFocusTrap()`, focus is restored to the saved element.
 *   - Only one trap may be active at a time; calling `trapFocus` twice
 *     releases the first before installing the second.
 *   - Listens on `document`, not on the container — this keeps the trap
 *     working even if focus is briefly outside (e.g. browser address bar
 *     steals focus on iOS Safari).
 */

let lockCount = 0;
let savedOverflow = '';
let savedPaddingRight = '';

export function acquireBodyScrollLock(): void {
	if (typeof document === 'undefined') return;
	if (lockCount === 0) {
		savedOverflow = document.body.style.overflow;
		savedPaddingRight = document.body.style.paddingRight;
		const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
		document.body.style.overflow = 'hidden';
		if (scrollbarWidth > 0) {
			document.body.style.paddingRight = `${scrollbarWidth}px`;
		}
	}
	lockCount++;
}

export function releaseBodyScrollLock(): void {
	if (typeof document === 'undefined') return;
	if (lockCount === 0) return;
	lockCount--;
	if (lockCount === 0) {
		document.body.style.overflow = savedOverflow;
		document.body.style.paddingRight = savedPaddingRight;
		savedOverflow = '';
		savedPaddingRight = '';
	}
}

// ── Focus trap ───────────────────────────────────────────────────────

let trapContainer: HTMLElement | null = null;
let savedActiveElement: Element | null = null;
let trapKeyHandler: ((e: KeyboardEvent) => void) | null = null;

const FOCUSABLE_SELECTOR = [
	'a[href]',
	'area[href]',
	'button:not([disabled])',
	'input:not([disabled]):not([type="hidden"])',
	'select:not([disabled])',
	'textarea:not([disabled])',
	'iframe',
	'object',
	'audio[controls]',
	'video[controls]',
	'[contenteditable]:not([contenteditable="false"])',
	'[tabindex]:not([tabindex="-1"])',
].join(',');

function focusablesIn(container: HTMLElement): HTMLElement[] {
	return Array.from(
		container.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR),
	).filter((el) => !el.hasAttribute('disabled') && el.tabIndex !== -1);
}

export function trapFocus(container: HTMLElement): void {
	if (typeof document === 'undefined') return;
	if (trapContainer) releaseFocusTrap();
	trapContainer = container;
	savedActiveElement = document.activeElement;

	const items = focusablesIn(container);
	if (items.length > 0) {
		items[0].focus();
	} else {
		// No focusables — make the container itself focusable so Escape /
		// Tab dispatch from the dialog handler still routes correctly.
		container.tabIndex = -1;
		container.focus();
	}

	trapKeyHandler = (e: KeyboardEvent) => {
		if (e.key !== 'Tab' || !trapContainer) return;
		const live = focusablesIn(trapContainer);
		if (live.length === 0) {
			e.preventDefault();
			return;
		}
		const first = live[0];
		const last = live[live.length - 1];
		const active = document.activeElement as HTMLElement | null;
		if (e.shiftKey && (active === first || !trapContainer.contains(active))) {
			e.preventDefault();
			last.focus();
		} else if (!e.shiftKey && active === last) {
			e.preventDefault();
			first.focus();
		}
	};
	document.addEventListener('keydown', trapKeyHandler, true);
}

export function releaseFocusTrap(): void {
	if (typeof document === 'undefined') return;
	if (!trapContainer) return;
	if (trapKeyHandler) {
		document.removeEventListener('keydown', trapKeyHandler, true);
		trapKeyHandler = null;
	}
	trapContainer = null;
	if (savedActiveElement instanceof HTMLElement) {
		savedActiveElement.focus();
	} else if (savedActiveElement && 'focus' in (savedActiveElement as object)) {
		(savedActiveElement as HTMLElement).focus();
	}
	savedActiveElement = null;
}
