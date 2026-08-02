<script lang="ts">
    import { onMount } from 'svelte';
    import { fade } from 'svelte/transition';
    import { ChevronLeft, ChevronRight, X } from 'lucide-svelte';
    import PublicMediaImage from './PublicMediaImage.svelte';
    import type { GalleryImageProps } from '$shared/lib/inertia';

    type Props = {
        images: GalleryImageProps[];
        index: number;
        open: boolean;
        onClose: () => void;
        onNavigate: (newIndex: number) => void;
    };

    let { images, index = $bindable(0), open, onClose, onNavigate }: Props = $props();

    const current = $derived(images[index] ?? null);
    const hasPrev = $derived(index > 0);
    const hasNext = $derived(index < images.length - 1);

    function prev() {
        if (hasPrev) {
            onNavigate(index - 1);
        }
    }

    function next() {
        if (hasNext) {
            onNavigate(index + 1);
        }
    }

    function onKey(e: KeyboardEvent) {
        if (!open) return;
        if (e.key === 'Escape') {
            e.preventDefault();
            onClose();
        } else if (e.key === 'ArrowLeft') {
            e.preventDefault();
            prev();
        } else if (e.key === 'ArrowRight') {
            e.preventDefault();
            next();
        }
    }

    onMount(() => {
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    });

    function backdropClick(e: MouseEvent) {
        if (e.target === e.currentTarget) {
            onClose();
        }
    }

    function imageAlt(image: GalleryImageProps | null): string {
        if (!image) return '';
        return (
            image.alt_text ??
            image.title ??
            `Photo ${(image.display_order ?? 0) + 1}`
        );
    }
</script>

{#if open && current}
    <div
        role="dialog"
        aria-modal="true"
        aria-label="Photo viewer"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4 backdrop-blur-sm"
        transition:fade={{ duration: 150 }}
        onclick={backdropClick}
    >
        <!-- Close button -->
        <button
            type="button"
            onclick={onClose}
            aria-label="Close photo viewer"
            class="absolute right-4 top-4 z-10 flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20"
        >
            <X class="h-5 w-5" aria-hidden="true" />
        </button>

        <!-- Prev button -->
        {#if hasPrev}
            <button
                type="button"
                onclick={prev}
                aria-label="Previous photo"
                class="absolute left-4 top-1/2 z-10 -translate-y-1/2 flex h-12 w-12 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20"
            >
                <ChevronLeft class="h-6 w-6" aria-hidden="true" />
            </button>
        {/if}

        <!-- Next button -->
        {#if hasNext}
            <button
                type="button"
                onclick={next}
                aria-label="Next photo"
                class="absolute right-4 top-1/2 z-10 -translate-y-1/2 flex h-12 w-12 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20"
            >
                <ChevronRight class="h-6 w-6" aria-hidden="true" />
            </button>
        {/if}

        <!-- Image -->
        <figure class="relative max-h-[85vh] max-w-[90vw]">
            {#if current.image}
                <PublicMediaImage
                    media={current.image}
                    alt={imageAlt(current)}
                    class="max-h-[85vh] max-w-[90vw] object-contain"
                />
            {/if}
            {#if current.alt_text || current.title}
                <figcaption
                    class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 to-transparent p-6 text-center"
                >
                    <p class="font-serif text-lg text-white">
                        {imageAlt(current)}
                    </p>
                    <p class="mt-1 text-xs uppercase tracking-[0.18em] text-white/60">
                        {index + 1} of {images.length}
                    </p>
                </figcaption>
            {/if}
        </figure>
    </div>
{/if}