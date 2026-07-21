<script lang="ts">
    import { Card, CardContent, CardHeader, CardTitle } from '$shared/ui/card';
    import PublicLayout from '$shared/components/PublicLayout.svelte';
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
</script>

<svelte:head>
    <title>{gallery.title} — {appName}</title>
    {#if gallery.short_description}
        <meta name="description" content={gallery.short_description} />
    {/if}
</svelte:head>

<PublicLayout>
    <article class="space-y-6">
        <header class="space-y-2">
            <h1 class="text-3xl font-semibold">{gallery.title}</h1>
            {#if gallery.short_description}
                <p class="text-sm text-muted-foreground">{gallery.short_description}</p>
            {/if}
            <div class="flex flex-wrap gap-2 text-xs uppercase tracking-wide">
                <span class="rounded-full bg-secondary px-2 py-0.5">{gallery.state}</span>
                <span class="rounded-full bg-secondary px-2 py-0.5">
                    {gallery.image_count} photo{gallery.image_count === 1 ? '' : 's'}
                </span>
                {#if gallery.is_featured}
                    <span class="rounded-full bg-primary px-2 py-0.5 text-primary-foreground">Featured</span>
                {/if}
            </div>
        </header>

        {#if gallery.description}
            <section aria-labelledby="description-title" class="prose max-w-none">
                <h2 id="description-title" class="text-xl font-semibold">About this gallery</h2>
                <p>{gallery.description}</p>
            </section>
        {/if}

        {#if images.length > 0}
            <section aria-labelledby="images-title" class="space-y-3">
                <h2 id="images-title" class="text-xl font-semibold">Photos</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {#each images as image (image.id)}
                        <Card>
                            <CardHeader>
                                <CardTitle class="text-base">
                                    {image.title ?? image.alt_text ?? `Photo ${image.display_order + 1}`}
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-1 text-sm">
                                {#if image.caption}
                                    <p>{image.caption}</p>
                                {/if}
                                {#if image.photographer_credit}
                                    <p class="text-xs text-muted-foreground">
                                        Photo: {image.photographer_credit}
                                    </p>
                                {/if}
                                {#if image.taken_at}
                                    <p class="text-xs text-muted-foreground">
                                        Taken: {image.taken_at}
                                    </p>
                                {/if}
                            </CardContent>
                        </Card>
                    {/each}
                </div>
            </section>
        {:else}
            <p class="text-sm text-muted-foreground">No photos in this gallery yet.</p>
        {/if}
    </article>
</PublicLayout>
