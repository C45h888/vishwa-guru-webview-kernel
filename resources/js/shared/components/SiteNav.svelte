<script lang="ts">
    const links = [
        { href: '/', label: 'Home' },
        { href: '/campaigns', label: 'Campaigns' },
        { href: '/gallery', label: 'Gallery' },
        { href: '/events', label: 'Events' },
        { href: '/about', label: 'About' },
        { href: '/contact', label: 'Contact' },
    ];

    let isMobileOpen = $state(false);

    function isActive(href: string): boolean {
        if (typeof window === 'undefined') return false;
        const path = window.location.pathname.replace(/\/+$/, '') || '/';
        if (href === '/') return path === '/';
        return path === href || path.startsWith(href + '/');
    }

    function toggleMobile() {
        isMobileOpen = !isMobileOpen;
    }

    function closeMobile() {
        isMobileOpen = false;
    }
</script>

<!-- Desktop nav (≥ md) -->
<ul class="hidden items-center gap-7 text-sm font-medium md:flex">
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
</ul>

<!-- Mobile trigger (≤ md) -->
<button
    type="button"
    class="md:hidden inline-flex h-10 w-10 items-center justify-center rounded-sm border border-border/60 text-foreground transition-colors hover:border-primary hover:text-primary"
    aria-label={isMobileOpen ? 'Close menu' : 'Open menu'}
    aria-expanded={isMobileOpen}
    onclick={toggleMobile}
>
    {#if isMobileOpen}
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

<!-- Mobile menu (≤ md). Full-screen overlay. -->
{#if isMobileOpen}
    <div
        class="fixed inset-0 z-50 flex flex-col bg-background md:hidden"
        role="dialog"
        aria-modal="true"
        aria-label="Site navigation"
    >
        <div class="container flex h-20 items-center justify-end">
            <button
                type="button"
                class="text-xs font-semibold uppercase tracking-[0.25em] text-muted-foreground hover:text-primary"
                onclick={closeMobile}
                aria-label="Close menu"
            >
                Close
            </button>
        </div>
        <nav class="flex flex-1 flex-col items-center justify-center gap-8">
            {#each links as link (link.href)}
                <a
                    href={link.href}
                    onclick={closeMobile}
                    class="text-2xl font-semibold transition-colors {isActive(link.href)
                        ? 'text-primary'
                        : 'text-foreground hover:text-primary'}"
                >
                    {link.label}
                </a>
            {/each}
            <a
                href="/donate"
                onclick={closeMobile}
                class="mt-6 inline-flex h-11 items-center justify-center rounded-sm bg-primary px-8 text-xs font-semibold uppercase tracking-[0.25em] text-primary-foreground"
            >
                Donate
            </a>
        </nav>
    </div>
{/if}
