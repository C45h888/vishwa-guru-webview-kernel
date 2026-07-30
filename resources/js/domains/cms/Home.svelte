<script lang="ts">
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import HeroSlideshow from '$shared/components/HeroSlideshow.svelte';
    import QuoteSection from '$shared/components/QuoteSection.svelte';
    import PicturePanel from '$shared/components/PicturePanel.svelte';
    import TrustBadgeRow from '$shared/components/TrustBadgeRow.svelte';
    import TrustPanel from '$shared/components/TrustPanel.svelte';
    import EventsRow from '$shared/components/EventsRow.svelte';
    import DonateCtaBand from '$shared/components/DonateCtaBand.svelte';
    import MandalaDecoration from '$shared/components/MandalaDecoration.svelte';
    import CampaignCard from '$shared/components/CampaignCard.svelte';
    import GalleryCard from '$shared/components/GalleryCard.svelte';
    import PlaceholderCard from '$shared/components/PlaceholderCard.svelte';
    import PublicMediaImage from '$shared/components/PublicMediaImage.svelte';
    import { Button } from '$shared/ui/button';
    import { ArrowRight, Camera, Heart } from 'lucide-svelte';
    import { FALLBACK_HOMEPAGE_CONTENT } from './homepage-fallbacks';
    import type { HomePageProps } from './types';

    let {
        page,
        homepageContent,
        heroBanners,
        html,
        featuredCampaigns,
        featuredEvents,
        featuredGalleries,
        appName,
    }: HomePageProps = $props();

    const content = $derived(
        homepageContent === null ? FALLBACK_HOMEPAGE_CONTENT : homepageContent,
    );

    const hasCause = $derived(featuredCampaigns.length > 0);
    const hasGallery = $derived(featuredGalleries.length > 0);

    const storyCtaVisible = $derived(
        content.story.cta_label !== null
            && content.story.cta_url !== null
            && content.story.cta_label !== ''
            && content.story.cta_url !== '',
    );
</script>

