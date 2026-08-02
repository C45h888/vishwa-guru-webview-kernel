<script lang="ts">
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import PublicMediaImage from '$shared/components/PublicMediaImage.svelte';
    import MandalaDecoration from '$shared/components/MandalaDecoration.svelte';
    import TrustBadgeRow from '$shared/components/TrustBadgeRow.svelte';
    import BottomCtaBand from '$shared/components/BottomCtaBand.svelte';
    import type { CmsPageProps } from './types';

    let { page, heroBanners, html, appName }: CmsPageProps = $props();
</script>

<svelte:head>
    <title>{page.title} — {appName}</title>
    {#if page.meta_description}
        <meta name="description" content={page.meta_description} />
    {/if}
</svelte:head>

<PublicLayout>
    <section class="relative overflow-hidden bg-background">
        <div
            class="pointer-events-none absolute -right-20 top-0 opacity-[0.10]"
            aria-hidden="true"
        >
            <MandalaDecoration size={320} tint="gold" />
        </div>

        <div class="container relative py-16 lg:py-24">
            <div class="mx-auto max-w-3xl space-y-5 text-center">
                <h1 class="font-serif text-4xl font-semibold lg:text-5xl">
                    {page.title}
                </h1>
                {#if page.meta_description}
                    <p class="text-base text-muted-foreground lg:text-lg">
                        {page.meta_description}
                    </p>
                {/if}
                <div class="pt-2">
                    <TrustBadgeRow />
                </div>
            </div>
        </div>
    </section>

    {#if heroBanners.length > 0}
        <section class="container pb-10 lg:pb-14">
            <div
                class="grid grid-cols-1 gap-4 {heroBanners.length >= 3
                    ? 'md:grid-cols-2 lg:grid-cols-3'
                    : heroBanners.length === 2
                      ? 'md:grid-cols-2'
                      : ''}"
            >
                {#each heroBanners as banner (banner.id)}
                    {#if banner.image}
                        <div class="overflow-hidden rounded-md border border-border/40">
                            <PublicMediaImage
                                media={banner.image}
                                alt={banner.title ?? ''}
                                class="aspect-[16/9] w-full object-cover"
                            />
                        </div>
                    {/if}
                {/each}
            </div>
        </section>
    {/if}

    {#if html && html.trim() !== ''}
        <section class="container py-12 lg:py-20">
            <div class="prose prose-stone mx-auto max-w-3xl prose-a:text-primary">
                {@html html}
            </div>
        </section>
    {:else}
        <section class="container py-12 lg:py-20">
            <div class="mx-auto max-w-3xl text-center">
                <p class="text-sm text-muted-foreground">
                    This page will be published soon.
                </p>
            </div>
        </section>
    {/if}

    <BottomCtaBand />
</PublicLayout>
