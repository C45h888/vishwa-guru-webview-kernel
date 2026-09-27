<script lang="ts">
    import { ChevronLeft, ChevronRight } from 'lucide-svelte';
    import type { EventSlide } from '$domains/events/event-slides';

    interface Props {
        slides: EventSlide[];
    }

    let { slides }: Props = $props();

    const hasSlides = $derived(slides.length > 0);

    let currentSlide = $state(0);
    let isPaused = $state(false);
    let prefersReducedMotion = $state(false);

    if (typeof window !== 'undefined') {
        const mql = window.matchMedia('(prefers-reduced-motion: reduce)');
        prefersReducedMotion = mql.matches;
        mql.addEventListener('change', (e) => {
            prefersReducedMotion = e.matches;
        });
    }

    $effect(() => {
        if (isPaused || prefersReducedMotion || !hasSlides || slides.length <= 1) return;
        const timer = setInterval(() => {
            currentSlide = (currentSlide + 1) % slides.length;
        }, 7000);
        return () => clearInterval(timer);
    });

    function next() {
        currentSlide = (currentSlide + 1) % slides.length;
    }

    function prev() {
        currentSlide = (currentSlide - 1 + slides.length) % slides.length;
    }

    function goTo(index: number) {
        currentSlide = index;
    }

    const current = $derived(slides[currentSlide]);
</script>

<!--
    Container uses aspect-ratio (not min-h-[80vh]) so the image fills a
    predictable shape at every viewport. The previous 80vh policy combined
    with portrait images under object-cover produced aggressive mobile
    cropping; landscape-only picks plus aspect-ratio lock fix that.

    aspect-[4/3]   — phone portrait, comfortable vertical space
    sm:aspect-[16/10] — phone landscape / small tablet
    md:aspect-[16/8] — desktop, wide cinematic
    2xl:aspect-[16/7] — extra-wide displays, deeper cinematic
-->
<section
    class="group relative isolate overflow-hidden border-b border-border/30 bg-ivory aspect-[4/3] sm:aspect-[16/10] md:aspect-[16/8] 2xl:aspect-[16/7]"
    onmouseenter={() => (isPaused = true)}
    onmouseleave={() => (isPaused = false)}
    onfocusin={() => (isPaused = true)}
    onfocusout={() => (isPaused = false)}
    aria-label="Temple events slideshow"
>
    <div class="absolute inset-0 -z-10">
        {#if hasSlides}
            {#key currentSlide}
                <div class="absolute inset-0 animate-fade-slow">
                    <img
                        src={current.src}
                        alt={current.alt}
                        class="h-full w-full object-cover object-center"
                        loading={currentSlide === 0 ? 'eager' : 'lazy'}
                        decoding="async"
                    />
                </div>
            {/key}
            <!--
                Gradient softened from from-black/80 (very heavy) to
                from-black/65 via-black/25 to-transparent. Subject stays
                visible under the overlay; text contrast on white still OK.
            -->
            <div
                class="absolute inset-0 bg-gradient-to-t from-black/65 via-black/25 to-transparent"
                aria-hidden="true"
            ></div>
        {:else}
            <div
                class="absolute inset-0 bg-gradient-to-br from-primary/15 via-ivory to-primary/10"
                aria-hidden="true"
            ></div>
        {/if}
    </div>

    <!--
        Content layer sits above the image layer. h-full + flex-column
        centers the text block within the aspect-ratio container; no
        viewport-based min-height.
    -->
    <div class="relative flex h-full flex-col justify-center py-10 sm:py-14">
        <div
            class="container mx-auto max-w-3xl space-y-5 px-4 text-center sm:space-y-6 sm:px-6"
        >
            {#if hasSlides}
                <p
                    class="text-xs font-semibold uppercase tracking-[0.25em] text-white/80"
                >
                    {current.eyebrow}
                </p>
                <h1
                    class="font-serif text-3xl font-semibold tracking-tight text-white sm:text-4xl lg:text-6xl"
                >
                    {current.title}
                </h1>
                <p class="mx-auto max-w-2xl text-sm text-white/85 sm:text-base lg:text-lg">
                    {current.body}
                </p>
            {:else}
                <h1
                    class="font-serif text-4xl font-semibold tracking-tight text-foreground lg:text-6xl"
                >
                    Events
                </h1>
            {/if}
        </div>
    </div>

    {#if hasSlides && slides.length > 1}
        <div
            class="absolute bottom-4 left-1/2 flex -translate-x-1/2 items-center gap-2 sm:bottom-6"
        >
            {#each slides as _slide, index (index)}
                <button
                    type="button"
                    class="h-2 rounded-full transition-all {index ===
                    currentSlide
                        ? 'w-8 bg-white'
                        : 'w-2 bg-white/40 hover:bg-white/70'}"
                    aria-label="Go to slide {index + 1}"
                    onclick={() => goTo(index)}
                ></button>
            {/each}
        </div>

        <div
            class="absolute right-4 top-1/2 hidden -translate-y-1/2 items-center gap-2 md:flex"
        >
            <button
                type="button"
                class="flex h-10 w-10 items-center justify-center rounded-full border border-white/20 bg-background/80 text-foreground backdrop-blur transition-colors hover:bg-background"
                aria-label="Previous slide"
                onclick={prev}
            >
                <ChevronLeft class="h-5 w-5" />
            </button>
            <button
                type="button"
                class="flex h-10 w-10 items-center justify-center rounded-full border border-white/20 bg-background/80 text-foreground backdrop-blur transition-colors hover:bg-background"
                aria-label="Next slide"
                onclick={next}
            >
                <ChevronRight class="h-5 w-5" />
            </button>
        </div>
    {/if}
</section>

<style>
    @keyframes fade-slow {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    :global(.animate-fade-slow) {
        animation: fade-slow 800ms ease-in-out both;
    }

    @media (prefers-reduced-motion: reduce) {
        :global(.animate-fade-slow) {
            animation: none;
        }
    }
</style>
