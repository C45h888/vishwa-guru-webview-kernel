<script lang="ts">
    import { ArrowRight, ChevronLeft, ChevronRight } from 'lucide-svelte';
    import { Button } from '$shared/ui/button';
    import PublicMediaImage from '$shared/components/PublicMediaImage.svelte';
    import { FALLBACK_HERO_TITLE, FALLBACK_HERO_SUBTITLE } from '$domains/cms/homepage-fallbacks';
    import type { PublicMediaProps } from '$shared/lib/inertia';
    import type { HeroBannerProps, StaticPageSummary } from '$domains/cms/types';

    interface Props {
        page: StaticPageSummary;
        heroBanners: HeroBannerProps[];
    }

    let { page, heroBanners }: Props = $props();

    const hasBanners = $derived(heroBanners.length > 0);

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
        if (isPaused || prefersReducedMotion || !hasBanners || heroBanners.length <= 1) return;
        const timer = setInterval(() => {
            currentSlide = (currentSlide + 1) % heroBanners.length;
        }, 7000);
        return () => clearInterval(timer);
    });

    function next() {
        currentSlide = (currentSlide + 1) % heroBanners.length;
    }

    function prev() {
        currentSlide =
            (currentSlide - 1 + heroBanners.length) % heroBanners.length;
    }

    function goTo(index: number) {
        currentSlide = index;
    }

    function decodeUnicodeEscapes(value: string): string {
        return value.replace(/\\u([0-9a-fA-F]{4})/g, (_match, code) =>
            String.fromCharCode(Number.parseInt(code, 16)),
        );
    }

    const currentBanner = $derived(heroBanners[currentSlide]);
    const slideTitle = $derived(
        decodeUnicodeEscapes(
            currentBanner?.title ?? page.title ?? FALLBACK_HERO_TITLE,
        ),
    );
    const slideSubtitle = $derived(
        decodeUnicodeEscapes(
            currentBanner?.subtitle ?? page.meta_description ?? FALLBACK_HERO_SUBTITLE,
        ),
    );
    const slideImage = $derived<PublicMediaProps | null>(
        currentBanner?.image ?? currentBanner?.mobile_image ?? null,
    );
    const hasImage = $derived(slideImage !== null);
</script>

<section
    class="group relative isolate overflow-hidden border-b border-border/30 bg-ivory"
    onmouseenter={() => (isPaused = true)}
    onmouseleave={() => (isPaused = false)}
    onfocusin={() => (isPaused = true)}
    onfocusout={() => (isPaused = false)}
    aria-label="Featured announcements"
>
    <div class="absolute inset-0 -z-10">
        {#if hasImage}
            {#key currentSlide}
                <div class="absolute inset-0 animate-fade-slow">
                    <PublicMediaImage
                        media={slideImage!}
                        alt={slideTitle}
                        loading="eager"
                        fetchpriority="high"
                        class="h-full w-full object-cover"
                    />
                </div>
            {/key}
            <div
                class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-black/20"
                aria-hidden="true"
            ></div>
        {:else}
            <div
                class="absolute inset-0 bg-gradient-to-br from-primary/15 via-ivory to-primary/10"
                aria-hidden="true"
            ></div>
        {/if}
    </div>

    <div class="relative min-h-[80vh]">
        <div
            class="container flex min-h-[80vh] flex-col justify-center py-20 lg:py-28"
        >
            <div class="mx-auto max-w-3xl space-y-6 text-center">
                <h1
                    class="font-serif text-4xl font-semibold tracking-tight lg:text-6xl {hasImage
                        ? 'text-white'
                        : 'text-foreground'}"
                >
                    {slideTitle}
                </h1>
                <p
                    class="mx-auto max-w-2xl text-base lg:text-lg {hasImage
                        ? 'text-white/85'
                        : 'text-muted-foreground'}"
                >
                    {slideSubtitle}
                </p>
                <div class="pt-2">
                    <Button
                        href="/campaigns"
                        size="lg"
                        variant={hasImage ? 'secondary' : 'default'}
                        class={hasImage
                            ? 'bg-white text-foreground hover:bg-white/90'
                            : ''}
                    >
                        See Our Campaigns
                        <ArrowRight
                            class="ml-1.5 h-4 w-4"
                            aria-hidden="true"
                        />
                    </Button>
                </div>
            </div>
        </div>

        {#if hasBanners && heroBanners.length > 1}
            <div
                class="absolute bottom-6 left-1/2 flex -translate-x-1/2 items-center gap-2"
            >
                {#each heroBanners as _banner, index (index)}
                    <button
                        type="button"
                        class="h-2 rounded-full transition-all {index ===
                        currentSlide
                            ? hasImage
                                ? 'w-8 bg-white'
                                : 'w-8 bg-primary'
                            : hasImage
                              ? 'w-2 bg-white/40 hover:bg-white/70'
                              : 'w-2 bg-foreground/30 hover:bg-foreground/60'}"
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
                    class="flex h-10 w-10 items-center justify-center rounded-full border border-current/20 bg-background/80 backdrop-blur transition-colors hover:bg-background {hasImage
                        ? 'text-white'
                        : 'text-foreground'}"
                    aria-label="Previous slide"
                    onclick={prev}
                >
                    <ChevronLeft class="h-5 w-5" />
                </button>
                <button
                    type="button"
                    class="flex h-10 w-10 items-center justify-center rounded-full border border-current/20 bg-background/80 backdrop-blur transition-colors hover:bg-background {hasImage
                        ? 'text-white'
                        : 'text-foreground'}"
                    aria-label="Next slide"
                    onclick={next}
                >
                    <ChevronRight class="h-5 w-5" />
                </button>
            </div>
        {/if}
    </div>
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
