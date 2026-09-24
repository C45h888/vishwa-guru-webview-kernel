<script lang="ts">
    /**
     * SiteHeader — sticky top bar.
     *
     * Pass M1 refactor:
     *   - SiteNav is now mounted once. Its internal viewport-driven
     *     `{#if isDesktop}` branch decides whether to render the desktop
     *     nav + Donate CTA or the mobile hamburger trigger. The previous
     *     duplicate-SiteNav mounting pattern is gone.
     *   - The desktop "Donate" CTA was moved into SiteNav (it lives next
     *     to the desktop link cluster, which is a better visual grouping
     *     than the prior `SiteHeader` standalone button).
     */
    import SiteNav from './SiteNav.svelte';
    import MandalaDecoration from './MandalaDecoration.svelte';

    interface Props {
        appName?: string;
        appShortName?: string;
    }

    // Header chrome uses the short brand identifier (config('app.short_name'),
    // default 'VSRSMS') so the bar is legible at small breakpoints. The full
    // legal name remains available via appName for footers, meta tags, and
    // copy blocks that need the complete trust name.
    let { appShortName = 'VSRSMS' }: Props = $props();
</script>

<header class="sticky top-0 z-40">
    <div
        class="relative overflow-hidden border-b border-primary/30 bg-background/90 backdrop-blur-md"
    >
        <div
            class="pointer-events-none absolute -right-20 top-1/2 hidden -translate-y-1/2 opacity-[0.10] md:block"
            aria-hidden="true"
        >
            <MandalaDecoration size={320} tint="gold" />
        </div>

        <div class="container relative">
            <div class="flex h-20 items-center justify-between gap-6">
                <a href="/" class="flex items-center gap-3" aria-label={appShortName}>
                    <MandalaDecoration
                        size={56}
                        tint="gold"
                        class="shrink-0"
                    />
                    <span
                        class="whitespace-nowrap font-serif text-sm font-semibold leading-tight tracking-tight text-foreground lg:text-base"
                    >
                        {appShortName}
                    </span>
                </a>

                <!-- Single SiteNav mount. The component branches on viewport
                     internally (see SiteNav.svelte). -->
                <SiteNav />
            </div>
        </div>
    </div>
</header>
