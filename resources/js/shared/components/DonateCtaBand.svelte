<script lang="ts">
    import { Button } from '$shared/ui/button';
    import { ArrowRight } from 'lucide-svelte';
    import type { HomepageDonateCtaProps } from '$shared/lib/inertia';

    let { content }: { content: HomepageDonateCtaProps } = $props();
</script>

<section class="bg-primary text-primary-foreground">
    <div class="container py-14 lg:py-20">
        <div class="mx-auto max-w-3xl space-y-4 text-center">
            <p
                class="text-xs font-semibold uppercase tracking-[0.2em] text-primary-foreground/80"
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
                <Button
                    href={content.cta_url}
                    variant="secondary"
                    size="lg"
                    class="bg-primary-foreground text-primary hover:bg-primary-foreground/90"
                >
                    {content.cta_label}
                    <ArrowRight
                        class="ml-1.5 h-4 w-4"
                        aria-hidden="true"
                    />
                </Button>
            </div>
        </div>
    </div>
</section>
