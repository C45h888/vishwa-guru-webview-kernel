<script lang="ts">
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import PublicMediaImage from '$shared/components/PublicMediaImage.svelte';
    import MandalaDecoration from '$shared/components/MandalaDecoration.svelte';
    import TrustBadgeRow from '$shared/components/TrustBadgeRow.svelte';
    import GradientPanel from '$shared/components/GradientPanel.svelte';
    import BottomCtaBand from '$shared/components/BottomCtaBand.svelte';
    import Lightbox from '$shared/components/Lightbox.svelte';
    import { Camera, ChevronLeft, ChevronRight, ArrowLeft } from 'lucide-svelte';
    import SeoHead from '$shared/components/SeoHead.svelte';
    import type {
        GalleryImageProps,
        GallerySummaryProps,
        AppPageProps,
    } from '$shared/lib/inertia';

    interface SiblingGallery {
        slug: string;
        title: string;
        image_count: number;
    }

    interface GalleryDetailProps extends GallerySummaryProps {
        description?: string | null;
        display_order?: number;
        metadata?: Record<string, unknown>;
        created_at?: string;
        images?: GalleryImageProps[];
    }

    let {
        gallery,
        siblings = [],
        appName,
        appUrl,
    }: AppPageProps<{
        gallery: GalleryDetailProps;
        siblings?: SiblingGallery[];
    }> = $props();

    // Exclude the cover image from the grid (it already appears in the hero)
    const allImages = $derived(gallery.images ?? []);
    const coverFileId = $derived(gallery.cover_image_file_id ?? null);
    const gridImages = $derived(
        allImages.filter((img) => img.file_asset_id !== coverFileId),
    );

    const heroImage = $derived(gallery.cover_image ?? null);
    const hasHeroImage = $derived(heroImage !== null);

    // Lightbox state
    let lightboxOpen = $state(false);
    let lightboxIndex = $state(0);

    function openLightbox(idx: number) {
        lightboxIndex = idx;
        lightboxOpen = true;
    }

    function closeLightbox() {
        lightboxOpen = false;
    }

    function navigateLightbox(newIdx: number) {
        lightboxIndex = newIdx;
    }

    function imageAlt(image: GalleryImageProps): string {
        return (
            image.alt_text ??
            image.title ??
            `Photo ${(image.display_order ?? 0) + 1}`
        );
    }

    // Prev/next gallery (cycle through siblings in display order)
    const currentIdx = $derived(
        siblings.findIndex((s) => s.slug === gallery.slug),
    );
    const prevGallery = $derived(
        currentIdx > 0 ? siblings[currentIdx - 1] : siblings[siblings.length - 1],
    );
    const nextGallery = $derived(
        currentIdx >= 0 && currentIdx < siblings.length - 1
            ? siblings[currentIdx + 1]
            : siblings[0],
    );
</script>

<SeoHead
    title={gallery.title}
    {appName}
    {appUrl}
    description={gallery.short_description}
    image={heroImage?.url ?? null}
    imageAlt={heroImage?.alt_text ?? null}
/>

