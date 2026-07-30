<script lang="ts">
    import SiteNav from './SiteNav.svelte';
    import MandalaDecoration from './MandalaDecoration.svelte';
</script>

<header class="border-b border-border/30 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80">
    <div class="container flex h-20 items-center justify-between gap-6">
        <a href="/" class="flex items-center gap-3">
            <MandalaDecoration size={36} tint="gold" class="shrink-0" />
            <span class="font-serif text-xl font-semibold tracking-tight">
                Temple Trust
            </span>
        </a>

        <nav class="hidden md:block">
            <SiteNav />
        </nav>
    </div>
</header>