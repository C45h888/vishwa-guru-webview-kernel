<script lang="ts">
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import HeroSlideshow from '$shared/components/HeroSlideshow.svelte';
    import PillarTriad from '$shared/components/PillarTriad.svelte';
    import QuoteSection from '$shared/components/QuoteSection.svelte';
    import PicturePanel from '$shared/components/PicturePanel.svelte';
    import TrustBadgeRow from '$shared/components/TrustBadgeRow.svelte';
    import TrustPanel from '$shared/components/TrustPanel.svelte';
    import EventsList from '$shared/components/EventsList.svelte';
    import DonateCtaBand from '$shared/components/DonateCtaBand.svelte';
    import GalleryPreview from '$shared/components/GalleryPreview.svelte';
    import CampaignCard from '$shared/components/CampaignCard.svelte';
    import GalleryCard from '$shared/components/GalleryCard.svelte';
    import PlaceholderCard from '$shared/components/PlaceholderCard.svelte';
    import PublicMediaImage from '$shared/components/PublicMediaImage.svelte';
    import { Button } from '$shared/ui/button';
    import { ArrowRight, Camera, Heart } from 'lucide-svelte';
    import { FALLBACK_HOMEPAGE_CONTENT } from './homepage-fallbacks';
    import type { HomePageProps } from './types';
    import SeoHead from '$shared/components/SeoHead.svelte';

    let {
        page,
        homepageContent,
        heroBanners,
        html,
        featuredCampaigns,
        featuredEvents,
        featuredGalleries,
        appName,
        appUrl,
    }: HomePageProps = $props();

    const sourceContent = $derived(
        homepageContent === null ? FALLBACK_HOMEPAGE_CONTENT : homepageContent,
    );

    // The live CMS row can predate the pooled-fund decision. Keep its
    // approved editorial images and structure, while making every public
    // donation explanation agree with the single checkout destination.
    const content = $derived.by(() => ({
        ...sourceContent,
        story: {
            ...sourceContent.story,
            body: 'Sri Vishwaguru Sri Sri Sriram Shishyavrundham Mahasamsthanam (VSRSMS) is a charitable trust led by Sri Ram Ram Das Guruji. Since 2007 the trust has schooled children in need of care — including blind and deaf pupils who receive free education. Daily life at the schools is sustained by annadanam: food, water, and events for the children. The schools run today, while a proposed three-acre healing and service campus near Nanjangud is being prepared. Donations enter one pooled Schools & Campus Fund for daily school operations and the staged campus programme, including land acquisition and planned Gaushala, temple, healing-environment, and cow-care work. A gift is not restricted to one sub-project.',
        },
        programs: [
            {
                ...sourceContent.programs[0],
                body: 'The trust runs schools where children in need of care — including blind and deaf pupils — receive free education. The schools run today. Their daily needs, including food, water, and events, are supported through the pooled Schools & Campus Fund alongside the staged campus programme.',
            },
            {
                ...sourceContent.programs[1],
                body: 'Every school day the trust provides meals, drinking water, and events for the children in its care. These ongoing school needs are supported through the same pooled fund as the proposed campus stages; donors do not select separate school and campus destinations.',
            },
            {
                ...sourceContent.programs[2],
                eyebrow: 'Campus · pooled fund',
                body: 'The pooled fund supports land acquisition near Nanjangud for the proposed three-acre campus, alongside daily school operations and planned later stages. The land is under discussion; construction follows once it is secured. Gifts are not restricted to land acquisition alone.',
            },
        ],
        trust_panel: {
            ...sourceContent.trust_panel,
            registration: 'Sri Vishwaguru Sri Sri Sriram Shishyavrundham Mahasamsthanam is a charitable trust registered in India. Donations are pooled across daily school operations and the staged campus programme near Nanjangud, including land acquisition and planned Gaushala, temple, healing-environment, and cow-care work. A gift is not restricted to one sub-project.',
        },
        donate_cta: {
            ...sourceContent.donate_cta,
            eyebrow: 'One pooled fund · schools and campus',
            title: 'Support the Schools & Campus Pooled Fund',
            body: 'One pooled fund supports daily school operations and the staged campus programme near Nanjangud, including land acquisition, planned Gaushala and temple work, the healing environment, and cow care. Your gift is not restricted to a single sub-project and is acknowledged with an official receipt.',
            cta_label: 'Support the pooled fund',
        },
    }));

    const hasCause = $derived(featuredCampaigns.length > 0);
    const hasGallery = $derived(featuredGalleries.length > 0);

    const publicHeroBanners = $derived(heroBanners.map((banner, index) =>
        index === 0
            ? {
                ...banner,
                subtitle: 'Since 2007 the trust has schooled children in need of care, sustained by daily annadanam. One pooled Schools & Campus Fund supports daily school operations and the staged campus programme near Nanjangud.',
            }
            : banner,
    ));

    const storyCtaVisible = $derived(
        content.story.cta_label !== null
            && content.story.cta_url !== null
            && content.story.cta_label !== ''
            && content.story.cta_url !== '',
    );

    const pillarFirst = $derived(
        content.story.pillar_image ?? content.story.image,
    );
    const pillarSecond = $derived(
        content.programs[1]?.pillar_image ?? content.programs[1]?.image ?? featuredEvents[0]?.banner_image ?? null,
    );
    const pillarThird = $derived(
        featuredGalleries[0]?.cover_image ?? null,
    );
