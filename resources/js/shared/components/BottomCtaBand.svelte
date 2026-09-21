<script lang="ts">
    import { Button } from '$shared/ui/button';

    interface Props {
        title?: string;
        body?: string;
        ctaLabel?: string;
        ctaHref?: string;
    }

    let {
        title = 'Support the schools and the campus',
        body = 'Your contribution sustains daily annadanam — food, water, and school events for the children in our care — and moves the proposed healing and service campus one step closer. Every donation is acknowledged with an official receipt.',
        ctaLabel = 'Donate Now',
        ctaHref = '/donate',
    }: Props = $props();
</script>

<section class="bg-primary text-primary-foreground">
    <div class="container py-12 lg:py-16">
        <div class="mx-auto max-w-3xl space-y-4 text-center">
            <h2 class="font-serif text-3xl font-semibold lg:text-4xl">
                {title}
            </h2>
            <p class="text-base opacity-90 lg:text-lg">{body}</p>
            <div class="pt-2">
                <Button
                    href={ctaHref}
                    variant="secondary"
                    size="lg"
                    class="bg-primary-foreground text-primary hover:bg-primary-foreground/90"
                >
                    {ctaLabel}
                </Button>
            </div>
        </div>
    </div>
</section>