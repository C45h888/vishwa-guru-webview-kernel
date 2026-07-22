<script lang="ts">
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import PublicMediaImage from '$shared/components/PublicMediaImage.svelte';
    import MandalaDecoration from '$shared/components/MandalaDecoration.svelte';
    import TrustBadgeRow from '$shared/components/TrustBadgeRow.svelte';
    import BottomCtaBand from '$shared/components/BottomCtaBand.svelte';
    import { Separator } from '$shared/ui/separator';
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
            class="pointer-events-none absolute right-0 top-0 opacity-15"
            aria-hidden="true"
        >
            <MandalaDecoration size={180} tint="gold" />
        </div>

        <div class="container relative py-14 lg:py-20">
            <div class="mx-auto max-w-3xl space-y-5 text-center">
                <h1 class="font-serif text-4xl font-semibold lg:text-5xl">
                    {page.title}
                </h1>
                {#if page.meta_description}
                    <p class="text-base text-muted-foreground lg:text-lg">
                        {page.meta_description}
                    </p>
                {/if}
                <div class="pt-1">
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
                        <div class="overflow-hidden rounded-md border border-border/60">
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

    <Separator class="my-0" />

    {#if html && html.trim() !== ''}
        <section class="container py-12 lg:py-16">
            <div class="prose prose-stone mx-auto max-w-3xl">
                {@html html}
            </div>
        </section>
    {:else}
        <section class="container py-12 lg:py-16">
            <div class="mx-auto max-w-3xl text-center">
                <p class="text-sm text-muted-foreground">
                    This page will be published soon.
                </p>
            </div>
        </section>
    {/if}

    <BottomCtaBand />
</PublicLayout>