<PublicLayout>
    <!-- ═══ BREADCRUMBS ═══ -->
    <nav
        class="container pt-6 text-xs uppercase tracking-[0.18em] text-muted-foreground"
        aria-label="Breadcrumb"
    >
        <ol class="flex flex-wrap items-center gap-2">
            <li><a href="/" class="hover:text-primary">Home</a></li>
            <li aria-hidden="true">›</li>
            <li><a href="/gallery" class="hover:text-primary">Gallery</a></li>
            <li aria-hidden="true">›</li>
            <li class="text-foreground">{gallery.title}</li>
        </ol>
    </nav>

    <article>
        <!-- ═══ HERO ═══ -->
        <section class="relative overflow-hidden bg-background">
            <div
                class="pointer-events-none absolute -right-20 top-0 opacity-[0.08]"
                aria-hidden="true"
            >
                <MandalaDecoration size={320} tint="gold" />
            </div>

            <div class="container relative py-12 lg:py-20">
                <!-- Back nav -->
                <div class="mb-6">
                    <a
                        href="/gallery"
                        class="inline-flex items-center gap-2 text-sm text-muted-foreground transition hover:text-primary"
                    >
                        <ArrowLeft class="h-4 w-4" aria-hidden="true" />
                        <span>All galleries</span>
                    </a>
                </div>

                <div class="grid grid-cols-1 items-center gap-10 lg:grid-cols-12 lg:gap-16">
                    <div class="space-y-5 lg:col-span-7">
                        <p
                            class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                        >
                            {gallery.image_count} photo{gallery.image_count === 1 ? '' : 's'} · Moment
                        </p>
                        <h1
                            class="font-serif text-3xl font-semibold leading-tight lg:text-5xl"
                        >
                            {gallery.title}
                        </h1>
                        {#if gallery.short_description}
                            <p
                                class="max-w-xl text-base leading-relaxed text-muted-foreground lg:text-lg"
                            >
                                {gallery.short_description}
                            </p>
                        {/if}

                        <div
                            class="flex flex-wrap gap-2 pt-1 text-[10px] font-semibold uppercase tracking-[0.18em]"
                        >
                            <span
                                class="rounded-full border border-primary/30 bg-primary/5 px-2.5 py-1 text-primary"
                            >
                                {gallery.state}
                            </span>
                            <span
                                class="rounded-full border border-border bg-background px-2.5 py-1 text-foreground/70"
                            >
                                {gallery.image_count} photo{gallery.image_count === 1 ? '' : 's'}
                            </span>
                            {#if gallery.is_featured}
                                <span
                                    class="rounded-full bg-primary px-2.5 py-1 text-primary-foreground"
                                >
                                    Featured
                                </span>
                            {/if}
                        </div>

                        <div class="pt-1">
                            <TrustBadgeRow />
                        </div>
                    </div>

                    <div class="relative lg:col-span-5">
                        {#if hasHeroImage}
                            <button
                                type="button"
                                onclick={() => openLightbox(0)}
                                class="group block w-full overflow-hidden rounded-md border border-border/40"
                                aria-label="Open cover photo in viewer"
                            >
                                <PublicMediaImage
                                    media={heroImage!}
                                    alt={gallery.title}
                                    class="aspect-[4/5] w-full object-cover transition-transform duration-500 group-hover:scale-[1.04]"
                                />
                            </button>
                        {:else}
                            <GradientPanel
                                aspectRatio="portrait"
                                variant="ivory"
                            />
                        {/if}
                    </div>
                </div>
            </div>
        </section>

        <!-- ═══ DESCRIPTION ═══ -->
        {#if gallery.description}
            <section class="container py-20 lg:py-28">
                <div class="mx-auto max-w-3xl space-y-4">
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                    >
                        About this gallery
                    </p>
                    <h2
                        class="font-serif text-2xl font-semibold lg:text-3xl"
                    >
                        The story behind these photos
                    </h2>
                    <div
                        class="prose prose-stone max-w-none prose-a:text-primary"
                    >
                        <p>{gallery.description}</p>
                    </div>
                </div>
            </section>
        {/if}

        <!-- ═══ PHOTOS ═══ -->
        <section class="bg-ivory py-20 lg:py-28">
            <div class="container">
                <div class="mx-auto max-w-5xl space-y-8">
                    <div class="space-y-2">
                        <p
                            class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                        >
                            Photos
                        </p>
                        <h2
                            class="font-serif text-2xl font-semibold lg:text-3xl"
                        >
                            The collection
                        </h2>
                    </div>

                    {#if gridImages.length > 0}
                        <div
                            class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
                        >
                            {#each gridImages as image, i (image.id)}
                                <figure
                                    class="group overflow-hidden rounded-md border border-border/40 bg-background"
                                >
                                    <button
                                        type="button"
                                        onclick={() => openLightbox(i)}
                                        class="block aspect-square w-full cursor-zoom-in overflow-hidden bg-ivory"
                                        aria-label={`Open photo ${imageAlt(image)} in viewer`}
                                    >
                                        {#if image.image}
                                            <PublicMediaImage
                                                media={image.image}
                                                alt={imageAlt(image)}
                                                class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.04]"
                                            />
                                        {:else}
                                            <div
                                                class="flex h-full w-full items-center justify-center"
                                            >
                                                <Camera
                                                    class="h-8 w-8 text-primary/40"
                                                    aria-hidden="true"
                                                />
                                            </div>
                                        {/if}
                                    </button>
                                    {#if image.caption || image.photographer_credit || image.taken_at}
                                        <figcaption
                                            class="space-y-1 p-3 text-xs"
                                        >
                                            {#if image.caption}
                                                <p class="font-medium text-foreground/90">
                                                    {image.caption}
                                                </p>
                                            {/if}
                                            {#if image.photographer_credit}
                                                <p class="text-muted-foreground">
                                                    Photo: {image.photographer_credit}
                                                </p>
                                            {/if}
                                            {#if image.taken_at}
                                                <p class="text-muted-foreground/70">
                                                    Taken: {image.taken_at}
                                                </p>
                                            {/if}
                                        </figcaption>
                                    {/if}
                                </figure>
                            {/each}
                        </div>
                    {:else}
                        <div
                            class="rounded-md border border-dashed border-border bg-background p-12 text-center"
                        >
                            <Camera
                                class="mx-auto mb-3 h-8 w-8 text-primary/40"
                                aria-hidden="true"
                            />
                            <p class="text-sm text-muted-foreground">
                                No photos in this gallery yet.
                            </p>
                        </div>
                    {/if}
                </div>
            </div>
        </section>

        <!-- ═══ PREV/NEXT GALLERY ═══ -->
        {#if siblings.length > 1 && prevGallery && nextGallery}
            <section class="container py-16 lg:py-24">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <a
                        href={`/gallery/${prevGallery.slug}`}
                        class="group flex items-center justify-between gap-4 rounded-md border border-border/40 bg-background p-6 transition hover:border-primary hover:bg-primary/5"
                    >
                        <div class="flex items-center gap-3 text-muted-foreground transition group-hover:text-primary">
                            <ChevronLeft class="h-5 w-5" aria-hidden="true" />
                            <span class="text-xs uppercase tracking-[0.18em]">Previous</span>
                        </div>
                        <div class="text-right">
                            <p class="font-serif text-lg font-semibold text-foreground">
                                {prevGallery.title}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {prevGallery.image_count} photo{prevGallery.image_count === 1 ? '' : 's'}
                            </p>
                        </div>
                    </a>
                    <a
                        href={`/gallery/${nextGallery.slug}`}
                        class="group flex items-center justify-between gap-4 rounded-md border border-border/40 bg-background p-6 transition hover:border-primary hover:bg-primary/5"
                    >
                        <div class="text-left">
                            <p class="font-serif text-lg font-semibold text-foreground">
                                {nextGallery.title}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {nextGallery.image_count} photo{nextGallery.image_count === 1 ? '' : 's'}
                            </p>
                        </div>
                        <div class="flex items-center gap-3 text-muted-foreground transition group-hover:text-primary">
                            <span class="text-xs uppercase tracking-[0.18em]">Next</span>
                            <ChevronRight class="h-5 w-5" aria-hidden="true" />
                        </div>
                    </a>
                </div>
            </section>
        {/if}
    </article>

    <BottomCtaBand
        title="Support the schools and the campus"
        body="Sustain daily annadanam at the schools — or move the proposed healing and service campus one step closer. Every donation is acknowledged with an official receipt."
        ctaLabel="Donate Now"
        ctaHref="/donate"
    />

    <Lightbox
        images={gridImages}
        index={lightboxIndex}
        open={lightboxOpen}
        onClose={closeLightbox}
        onNavigate={navigateLightbox}
    />
</PublicLayout>