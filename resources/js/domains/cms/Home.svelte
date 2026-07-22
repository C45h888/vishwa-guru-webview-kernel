<script lang="ts">
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import CampaignCard from '$shared/components/CampaignCard.svelte';
    import EventCard from '$shared/components/EventCard.svelte';
    import GalleryCard from '$shared/components/GalleryCard.svelte';
    import PublicMediaImage from '$shared/components/PublicMediaImage.svelte';
    import MandalaDecoration from '$shared/components/MandalaDecoration.svelte';
    import TrustBadgeRow from '$shared/components/TrustBadgeRow.svelte';
    import BottomCtaBand from '$shared/components/BottomCtaBand.svelte';
    import { Button } from '$shared/ui/button';
    import { Card, CardContent } from '$shared/ui/card';
    import { Receipt, Shield, Building2, ArrowRight } from 'lucide-svelte';
    import type { HomePageProps } from './types';

    let {
        page,
        heroBanners,
        html,
        featuredCampaigns,
        featuredEvents,
        featuredGalleries,
        appName,
    }: HomePageProps = $props();

    const subtitle = $derived(
        page.meta_description ??
            'Supporting daily pooja, annadanam, and temple maintenance through seva.',
    );

    const hasCause = $derived(featuredCampaigns.length > 0);
    const hasEvents = $derived(featuredEvents.length > 0);
    const hasGallery = $derived(featuredGalleries.length > 0);
    const hasHeroBanners = $derived(heroBanners.length > 0);
    const showComposition = $derived(hasCause || hasEvents);
</script>

