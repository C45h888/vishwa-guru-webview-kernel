<script lang="ts">
    /**
     * SiteNav — public site navigation (desktop + mobile).
     *
     * Doctrine (AGENTS.md §"Phase 4: Admin Kernel"):
     *   - The "Admin" link in the desktop + mobile nav routes to
     *     /login (which is wrapped by the `guest` middleware; signed-in
     *     admins get bounced straight to /admin).
     *   - We intentionally keep this link visible at all times — admins
     *     are temple staff who occasionally browse the public site and
     *     need a fast path back into the console. The link is visually
     *     subordinate to the public CTAs (Home, Campaigns, etc.) so it
     *     does not look like a public-facing login surface.
     *
     * Pass M1 refactor: SiteNav now consumes the mobile runtime kernel
     * for menu state, scroll-lock, focus-trap, and viewport-driven
     * branch selection. SiteHeader mounts SiteNav exactly once; the
     * previous duplication (one SiteNav per breakpoint, with the parent
     * toggling CSS visibility) is replaced with viewport-driven
     * `{#if}` branches inside SiteNav.
     */
    import { page } from '@inertiajs/svelte';
    import {
        mobileMenu,
        viewport,
        acquireBodyScrollLock,
        releaseBodyScrollLock,
        trapFocus,
        releaseFocusTrap,
        portal,
    } from '$shared/lib/runtime/mobile';

    const links = [
        { href: '/', label: 'Home' },
        { href: '/campaigns', label: 'Campaigns' },
        { href: '/gallery', label: 'Gallery' },
        { href: '/events', label: 'Events' },
        { href: '/about', label: 'About' },
        { href: '/contact', label: 'Contact' },
    ];

    const authUser = $derived($page.props.authUser);
    const isDesktop = $derived(viewport.isLg);

    /** Bound to the dialog root. Used by `trapFocus`. */
    let dialogEl: HTMLDivElement | undefined = $state();

    function isActive(href: string): boolean {
        if (typeof window === 'undefined') return false;
        const path = window.location.pathname.replace(/\/+$/, '') || '/';
        if (href === '/') return path === '/';
        return path === href || path.startsWith(href + '/');
    }

    // ── Effects bound to menu lifecycle ─────────────────────────────

    /**
     * Body-scroll-lock + focus-trap lifecycle is bound to menu visibility.
     * When the dialog becomes visible, acquire the lock; when it leaves
     * (closed via close button, Escape, route change, or viewport cross),
     * the cleanup fires.
     */
    $effect(() => {
        if (!mobileMenu.isVisible) return;
        acquireBodyScrollLock();
        return () => {
            releaseBodyScrollLock();
            releaseFocusTrap();
        };
    });

    /**
     * Install the focus trap once the dialog element is mounted and the
     * menu has reached the steady `open` state. Runs again on every menu
     * open; `releaseFocusTrap` is idempotent (see overlays.ts).
     */
    $effect(() => {
        if (mobileMenu.isOpen && dialogEl) {
            trapFocus(dialogEl);
        }
    });

    /**
     * Escape-to-close while the dialog is visible. Listener lives on
     * `document` because iOS Safari occasionally steals focus out of the
     * dialog into the address bar.
     */
    $effect(() => {
        if (!mobileMenu.isVisible) return;
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                e.preventDefault();
                mobileMenu.close();
            }
        };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    });

    /**
     * If the viewport crosses into the desktop range while the dialog is
     * still open (e.g. device rotation on a tablet, or browser window
     * resized past the lg breakpoint), hard-close so the user doesn't end
     * up with a fixed overlay covering the desktop nav.
     */
    $effect(() => {
        if (mobileMenu.isVisible && viewport.isLg) {
            mobileMenu.forceClosed();
        }
    });
</script>

