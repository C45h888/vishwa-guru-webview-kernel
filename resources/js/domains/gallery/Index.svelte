<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import GalleryCard from '$shared/components/GalleryCard.svelte';
    import MandalaDecoration from '$shared/components/MandalaDecoration.svelte';
    import TrustBadgeRow from '$shared/components/TrustBadgeRow.svelte';
    import BottomCtaBand from '$shared/components/BottomCtaBand.svelte';
    import { Button } from '$shared/ui/button';
    import { Camera } from 'lucide-svelte';
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
    <section class="relative overflow-hidden bg-background">
        <div
            class="pointer-events-none absolute -right-20 top-0 opacity-[0.10]"
            aria-hidden="true"
        >
            <MandalaDecoration size={320} tint="gold" />
        </div>

        <div class="container relative py-16 lg:py-24">
            <div class="mx-auto max-w-3xl space-y-5 text-center">
                <p
                    class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                >
                    From the temple
                </p>
                <h1 class="font-serif text-4xl font-semibold lg:text-5xl">
                    Gallery
                </h1>
                <p class="text-base text-muted-foreground lg:text-lg">
                    Daily darshan, festivals, and moments from the temple.
                </p>
                <div class="pt-2">
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
        {#if featuredGalleries.length > 0}
            <section class="container space-y-6 py-12 lg:py-16">
                <div class="flex items-end justify-between gap-4">
                    <h2 class="font-serif text-3xl font-semibold lg:text-4xl">
                        Featured
                    </h2>
                </div>
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

        {#if otherGalleries.length > 0}
            <section class="container space-y-6 py-12 lg:py-16">
                <div class="flex items-end justify-between gap-4">
                    <h2 class="font-serif text-3xl font-semibold lg:text-4xl">
                        All galleries
                        <span
                            class="ml-2 text-base font-normal text-muted-foreground"
                        >
                            {pagination.total}
                        </span>
                    </h2>
                </div>
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
                class="mx-auto max-w-xl rounded-md border border-dashed border-border bg-ivory/60 p-8 text-center"
            >
                <Camera
                    class="mx-auto mb-3 h-8 w-8 text-primary/60"
                    aria-hidden="true"
                />
                <p class="text-sm text-muted-foreground">
                    No galleries yet. The site is being prepared.
                </p>
            </div>
        </section>
    {/if}

    <BottomCtaBand
        title="Support our work"
        body="Your contributions make every darshan, festival, and seva possible."
        ctaLabel="Donate Now"
        ctaHref="/donate"
    />
</PublicLayout>
