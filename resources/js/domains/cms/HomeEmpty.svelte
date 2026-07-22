<script lang="ts">
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import TrustBadgeRow from '$shared/components/TrustBadgeRow.svelte';
    import BottomCtaBand from '$shared/components/BottomCtaBand.svelte';
    import MandalaDecoration from '$shared/components/MandalaDecoration.svelte';
    import type { HomeEmptyProps } from './types';

    let { appName }: HomeEmptyProps = $props();
</script>

<svelte:head>
    <title>Welcome — {appName}</title>
</svelte:head>

<PublicLayout>
    <section class="relative overflow-hidden bg-background">
        <div
            class="pointer-events-none absolute inset-0 flex items-center justify-center opacity-25"
            aria-hidden="true"
        >
            <MandalaDecoration size={680} tint="gold" />
        </div>

        <div class="container relative py-24 lg:py-32">
            <div class="mx-auto max-w-3xl space-y-6 text-center">
                <h1 class="font-serif text-4xl font-semibold lg:text-6xl">
                    Welcome to {appName}
                </h1>
                <p class="text-base text-muted-foreground lg:text-lg">
                    Supporting daily pooja, annadanam, and temple maintenance
                    through seva.
                </p>
                <div class="pt-2">
                    <TrustBadgeRow />
                </div>
                <div
                    class="flex flex-wrap items-center justify-center gap-3 pt-2"
                >
                    <BottomCtaBand
                        title=""
                        body=""
                        ctaLabel="Donate Now"
                        ctaHref="/donate"
                    />
                </div>
            </div>
        </div>
    </section>

    <section class="bg-muted/30 py-12 lg:py-16">
        <div class="container text-center">
            <p class="mx-auto max-w-2xl text-sm text-muted-foreground">
                The site is being prepared. Campaign, event, and gallery pages
                will appear here as content is published.
            </p>
        </div>
    </section>
</PublicLayout>