</script>

<SeoHead
    title={page.title}
    {appName}
    {appUrl}
    description={page.meta_description}
    jsonLd={{
        '@context': 'https://schema.org',
        '@type': 'HinduTemple',
        name: appName,
        url: appUrl,
        logo: `${appUrl}/icon-512.png`,
    }}
/>

<PublicLayout>
    <!-- ═══ 1. HERO SLIDESHOW ═══ -->
    <HeroSlideshow {page} heroBanners={publicHeroBanners} />

    <!-- ═══ 2. PILLAR TRIAD — 3 image cards, no labels ═══ -->
    <PillarTriad
        firstImage={pillarFirst}
        secondImage={pillarSecond}
        thirdImage={pillarThird}
    />

    <!-- ═══ 3. TRUST STORY ═══ -->
    <section class="container py-24 lg:py-32">
        <div
            class="grid grid-cols-1 items-center gap-10 lg:grid-cols-12 lg:gap-16"
        >
            <div class="space-y-5 lg:col-span-7">
                <p
                    class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                >
                    {content.story.eyebrow}
                </p>
                <h2
                    class="text-3xl font-semibold leading-tight tracking-tight lg:text-5xl"
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
                <div class="relative">
                    {#if content.story.image}
                        <div
                            class="overflow-hidden rounded-md border border-border"
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
                            class="aspect-square overflow-hidden rounded-md border border-border bg-ivory"
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
                        </div>
                    {/if}
                </div>
            </div>
        </div>
    </section>

    <!-- ═══ 4. PROGRAMS — 3 alternating beats ═══ -->
    <div class="bg-muted">
        {#each content.programs as program, index (program.key)}
            <PicturePanel
                eyebrow={program.eyebrow}
                title={program.title}
                body={program.body}
                image={program.image}
                altText={program.alt_text ?? undefined}
                reverse={index % 2 === 1}
                variant={index % 2 === 1 ? 'warm' : 'gold'}
            />
        {/each}
    </div>

    <!-- ═══ 5. MISSION QUOTE ═══ -->
    <QuoteSection
        eyebrow={content.mission_quote.eyebrow}
        quote={content.mission_quote.quote}
        attribution={content.mission_quote.attribution}
    />

    <!-- ═══ 6. CAMPAIGNS CTA + GRID ═══ -->
    <section class="py-24 lg:py-32">
        <div class="container space-y-10">
            <div class="mx-auto max-w-2xl space-y-4 text-center">
                <p
                    class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                >
                    Campaigns
                </p>
                <h2 class="text-3xl font-semibold tracking-tight lg:text-5xl">
                    What we're doing today
                </h2>
                <p class="text-base text-muted-foreground">
                    One pooled fund supports daily school operations and the
                    staged campus programme near Nanjangud, including land
                    acquisition and planned Gaushala, temple, healing, and
                    cow-care work as the stages proceed.
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

    <!-- ═══ 7. EVENTS — date-sorted list ═══ -->
    <EventsList events={featuredEvents} limit={5} />

    <!-- ═══ 8. GALLERY — 3 featured ═══ -->
    <section class="container py-24 lg:py-32">
        <div class="mb-8 flex items-end justify-between gap-4">
            <div class="space-y-2">
                <p
                    class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                >
                    Gallery
                </p>
                <h2 class="text-3xl font-semibold tracking-tight lg:text-5xl">
                    School life and temple moments
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

    <!-- ═══ 9. GALLERY PREVIEW — 4 featured, larger showcase ═══ -->
    <GalleryPreview galleries={featuredGalleries} limit={4} />

    <!-- ═══ 10. TRUST / AUTHORITY PANEL ═══ -->
    <TrustPanel content={content.trust_panel} />

    <!-- ═══ 11. DONATE CTA BAND — composed saffron treatment ═══ -->
    <DonateCtaBand content={content.donate_cta} />

    <!-- ═══ 12. TRUST STRIP — small signals ═══ -->
    <section class="border-y border-border bg-muted py-8">
        <div class="container">
            <TrustBadgeRow />
        </div>
    </section>

    <!-- ═══ 13. CMS BODY (only when admin publishes long-form content) ═══ -->
    {#if html && html.trim() !== ''}
        <section class="container py-12 lg:py-16">
            <div class="prose prose-stone mx-auto max-w-3xl prose-a:text-primary">
                {@html html}
            </div>
        </section>
    {/if}
</PublicLayout>
