<script lang="ts">
    import { Button } from '$shared/ui/button';
    import { ArrowRight } from 'lucide-svelte';
    import MandalaDecoration from './MandalaDecoration.svelte';
    import type { HomepageDonateCtaProps } from '$shared/lib/inertia';

    let { content }: { content: HomepageDonateCtaProps } = $props();
</script>

<section
    class="relative overflow-hidden bg-primary text-primary-foreground"
>
    <div
        class="pointer-events-none absolute -right-24 top-1/2 -translate-y-1/2 opacity-15"
        aria-hidden="true"
    >
        <MandalaDecoration size={420} tint="gold" />
    </div>

    <div
        class="pointer-events-none absolute inset-0 opacity-30"
        style="background: radial-gradient(circle at 80% 20%, hsl(25 95% 60% / 0.35), transparent 55%), radial-gradient(circle at 15% 85%, hsl(25 85% 30% / 0.45), transparent 55%);"
        aria-hidden="true"
    ></div>

    <div class="container relative py-16 lg:py-24">
        <div class="mx-auto max-w-3xl space-y-5 text-center">
            <p
                class="text-xs font-semibold uppercase tracking-[0.25em] text-primary-foreground/80"
            >
                {content.eyebrow}
            </p>
            <h2 class="font-serif text-3xl font-semibold lg:text-4xl">
                {content.title}
            </h2>
            <p class="text-base opacity-90 lg:text-lg">
                {content.body}
            </p>
            <div class="pt-2">
                <a
                    href={content.cta_url}
                    class="inline-flex h-11 items-center justify-center rounded-sm bg-primary-foreground px-6 text-xs font-semibold uppercase tracking-[0.2em] text-primary transition-colors hover:bg-primary-foreground/90"
                >
                    {content.cta_label}
                    <ArrowRight class="ml-2 h-4 w-4" aria-hidden="true" />
                </a>
            </div>
        </div>
    </div>
</section>
