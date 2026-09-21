<script lang="ts">
    import PublicMediaImage from './PublicMediaImage.svelte';
    import PlaceholderCard from './PlaceholderCard.svelte';
    import MandalaDecoration from './MandalaDecoration.svelte';
    import { Camera } from 'lucide-svelte';
    import type { GallerySummaryProps } from '$shared/lib/inertia';

    /**
     * Larger showcase block — 4 featured galleries, square thumbnails,
     * mandala overlay fades in on hover. Replaces the older newsletter block.
     */

    interface Props {
        galleries: GallerySummaryProps[];
        limit?: number;
    }

    let { galleries, limit = 4 }: Props = $props();

    const visible = $derived(galleries.slice(0, limit));
    const hasGalleries = $derived(visible.length > 0);
</script>

<section class="relative overflow-hidden bg-background py-20 lg:py-28">
    <div
        class="pointer-events-none absolute -left-32 top-1/2 -translate-y-1/2 opacity-[0.05]"
        aria-hidden="true"
    >
        <MandalaDecoration size={420} tint="gold" />
    </div>

    <div class="container relative">
        <div class="mb-10 flex items-end justify-between gap-4">
            <div class="space-y-2">
                <p
                    class="text-xs font-semibold uppercase tracking-[0.2em] text-primary"
                >
                    Gallery
                </p>
                <h2 class="font-serif text-3xl font-semibold lg:text-4xl">
                    From the schools and the temple, in moments
                </h2>
                <p class="max-w-xl text-sm text-muted-foreground lg:text-base">
                    A glimpse into school life, daily annadanam, and the
                    community that sustains the trust.
                </p>
            </div>
            <a
                href="/gallery"
                class="hidden whitespace-nowrap text-sm font-medium text-primary hover:underline sm:inline"
            >
                View full gallery →
            </a>
        </div>

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4 lg:gap-6">
            {#if hasGalleries}
                {#each visible as gallery (gallery.id)}
                    <a
                        href={`/gallery/${gallery.slug}`}
                        class="group relative block aspect-square overflow-hidden rounded-md border border-border/40 bg-ivory"
                        aria-label={`View ${gallery.title}`}
                    >
                        {#if gallery.cover_image}
                            <PublicMediaImage
                                media={gallery.cover_image}
                                alt={gallery.title}
                                class="h-full w-full object-cover transition-transform duration-700 group-hover:scale-[1.05]"
                            />
                        {/if}
                        <div
                            class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/70 via-black/10 to-transparent opacity-0 transition-opacity duration-500 group-hover:opacity-100"
                            aria-hidden="true"
                        ></div>
                        <div
                            class="pointer-events-none absolute inset-0 flex items-center justify-center opacity-0 transition-opacity duration-500 group-hover:opacity-100"
                            aria-hidden="true"
                        >
                            <div class="opacity-30">
                                <MandalaDecoration size={160} tint="gold" />
                            </div>
                        </div>
                        <div
                            class="absolute inset-x-0 bottom-0 translate-y-2 px-4 pb-4 text-white opacity-0 transition-all duration-500 group-hover:translate-y-0 group-hover:opacity-100"
                        >
                            <p
                                class="font-serif text-sm font-semibold lg:text-base"
                            >
                                {gallery.title}
                            </p>
                            {#if gallery.image_count > 0}
                                <p class="text-xs text-white/80">
                                    {gallery.image_count} photos
                                </p>
                            {/if}
                        </div>
                    </a>
                {/each}
            {:else}
                {#each [1, 2, 3, 4] as slot (slot)}
                    <PlaceholderCard
                        icon={Camera}
                        title="Gallery placeholder"
                        hint="A gallery will appear here when published"
                        aspectRatio="square"
                    />
                {/each}
            {/if}
        </div>

        <div class="mt-8 text-center sm:hidden">
            <a
                href="/gallery"
                class="inline-flex text-sm font-medium text-primary hover:underline"
            >
                View full gallery →
            </a>
        </div>
    </div>
</section>
