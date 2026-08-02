<script lang="ts">
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import PublicMediaImage from '$shared/components/PublicMediaImage.svelte';
    import MandalaDecoration from '$shared/components/MandalaDecoration.svelte';
    import TrustBadgeRow from '$shared/components/TrustBadgeRow.svelte';
    import GradientPanel from '$shared/components/GradientPanel.svelte';
    import { Camera } from 'lucide-svelte';
    import type {
        GalleryImageProps,
        GallerySummaryProps,
        AppPageProps,
    } from '$shared/lib/inertia';

    interface GalleryDetailProps extends GallerySummaryProps {
        description?: string | null;
        display_order?: number;
        metadata?: Record<string, unknown>;
        created_at?: string;
        images?: GalleryImageProps[];
    }

    let {
        gallery,
        appName,
    }: AppPageProps<{ gallery: GalleryDetailProps }> = $props();

    const images = $derived(gallery.images ?? []);
    const heroImage = $derived(gallery.cover_image ?? null);
    const hasHeroImage = $derived(heroImage !== null);

    function imageAlt(image: GalleryImageProps): string {
        return (
            image.alt_text ??
            image.title ??
            `Photo ${(image.display_order ?? 0) + 1}`
        );
    }
</script>

<svelte:head>
    <title>{gallery.title} — {appName}</title>
    {#if gallery.short_description}
        <meta name="description" content={gallery.short_description} />
    {/if}
</svelte:head>

<PublicLayout>
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
                <div class="grid grid-cols-1 items-center gap-10 lg:grid-cols-12 lg:gap-16">
                    <div class="space-y-5 lg:col-span-7">
                        <p
                            class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                        >
                            Gallery
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
                            <div
                                class="overflow-hidden rounded-md border border-border/40"
                            >
                                <PublicMediaImage
                                    media={heroImage!}
                                    alt={gallery.title}
                                    class="aspect-[4/5] w-full object-cover"
                                />
                            </div>
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

                    {#if images.length > 0}
                        <div
                            class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
                        >
                            {#each images as image (image.id)}
                                <figure
                                    class="group overflow-hidden rounded-md border border-border/40 bg-background"
                                >
                                    <div
                                        class="aspect-square overflow-hidden bg-ivory"
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
                                    </div>
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
    </article>
</PublicLayout>
