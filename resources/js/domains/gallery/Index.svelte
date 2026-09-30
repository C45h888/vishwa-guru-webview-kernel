<script lang="ts">
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import PublicMediaImage from '$shared/components/PublicMediaImage.svelte';
    import MandalaDecoration from '$shared/components/MandalaDecoration.svelte';
    import TrustBadgeRow from '$shared/components/TrustBadgeRow.svelte';
    import BottomCtaBand from '$shared/components/BottomCtaBand.svelte';
    import { Button } from '$shared/ui/button';
    import { ArrowRight, Camera } from 'lucide-svelte';
    import SeoHead from '$shared/components/SeoHead.svelte';
    import type {
        GallerySummaryProps,
        PaginationProps,
        AppPageProps,
    } from '$shared/lib/inertia';

    let {
        galleries,
        pagination,
        appName,
        appUrl,
    }: AppPageProps<{
        galleries: GallerySummaryProps[];
        pagination: PaginationProps;
    }> = $props();

    const hasContent = $derived(galleries.length > 0);
</script>

<SeoHead
    title="Gallery"
    {appName}
    {appUrl}
    description="School life, daily annadanam, festivals, and community moments from the trust."
/>

<PublicLayout>
    <!-- ═══ INTRO HEAD ═══ -->
    <section class="relative overflow-hidden bg-background">
        <div
            class="pointer-events-none absolute -right-24 top-0 opacity-[0.08]"
            aria-hidden="true"
        >
            <MandalaDecoration size={420} tint="gold" />
        </div>
        <div
            class="pointer-events-none absolute -left-20 bottom-0 opacity-[0.06]"
            aria-hidden="true"
        >
            <MandalaDecoration size={260} tint="gold" />
        </div>

        <div class="container relative py-16 lg:py-24">
            <div class="mx-auto max-w-3xl space-y-5 text-center">
                <p
                    class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                >
                    From the schools and the temple
                </p>
                <h1
                    class="font-serif text-4xl font-semibold leading-tight lg:text-6xl"
                >
                    Gallery
                </h1>
                <p
                    class="text-base text-muted-foreground lg:text-lg"
                >
                    School life, daily annadanam, the festivals, and the
                    community that sustains the trust.
                </p>
                <div class="pt-2">
                    <TrustBadgeRow />
                </div>
            </div>
        </div>
    </section>

    <!-- ═══ THREE PILLARS ═══ -->
    {#if hasContent}
        <section class="container space-y-10 py-12 lg:space-y-14 lg:py-16">
            <div class="mx-auto max-w-3xl text-center">
                <p
                    class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                >
                    The three pillars
                </p>
                <h2
                    class="mt-3 font-serif text-2xl font-semibold lg:text-3xl"
                >
                    Choose a window
                </h2>
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-3 lg:gap-8">
                {#each galleries as gallery (gallery.id)}
                    <a
                        href={`/gallery/${gallery.slug}`}
                        class="group flex flex-col"
                        aria-label={`Open the ${gallery.title} gallery`}
                    >
                        <!-- Image card -->
                        <div
                            class="relative aspect-square w-full overflow-hidden rounded-md border border-border/40 bg-ivory"
                        >
                            {#if gallery.cover_image}
                                <PublicMediaImage
                                    media={gallery.cover_image}
                                    alt={gallery.title}
                                    class="h-full w-full object-cover transition-transform duration-700 group-hover:scale-[1.06]"
                                />
                            {:else}
                                <div
                                    class="flex h-full w-full items-center justify-center"
                                >
                                    <Camera
                                        class="h-10 w-10 text-primary/40"
                                        aria-hidden="true"
                                    />
                                </div>
                            {/if}

                            <!-- Photo count chip -->
                            <span
                                class="absolute right-3 top-3 rounded-full bg-black/60 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-white backdrop-blur"
                            >
                                {gallery.image_count} photo{gallery.image_count === 1 ? '' : 's'}
                            </span>
                        </div>

                        <!-- Text block -->
                        <div class="mt-5 space-y-3">
                            <p
                                class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                            >
                                {gallery.image_count} photo{gallery.image_count === 1 ? '' : 's'} · Moment
                            </p>
                            <h3
                                class="font-serif text-2xl font-semibold leading-tight lg:text-3xl"
                            >
                                {gallery.title}
                            </h3>
                            {#if gallery.short_description}
                                <p
                                    class="line-clamp-3 text-sm leading-relaxed text-muted-foreground"
                                >
                                    {gallery.short_description}
                                </p>
                            {/if}
                            <div
                                class="inline-flex items-center gap-2 pt-1 text-sm font-semibold text-primary transition group-hover:gap-3"
                            >
                                <span>Enter the gallery</span>
                                <ArrowRight
                                    class="h-4 w-4"
                                    aria-hidden="true"
                                />
                            </div>
                        </div>
                    </a>
                {/each}
            </div>
        </section>

        <!-- ═══ PAGINATION (only if there are more than one page) ═══ -->
        {#if pagination.has_more || pagination.page > 1}
            <nav
                class="container flex items-center justify-between pb-12"
                aria-label="Pagination"
            >
                <Button
                    variant="outline"
                    size="sm"
                    disabled={pagination.page <= 1}
                    href={pagination.page > 1 ? `?page=${pagination.page - 1}` : undefined}
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
                    href={pagination.has_more
                        ? `?page=${pagination.page + 1}`
                        : undefined}
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
        title="Support the schools and the campus"
        body="Sustain daily annadanam at the schools — or move the proposed healing and service campus one step closer. Every donation is acknowledged with an official receipt."
        ctaLabel="Donate Now"
        ctaHref="/donate"
    />
</PublicLayout>