<svelte:head>
    <title>{page.title} — {appName}</title>
    {#if page.meta_description}
        <meta name="description" content={page.meta_description} />
    {/if}
</svelte:head>

<PublicLayout>
    <!-- HERO -->
    <section class="relative overflow-hidden bg-background">
        <div
            class="pointer-events-none absolute inset-0 flex items-center justify-center opacity-25"
            aria-hidden="true"
        >
            <MandalaDecoration size={680} tint="gold" />
        </div>

        <div class="container relative py-20 lg:py-28">
            <div class="mx-auto max-w-3xl space-y-6 text-center">
                <h1
                    class="font-serif text-4xl font-semibold tracking-tight lg:text-6xl"
                >
                    {page.title}
                </h1>
                <p
                    class="mx-auto max-w-2xl text-base text-muted-foreground lg:text-lg"
                >
                    {subtitle}
                </p>

                <div class="pt-2">
                    <TrustBadgeRow />
                </div>

                <div
                    class="flex flex-wrap items-center justify-center gap-3 pt-2"
                >
                    <Button href="/donate" size="lg">Donate Now</Button>
                    <Button href="/campaigns" size="lg" variant="outline">
                        Browse Causes
                    </Button>
                </div>
            </div>
        </div>
    </section>

    <!-- HERO BANNER CAROUSEL -->
    {#if hasHeroBanners}
        <section class="container py-10 lg:py-14">
            <div
                class="grid grid-cols-1 gap-4 {heroBanners.length >= 3
                    ? 'md:grid-cols-2 lg:grid-cols-3'
                    : heroBanners.length === 2
                      ? 'md:grid-cols-2'
                      : ''}"
            >
                {#each heroBanners as banner (banner.id)}
                    <Card class="overflow-hidden">
                        {#if banner.image}
                            <PublicMediaImage
                                media={banner.image}
                                alt={banner.title ?? ''}
                                class="aspect-[16/9] w-full object-cover"
                            />
                        {/if}
                        {#if banner.title || banner.cta_label}
                            <CardContent class="space-y-3 p-5">
                                {#if banner.title}
                                    <h3
                                        class="font-serif text-xl font-semibold"
                                    >
                                        {banner.title}
                                    </h3>
                                {/if}
                                {#if banner.subtitle}
                                    <p class="text-sm text-muted-foreground">
                                        {banner.subtitle}
                                    </p>
                                {/if}
                                {#if banner.cta_label && banner.cta_url}
                                    <a
                                        href={banner.cta_url}
                                        class="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline"
                                    >
                                        {banner.cta_label}
                                        <ArrowRight class="h-4 w-4" />
                                    </a>
                                {/if}
                            </CardContent>
                        {/if}
                    </Card>
                {/each}
            </div>
        </section>
    {/if}

    <!-- CHOOSE YOUR SEVA (campaigns) -->
    {#if hasCause}
        <section class="container space-y-6 py-12 lg:py-16">
            <div class="flex items-end justify-between gap-4">
                <h2 class="font-serif text-3xl font-semibold lg:text-4xl">
                    Choose your seva
                </h2>
                <a
                    href="/campaigns"
                    class="hidden text-sm font-medium text-primary hover:underline sm:inline"
                >
                    View all campaigns →
                </a>
            </div>
            <div
                class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3"
            >
                {#each featuredCampaigns.slice(0, 6) as campaign (campaign.id)}
                    <CampaignCard
                        campaign={campaign}
                        href={`/campaigns/${campaign.slug}`}
                    />
                {/each}
            </div>
        </section>
    {/if}

    <!-- ISKCON MUMBAI-STYLE COMPOSITION: events + causes interleaved -->
    {#if showComposition}
        <section class="bg-muted/30 py-12 lg:py-16">
            <div class="container space-y-10">
                <h2 class="font-serif text-3xl font-semibold lg:text-4xl">
                    Support the temple
                </h2>

                <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
                    {#if hasEvents}
                        <div class="space-y-4">
                            <h3
                                class="font-serif text-xl font-semibold text-muted-foreground"
                            >
                                Upcoming events
                            </h3>
                            <div class="space-y-3">
                                {#each featuredEvents.slice(0, 3) as event (event.id)}
                                    <a
                                        href={`/events/${event.slug}`}
                                        class="block rounded-md border border-border/60 bg-background p-4 transition-colors hover:border-primary/40"
                                    >
                                        <div
                                            class="font-serif text-lg font-medium"
                                        >
                                            {event.title}
                                        </div>
                                        {#if event.short_description}
                                            <p
                                                class="mt-1 text-sm text-muted-foreground"
                                            >
                                                {event.short_description}
                                            </p>
                                        {/if}
                                    </a>
                                {/each}
                            </div>
                            <a
                                href="/events"
                                class="inline-block text-sm font-medium text-primary hover:underline"
                            >
                                See all events →
                            </a>
                        </div>
                    {/if}

                    {#if hasCause}
                        <div class="space-y-4">
                            <h3
                                class="font-serif text-xl font-semibold text-muted-foreground"
                            >
                                Active causes
                            </h3>
                            <div class="space-y-3">
                                {#each featuredCampaigns.slice(0, 3) as campaign (campaign.id)}
                                    <a
                                        href={`/campaigns/${campaign.slug}`}
                                        class="block rounded-md border border-border/60 bg-background p-4 transition-colors hover:border-primary/40"
                                    >
                                        <div
                                            class="font-serif text-lg font-medium"
                                        >
                                            {campaign.title}
                                        </div>
                                        {#if campaign.short_description}
                                            <p
                                                class="mt-1 text-sm text-muted-foreground"
                                            >
                                                {campaign.short_description}
                                            </p>
                                        {/if}
                                    </a>
                                {/each}
                            </div>
                            <a
                                href="/campaigns"
                                class="inline-block text-sm font-medium text-primary hover:underline"
                            >
                                See all causes →
                            </a>
                        </div>
                    {/if}
                </div>
            </div>
        </section>
    {/if}

    <!-- RECENT DARSHAN -->
    {#if hasGallery}
        <section class="container space-y-6 py-12 lg:py-16">
            <div class="flex items-end justify-between gap-4">
                <h2 class="font-serif text-3xl font-semibold lg:text-4xl">
                    Recent darshan
                </h2>
                <a
                    href="/gallery"
                    class="hidden text-sm font-medium text-primary hover:underline sm:inline"
                >
                    View gallery →
                </a>
            </div>
            <div
                class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3"
            >
                {#each featuredGalleries.slice(0, 6) as gallery (gallery.id)}
                    <GalleryCard
                        gallery={gallery}
                        href={`/gallery/${gallery.slug}`}
                    />
                {/each}
            </div>
        </section>
    {/if}

    <!-- TRUST STRIP -->
    <section class="bg-muted/50 py-12 lg:py-16">
        <div class="container">
            <div class="grid grid-cols-1 gap-8 md:grid-cols-3">
                <div class="flex gap-4">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                    >
                        <Receipt class="h-5 w-5" />
                    </div>
                    <div>
                        <h3
                            class="font-serif text-base font-semibold"
                        >
                            80G eligible
                        </h3>
                        <p class="mt-1 text-sm text-muted-foreground">
                            All donations qualify for tax exemption under
                            Section 80G of the Income Tax Act.
                        </p>
                    </div>
                </div>
                <div class="flex gap-4">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                    >
                        <Shield class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="font-serif text-base font-semibold">
                            Secure payments
                        </h3>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Payments processed securely via Razorpay. PCI-DSS
                            compliant infrastructure.
                        </p>
                    </div>
                </div>
                <div class="flex gap-4">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                    >
                        <Building2 class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="font-serif text-base font-semibold">
                            Registered trust
                        </h3>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Temple Trust is a registered charitable trust.
                            <a
                                href="/about"
                                class="font-medium text-primary hover:underline"
                            >
                                View registration details →
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CMS BODY (only when admin publishes long-form content) -->
    {#if html && html.trim() !== ''}
        <section class="container py-12 lg:py-16">
            <div class="prose prose-stone max-w-none">
                {@html html}
            </div>
        </section>
    {/if}

    <!-- BOTTOM CTA -->
    <BottomCtaBand />
</PublicLayout>