<!-- Desktop nav + donate CTA (≥ lg). Rendered as a single cluster so the
     header can flex-align it next to the logo. -->
{#if isDesktop}
    <div class="hidden items-center gap-7 text-sm font-medium lg:flex">
        <ul class="flex items-center gap-7">
            {#each links as link (link.href)}
                <li>
                    <a
                        href={link.href}
                        class="border-b-2 pb-0.5 transition-colors {isActive(link.href)
                            ? 'border-primary text-foreground'
                            : 'border-transparent text-muted-foreground hover:text-foreground'}"
                    >
                        {link.label}
                    </a>
                </li>
            {/each}
            <li>
                {#if authUser}
                    <a
                        href="/admin"
                        class="border-b-2 border-transparent pb-0.5 text-muted-foreground transition-colors hover:border-primary hover:text-foreground"
                    >
                        Admin
                    </a>
                {:else}
                    <a
                        href="/login"
                        class="border-b-2 border-transparent pb-0.5 text-muted-foreground transition-colors hover:border-primary hover:text-foreground"
                    >
                        Sign in
                    </a>
                {/if}
            </li>
        </ul>
        <a
            href="/donate"
            class="inline-flex h-9 items-center justify-center rounded-sm bg-primary px-5 text-xs font-semibold uppercase tracking-[0.2em] text-primary-foreground transition-colors hover:bg-accent"
        >
            Donate
        </a>
    </div>
{/if}

<!-- Mobile hamburger trigger (< md). -->
{#if !isDesktop}
    <button
        type="button"
        class="lg:hidden inline-flex h-10 w-10 items-center justify-center rounded-sm border border-border/60 text-foreground transition-colors hover:border-primary hover:text-primary"
        aria-label={mobileMenu.isOpen ? 'Close menu' : 'Open menu'}
        aria-expanded={mobileMenu.isOpen}
        aria-controls="site-nav-dialog"
        onclick={() => mobileMenu.toggle()}
    >
        {#if mobileMenu.isOpen}
            <svg
                class="h-5 w-5"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <path d="M18 6 6 18M6 6l12 12" />
            </svg>
        {:else}
            <svg
                class="h-5 w-5"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <path d="M3 6h18M3 12h18M3 18h18" />
            </svg>
        {/if}
    </button>
{/if}

<!-- Mobile menu dialog. Renders whenever the menu is `opening`/`open`/`closing`
     so the close transition (M2) has a frame to play.

     IMPORTANT — `use:portal={'body'}`:
       The dialog uses `fixed inset-0` to cover the viewport. However, the
       SiteHeader's inner strip applies `backdrop-blur-md`, and any ancestor
       with `backdrop-filter` (or `transform`, `filter`, `perspective`,
       `contain: paint`) becomes the containing block for `position: fixed`
       descendants. Without the portal, the dialog would be clipped to the
       ~80px-tall header strip and the menu would be inaccessible. The
       portal moves the dialog to `document.body` so `fixed` positions
       against the viewport as authored. -->
{#if mobileMenu.isVisible}
    <div
        id="site-nav-dialog"
        bind:this={dialogEl}
        use:portal={'body'}
        class="fixed inset-0 z-50 flex flex-col bg-background"
        role="dialog"
        aria-modal="true"
        aria-label="Site navigation"
    >
        <div class="container flex h-20 items-center justify-end">
            <button
                type="button"
                class="text-xs font-semibold uppercase tracking-[0.25em] text-muted-foreground hover:text-primary"
                onclick={() => mobileMenu.close()}
                aria-label="Close menu"
            >
                Close
            </button>
        </div>
        <nav class="flex flex-1 flex-col items-center justify-center gap-8">
            {#each links as link (link.href)}
                <a
                    href={link.href}
                    onclick={() => mobileMenu.close()}
                    class="text-2xl font-semibold transition-colors {isActive(link.href)
                        ? 'text-primary'
                        : 'text-foreground hover:text-primary'}"
                >
                    {link.label}
                </a>
            {/each}
            {#if authUser}
                <a
                    href="/admin"
                    onclick={() => mobileMenu.close()}
                    class="text-2xl font-semibold text-foreground transition-colors hover:text-primary"
                >
                    Admin
                </a>
            {:else}
                <a
                    href="/login"
                    onclick={() => mobileMenu.close()}
                    class="text-2xl font-semibold text-foreground transition-colors hover:text-primary"
                >
                    Sign in
                </a>
            {/if}
            <a
                href="/donate"
                onclick={() => mobileMenu.close()}
                class="mt-6 inline-flex h-11 items-center justify-center rounded-sm bg-primary px-8 text-xs font-semibold uppercase tracking-[0.25em] text-primary-foreground"
            >
                Donate
            </a>
        </nav>
    </div>
{/if}
