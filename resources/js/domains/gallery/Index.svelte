<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import GalleryCard from '$shared/components/GalleryCard.svelte';
    import MandalaDecoration from '$shared/components/MandalaDecoration.svelte';
    import TrustBadgeRow from '$shared/components/TrustBadgeRow.svelte';
    import BottomCtaBand from '$shared/components/BottomCtaBand.svelte';
    import { Button } from '$shared/ui/button';
    import { Badge } from '$shared/ui/badge';
    import { MessageCircle } from 'lucide-svelte';
    import type {
        GallerySummaryProps,
        PaginationProps,
        AppPageProps,
    } from '$shared/lib/inertia';

    let {
        galleries,
        pagination,
        appName,
    }: AppPageProps<{
        galleries: GallerySummaryProps[];
        pagination: PaginationProps;
    }> = $props();

    const featuredGalleries = $derived(
        galleries.filter((g) => g.is_featured),
    );
    const otherGalleries = $derived(
        galleries.filter((g) => !g.is_featured),
    );
    const hasContent = $derived(galleries.length > 0);

    function goToPage(page: number) {
        router.get('/gallery', { page }, { preserveScroll: true });
    }
</script>

<svelte:head>
    <title>Gallery — {appName}</title>
</svelte:head>

<PublicLayout>
    <!-- HERO -->
    <section class="relative overflow-hidden bg-background">
        <div
            class="pointer-events-none absolute right-0 top-0 opacity-15"
            aria-hidden="true"
        >
            <MandalaDecoration size={180} tint="gold" />
        </div>

        <div class="container relative py-14 lg:py-20">
            <div class="mx-auto max-w-3xl space-y-5 text-center">
                <h1 class="font-serif text-4xl font-semibold lg:text-5xl">
                    Gallery
                </h1>
                <p class="text-base text-muted-foreground lg:text-lg">
                    Daily darshan, festivals, and moments from the temple.
                </p>
                <div class="pt-1">
                    <TrustBadgeRow />
                </div>
                <div
                    class="flex flex-wrap items-center justify-center gap-3 pt-2"
                >
                    <Button href="/donate" size="lg">
                        Donate to support the temple
                    </Button>
                    <Button href="/campaigns" size="lg" variant="outline">
                        Browse campaigns
                    </Button>
                </div>
            </div>
        </div>
    </section>

    {#if hasContent}
        <!-- FEATURED STRIP -->
        {#if featuredGalleries.length > 0}
            <section class="container space-y-6 py-12 lg:py-16">
                <h2 class="font-serif text-3xl font-semibold lg:text-4xl">
                    Featured
                </h2>
                <div
                    class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3"
                >
                    {#each featuredGalleries as gallery (gallery.id)}
                        <GalleryCard
                            {gallery}
                            href={`/gallery/${gallery.slug}`}
                        />
                    {/each}
                </div>
            </section>
        {/if}

        <!-- ALL GALLERIES -->
        {#if otherGalleries.length > 0}
            <section class="container space-y-6 py-12 lg:py-16">
                <h2 class="font-serif text-3xl font-semibold lg:text-4xl">
                    All galleries
                    <span
                        class="ml-2 text-base font-normal text-muted-foreground"
                    >
                        {pagination.total}
                    </span>
                </h2>
                <div
                    class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3"
                >
                    {#each otherGalleries as gallery (gallery.id)}
                        <GalleryCard
                            {gallery}
                            href={`/gallery/${gallery.slug}`}
                        />
                    {/each}
                </div>
            </section>
        {/if}

        <!-- PAGINATION -->
        {#if pagination.has_more || pagination.page > 1}
            <nav
                class="container flex items-center justify-between pb-12"
                aria-label="Pagination"
            >
                <Button
                    variant="outline"
                    size="sm"
                    disabled={pagination.page <= 1}
                    onclick={() => goToPage(pagination.page - 1)}
                >
                    ← Previous
                </Button>
                <span class="text-sm text-muted-foreground">
                    Page {pagination.page}
                </span>
                <Button
                    variant="outline"
                    size="sm"
                    disabled={!pagination.has_more}
                    onclick={() => goToPage(pagination.page + 1)}
                >
                    Next →
                </Button>
            </nav>
        {/if}
    {:else}
        <section class="container py-16 lg:py-20">
            <div
                class="mx-auto max-w-xl rounded-md border border-dashed border-border bg-muted/30 p-8 text-center"
            >
                <p class="text-sm text-muted-foreground">
                    No galleries yet. The site is being prepared.
                </p>
            </div>
        </section>
    {/if}

    <!-- WHATSAPP SOCIAL CTA (Instagram + YouTube placeholders dropped until Phase 4) -->
    <section class="bg-muted/30 py-12 lg:py-16">
        <div class="container">
            <div class="mx-auto max-w-2xl space-y-4 text-center">
                <h2 class="font-serif text-3xl font-semibold lg:text-4xl">
                    Follow our daily darshan
                </h2>
                <p class="text-base text-muted-foreground">
                    Stay connected with daily darshan and temple moments.
                </p>
                <div class="flex justify-center pt-2">
                    <Badge variant="secondary" class="gap-2 py-2 px-4 text-sm">
                        <MessageCircle class="h-4 w-4" aria-hidden="true" />
                        <span>WhatsApp Channel · coming soon</span>
                    </Badge>
                </div>
                <p class="text-xs text-muted-foreground">
                    WhatsApp handle will be linked once the official channel
                    is verified (Phase 4).
                </p>
            </div>
        </div>
    </section>

    <BottomCtaBand
        title="Support our work"
        body="Your contributions make every darshan, festival, and seva possible."
        ctaLabel="Donate Now"
        ctaHref="/donate"
    />
</PublicLayout>