<svelte:head>
    <title>{page.title} — {appName}</title>
    {#if page.meta_description}
        <meta name="description" content={page.meta_description} />
    {/if}
</svelte:head>

<PublicLayout>
    <!-- ═══ 1. HERO SLIDESHOW ═══ -->
    <HeroSlideshow {page} {heroBanners} />

    <!-- ═══ 2. TRUST STORY ═══ -->
    <section class="container py-20 lg:py-28">
        <div
            class="grid grid-cols-1 items-center gap-10 lg:grid-cols-12 lg:gap-16"
        >
            <div class="space-y-5 lg:col-span-7">
                <p
                    class="text-xs font-semibold uppercase tracking-[0.2em] text-primary"
                >
                    {content.story.eyebrow}
                </p>
                <h2
                    class="font-serif text-3xl font-semibold leading-tight lg:text-4xl"
                >
                    {content.story.title}
                </h2>
                <p
                    class="max-w-xl text-base leading-relaxed text-muted-foreground lg:text-lg"
                >
                    {content.story.body}
                </p>
                {#if storyCtaVisible}
                    <a
                        href={content.story.cta_url}
                        class="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline"
                    >
                        {content.story.cta_label}
                        <ArrowRight class="h-4 w-4" aria-hidden="true" />
                    </a>
                {/if}
            </div>
            <div class="relative lg:col-span-5">
                <div
                    class="pointer-events-none absolute -right-12 -top-12 opacity-15"
                    aria-hidden="true"
                >
                    <MandalaDecoration size={260} tint="gold" />
                </div>
                <div class="relative">
                    {#if content.story.image}
                        <div
                            class="overflow-hidden rounded-md border border-border/40"
                        >
                            <PublicMediaImage
                                media={content.story.image}
                                alt={content.story.alt_text ??
                                    content.story.title}
                                class="aspect-square w-full object-cover"
                            />
                        </div>
                    {:else}
                        <div
                            class="aspect-square overflow-hidden rounded-md border border-border/40 bg-gradient-to-br from-primary/15 via-ivory to-primary/10"
                        >
                            <div
                                class="flex h-full w-full items-center justify-center"
                            >
                                <div
                                    class="rounded-full border border-primary/30 bg-primary/5 px-3 py-1 text-xs font-medium text-primary/70"
                                >
                                    {content.story.eyebrow}
                                </div>
                            </div>
                            <div
                                class="pointer-events-none absolute inset-0 opacity-30"
                                style="background: radial-gradient(circle at 30% 20%, hsl(42 70% 48% / 0.08), transparent 50%), radial-gradient(circle at 70% 80%, hsl(42 70% 48% / 0.06), transparent 50%);"
                                aria-hidden="true"
                            ></div>
                        </div>
                    {/if}
                </div>
            </div>
        </div>
    </section>

    <!-- ═══ 3. MISSION QUOTE ═══ -->
    <QuoteSection
        eyebrow={content.mission_quote.eyebrow}
        quote={content.mission_quote.quote}
        attribution={content.mission_quote.attribution}
    />

    <!-- ═══ 4. PROGRAMS — 3 alternating beats ═══ -->
    <div class="bg-ivory">
        {#each content.programs as program, index (program.key)}
            <PicturePanel
                eyebrow={program.eyebrow}
                title={program.title}
                body={program.body}
                image={program.image}
                altText={program.alt_text}
                reverse={index % 2 === 1}
                variant={index % 2 === 1 ? 'warm' : 'gold'}
            />
        {/each}
    </div>

    <!-- ═══ 5. CAMPAIGNS CTA + GRID ═══ -->
    <section class="bg-muted/30 py-20 lg:py-28">
        <div class="container space-y-10">
            <div class="mx-auto max-w-2xl space-y-4 text-center">
                <p
                    class="text-xs font-semibold uppercase tracking-[0.2em] text-primary"
                >
                    Campaigns
                </p>
                <h2 class="font-serif text-3xl font-semibold lg:text-4xl">
                    What we're doing today
                </h2>
                <p class="text-base text-muted-foreground">
                    Active causes the trust is currently sustaining. Every
                    donation goes directly to temple operations.
                </p>
                <div class="pt-1">
                    <Button
                        href="/campaigns"
                        variant="outline"
                        size="sm"
                    >
                        See all campaigns
                        <ArrowRight
                            class="ml-1.5 h-4 w-4"
                            aria-hidden="true"
                        />
                    </Button>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                {#if hasCause}
                    {#each featuredCampaigns.slice(0, 3) as campaign (campaign.id)}
                        <CampaignCard
                            {campaign}
                            href={`/campaigns/${campaign.slug}`}
                        />
                    {/each}
                {:else}
                    {#each [1, 2, 3] as slot (slot)}
                        <PlaceholderCard
                            icon={Heart}
                            title="Campaign placeholder"
                            hint="A campaign card will appear here when published"
                        />
                    {/each}
                {/if}
            </div>
        </div>
    </section>

    <!-- ═══ 6. EVENTS — top 3 upcoming ═══ -->
    <EventsRow events={featuredEvents} />

    <!-- ═══ 7. GALLERY — top 3 ═══ -->
    <section class="container py-16 lg:py-20">
        <div class="mb-8 flex items-end justify-between gap-4">
            <div class="space-y-2">
                <p
                    class="text-xs font-semibold uppercase tracking-[0.2em] text-primary"
                >
                    Gallery
                </p>
                <h2 class="font-serif text-3xl font-semibold lg:text-4xl">
                    Recent darshan
                </h2>
            </div>
            <a
                href="/gallery"
                class="hidden text-sm font-medium text-primary hover:underline sm:inline"
            >
                View gallery →
            </a>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            {#if hasGallery}
                {#each featuredGalleries.slice(0, 3) as gallery (gallery.id)}
                    <GalleryCard {gallery} href={`/gallery/${gallery.slug}`} />
                {/each}
            {:else}
                {#each [1, 2, 3] as slot (slot)}
                    <PlaceholderCard
                        icon={Camera}
                        title="Gallery placeholder"
                        hint="A gallery will appear here when published"
                    />
                {/each}
            {/if}
        </div>
    </section>

    <!-- ═══ 8. TRUST / AUTHORITY PANEL ═══ -->
    <TrustPanel content={content.trust_panel} />

    <!-- ═══ 9. DONATE CTA BAND ═══ -->
    <DonateCtaBand content={content.donate_cta} />

    <!-- ═══ 10. TRUST STRIP — small signals ═══ -->
    <section class="border-y border-border/40 bg-ivory py-8">
        <div class="container">
            <TrustBadgeRow />
        </div>
    </section>

    <!-- ═══ 11. CMS BODY (only when admin publishes long-form content) ═══ -->
    {#if html && html.trim() !== ''}
        <section class="container py-12 lg:py-16">
            <div class="prose prose-stone mx-auto max-w-3xl">
                {@html html}
            </div>
        </section>
    {/if}
</PublicLayout>
