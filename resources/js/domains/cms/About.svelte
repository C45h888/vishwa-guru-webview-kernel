<script lang="ts">
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import PublicMediaImage from '$shared/components/PublicMediaImage.svelte';
    import MandalaDecoration from '$shared/components/MandalaDecoration.svelte';
    import TrustBadgeRow from '$shared/components/TrustBadgeRow.svelte';
    import DonateCtaBand from '$shared/components/DonateCtaBand.svelte';
    import PlaceholderCard from '$shared/components/PlaceholderCard.svelte';
    import { Separator } from '$shared/ui/separator';
    import {
        BookOpen,
        Camera,
        ExternalLink,
        Flame,
        GraduationCap,
        Heart,
        MapPin,
        Phone,
        Sparkles,
    } from 'lucide-svelte';
    import { FALLBACK_ABOUT_PAGE_CONTENT } from './about-fallbacks';
    import type { AboutPageProps } from './types';

    let { page, aboutContent, heroBanners, html, appName }: AboutPageProps =
        $props();

    const content = $derived(
        aboutContent === null ? FALLBACK_ABOUT_PAGE_CONTENT : aboutContent,
    );

    const trustees = $derived(content.trustees);
    const hasTrustees = $derived(trustees.length > 0);
    const stats = $derived(content.stats);
    const programs = $derived(content.programs);
    const pillars = $derived(content.values.pillars);
    const timeline = $derived(content.timeline);
    const hasValuesImage = $derived(content.values.image !== null);
    const hasStoryImage = $derived(content.story.image !== null);

    // Icon mapping: V2 grammar vocabulary → lucide-svelte component.
    // Unknown keys fall back to Sparkles so the page never breaks on a new key.
    const pillarIcons: Record<string, typeof Heart> = {
        dharma: BookOpen,
        care: Heart,
        devotion: Flame,
        purpose: Sparkles,
    };
    const programIcons: Record<string, typeof Flame> = {
        children_education: GraduationCap,
        children_housing: Heart,
        gaushala: Sparkles,
        shiva_temple: Flame,
    };

    function imageAlt(image: { alt_text: string | null; title?: string | null } | null, fallback: string): string {
        return image?.alt_text ?? fallback;
    }
</script>

<svelte:head>
    <title>{page.title} — {appName}</title>
    {#if page.meta_description}
        <meta name="description" content={page.meta_description} />
    {/if}
</svelte:head>

<PublicLayout>
    <!-- ═══ MAGAZINE HERO ═══ -->
    <section class="relative overflow-hidden bg-background">
        <div
            class="pointer-events-none absolute -right-32 top-0 opacity-[0.08]"
            aria-hidden="true"
        >
            <MandalaDecoration size={420} tint="gold" />
        </div>
        <div
            class="pointer-events-none absolute -left-24 bottom-0 opacity-[0.06]"
            aria-hidden="true"
        >
            <MandalaDecoration size={260} tint="gold" />
        </div>

        <div class="container relative py-16 lg:py-24">
            <div class="grid grid-cols-1 items-center gap-12 lg:grid-cols-12 lg:gap-16">
                <div class="space-y-5 lg:col-span-7">
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                    >
                        About
                    </p>
                    <h1
                        class="font-serif text-4xl font-semibold leading-tight lg:text-6xl"
                    >
                        {page.title}
                    </h1>
                    {#if page.meta_description}
                        <p
                            class="max-w-2xl text-base leading-relaxed text-muted-foreground lg:text-lg"
                        >
                            {page.meta_description}
                        </p>
                    {/if}
                    <div class="pt-2">
                        <TrustBadgeRow />
                    </div>
                </div>

                <div class="relative lg:col-span-5">
                    <div
                        class="pointer-events-none absolute -right-10 -top-10 opacity-15"
                        aria-hidden="true"
                    >
                        <MandalaDecoration size={220} tint="gold" />
                    </div>
                    <div
                        class="relative aspect-[4/5] overflow-hidden rounded-md border border-border/40 bg-ivory"
                    >
                        {#if heroBanners.length > 0 && heroBanners[0].image}
                            <PublicMediaImage
                                media={heroBanners[0].image}
                                alt={imageAlt(heroBanners[0].image, page.title)}
                                class="h-full w-full object-cover"
                            />
                        {:else}
                            <div
                                class="flex h-full w-full items-center justify-center bg-gradient-to-br from-primary/15 via-ivory to-primary/10"
                            >
                                <MandalaDecoration size={280} tint="gold" />
                            </div>
                        {/if}
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ═══ OUR STORY ═══ -->
    <section class="container py-20 lg:py-28">
        <div class="grid grid-cols-1 items-center gap-12 lg:grid-cols-12 lg:gap-16">
            <div class="space-y-5 lg:col-span-7">
                <p
                    class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                >
                    {content.story.eyebrow}
                </p>
                <h2
                    class="font-serif text-3xl font-semibold leading-tight lg:text-5xl"
                >
                    {content.story.title}
                </h2>
                <p
                    class="max-w-2xl text-base leading-relaxed text-muted-foreground lg:text-lg"
                >
                    {content.story.body}
                </p>
            </div>
            <div class="relative lg:col-span-5">
                <div
                    class="pointer-events-none absolute -right-10 -top-10 opacity-15"
                    aria-hidden="true"
                >
                    <MandalaDecoration size={220} tint="gold" />
                </div>
                <div
                    class="relative aspect-square overflow-hidden rounded-md border border-border/40 bg-ivory"
                >
                    {#if hasStoryImage && content.story.image}
                        <PublicMediaImage
                            media={content.story.image}
                            alt={imageAlt(content.story.image, content.story.title)}
                            class="h-full w-full object-cover"
                        />
                    {:else}
                        <div
                            class="flex h-full w-full items-center justify-center bg-gradient-to-br from-primary/15 via-ivory to-primary/10"
                        >
                            <MandalaDecoration size={240} tint="gold" />
                        </div>
                    {/if}
                </div>
            </div>
        </div>
    </section>

    <Separator />

    <!-- ═══ STATS GRID ═══ -->
    <section class="bg-muted/30 py-20 lg:py-28">
        <div class="container">
            <div class="mx-auto max-w-2xl space-y-4 text-center">
                <p
                    class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                >
                    By the numbers
                </p>
                <h2 class="font-serif text-3xl font-semibold lg:text-4xl">
                    The trust at a glance
                </h2>
                <p class="text-base text-muted-foreground">
                    Numbers the trust has tracked and reported \u2014 the
                    children\u2019s-care chapter, the fundraising sequence, and
                    the proposed campus. Approximate figures are stated as
                    approximate; verified figures are stated as they are.
                </p>
            </div>

            <div
                class="mx-auto mt-14 grid max-w-5xl grid-cols-1 gap-x-8 gap-y-12 sm:grid-cols-2 lg:grid-cols-3"
            >
                {#each stats as stat (stat.label)}
                    <div class="text-center">
                        <div
                            class="font-serif text-5xl font-semibold leading-none text-primary lg:text-6xl"
                        >
                            {stat.number}
                        </div>
                        <div
                            class="mt-3 text-sm font-semibold uppercase tracking-[0.18em] text-foreground"
                        >
                            {stat.label}
                        </div>
                        {#if stat.description}
                            <p
                                class="mt-2 text-sm leading-relaxed text-muted-foreground"
                            >
                                {stat.description}
                            </p>
                        {/if}
                    </div>
                {/each}
            </div>
        </div>
    </section>

    <!-- ═══ VALUES + 3-PILLAR CARDS ═══ -->
    <section class="container py-20 lg:py-28">
        <div class="mx-auto max-w-3xl space-y-5 text-center">
            <p
                class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
            >
                {content.values.eyebrow}
            </p>
            <h2 class="font-serif text-3xl font-semibold lg:text-5xl">
                {content.values.title}
            </h2>
            <p class="text-base leading-relaxed text-muted-foreground lg:text-lg">
                {content.values.body}
            </p>
        </div>

        {#if pillars.length > 0}
            <div class="mx-auto mt-14 grid max-w-5xl grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                {#each pillars as pillar (pillar.name)}
                    {@const Icon = pillarIcons[pillar.icon_key] ?? Sparkles}
                    <article
                        class="rounded-md border border-border/40 bg-card p-6 text-center transition hover:border-primary/40 hover:shadow-sm"
                    >
                        <div class="mx-auto inline-flex h-12 w-12 items-center justify-center rounded-full border border-primary/30 bg-primary/5 text-primary">
                            <Icon class="h-5 w-5" aria-hidden="true" />
                        </div>
                        <h3
                            class="mt-4 font-serif text-xl font-semibold lg:text-2xl"
                        >
                            {pillar.name}
                        </h3>
                        <p
                            class="mt-2 text-sm leading-relaxed text-muted-foreground"
                        >
                            {pillar.description}
                        </p>
                    </article>
                {/each}
            </div>
        {/if}
    </section>

    <Separator />

    <!-- ═══ PROGRAMS ═══ -->
    <section class="container py-20 lg:py-28">
        <div class="mx-auto max-w-2xl space-y-4 text-center">
            <p
                class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
            >
                What we do
            </p>
            <h2 class="font-serif text-3xl font-semibold lg:text-4xl">
                Four stages, told honestly
            </h2>
            <p class="text-base text-muted-foreground">
                Each stage has its own scope, its own people, and its own
                ledger. The first chapter is concluded, the current
                stage is the land acquisition, and the planned stages are
                the campus construction and the cow care.
            </p>
        </div>

        <div class="mx-auto mt-14 grid max-w-6xl grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
            {#each programs as program, i (program.title)}
                {@const Icon = programIcons[program.icon_key] ?? Sparkles}
                <article
                    class="group flex h-full flex-col rounded-md border border-border/40 bg-card p-6 transition hover:border-primary/40 hover:shadow-sm"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-primary"
                        >
                            <Icon class="h-5 w-5" aria-hidden="true" />
                        </div>
                        <span
                            class="text-[10px] font-semibold uppercase tracking-[0.18em] text-muted-foreground"
                        >
                            {program.eyebrow}
                        </span>
                    </div>
                    <h3
                        class="mt-4 font-serif text-xl font-semibold lg:text-2xl"
                    >
                        {program.title}
                    </h3>
                    <p
                        class="mt-3 flex-1 text-sm leading-relaxed text-muted-foreground"
                    >
                        {program.body}
                    </p>
                    <span class="sr-only">Programme {i + 1}</span>
                </article>
            {/each}
        </div>
    </section>

    <!-- ═══ TIMELINE ═══ -->
    <section class="bg-muted/30 py-20 lg:py-28">
        <div class="container">
            <div class="mx-auto max-w-2xl space-y-4 text-center">
                <p
                    class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                >
                    Our Journey
                </p>
                <h2 class="font-serif text-3xl font-semibold lg:text-4xl">
                    Milestones in the trust\u2019s work
                </h2>
                <p class="text-base text-muted-foreground">
                    The arc of the trust\u2019s work \u2014 from the beginning
                    of the children\u2019s-care chapter to the launch of the land
                    acquisition campaign.
                </p>
            </div>

            <ol class="mx-auto mt-14 max-w-3xl space-y-12">
                {#each timeline as entry (entry.year)}
                    <li>
                        <div class="grid grid-cols-[auto,1fr] gap-x-6">
                            <div class="flex flex-col items-center">
                                <div
                                    class="font-serif text-4xl font-semibold text-primary lg:text-5xl"
                                >
                                    {entry.year}
                                </div>
                                <div
                                    class="mt-2 h-full w-px flex-1 bg-primary/30"
                                    aria-hidden="true"
                                ></div>
                            </div>
                            <div class="space-y-3 pb-2">
                                <h3
                                    class="font-serif text-xl font-semibold lg:text-2xl"
                                >
                                    {entry.title}
                                </h3>
                                <p
                                    class="text-base leading-relaxed text-muted-foreground"
                                >
                                    {entry.description}
                                </p>
                                {#if entry.image}
                                    <div
                                        class="mt-4 overflow-hidden rounded-md border border-border/40"
                                    >
                                        <PublicMediaImage
                                            media={entry.image}
                                            alt={imageAlt(entry.image, entry.title)}
                                            class="aspect-[16/10] w-full object-cover"
                                        />
                                    </div>
                                {/if}
                            </div>
                        </div>
                    </li>
                {/each}
            </ol>
        </div>
    </section>

    <Separator />

    <!-- ═══ TRUSTEES ═══ -->
    <section class="container py-20 lg:py-28">
        <div class="mx-auto max-w-2xl space-y-4 text-center">
            <p
                class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
            >
                Leadership
            </p>
            <h2 class="font-serif text-3xl font-semibold lg:text-4xl">
                Leadership of the trust
            </h2>
            <p class="text-base text-muted-foreground">
                The spiritual leader and the wider board responsible for
                the day-to-day stewardship of the trust\u2019s work.
            </p>
        </div>

        <div
            class="mx-auto mt-14 grid max-w-6xl grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3"
        >
            {#if hasTrustees}
                {#each trustees as trustee (trustee.name)}
                    <article
                        class="flex h-full flex-col overflow-hidden rounded-md border border-border/40 bg-card"
                    >
                        {#if trustee.photo}
                            <PublicMediaImage
                                media={trustee.photo}
                                alt={imageAlt(trustee.photo, trustee.name)}
                                class="aspect-[3/4] w-full object-cover"
                            />
                        {:else}
                            <div
                                class="flex aspect-[3/4] w-full items-center justify-center bg-gradient-to-br from-primary/15 via-ivory to-primary/10"
                            >
                                <div
                                    class="rounded-full border border-primary/30 bg-primary/5 px-3 py-1 text-xs font-medium text-primary/70"
                                >
                                    {trustee.role}
                                </div>
                            </div>
                        {/if}
                        <div class="flex flex-1 flex-col space-y-3 p-6">
                            <h3
                                class="font-serif text-xl font-semibold lg:text-2xl"
                            >
                                {trustee.name}
                            </h3>
                            <p
                                class="text-xs font-semibold uppercase tracking-[0.18em] text-primary"
                            >
                                {trustee.role}
                            </p>
                            {#if trustee.bio}
                                <p
                                    class="text-sm leading-relaxed text-muted-foreground"
                                >
                                    {trustee.bio}
                                </p>
                            {/if}
                        </div>
                    </article>
                {/each}
            {:else}
                {#each [1, 2, 3] as slot (slot)}
                    <PlaceholderCard
                        icon={Camera}
                        title="Trustee profile"
                        hint="A trustee card will appear here when published"
                    />
                {/each}
            {/if}
        </div>
    </section>

    <!-- ═══ SACRED VISUALS STRIP ═══ -->
    <section class="bg-muted/30 py-20 lg:py-28">
        <div class="container">
            <div class="mx-auto max-w-2xl space-y-4 text-center">
                <p
                    class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                >
                    From the gallery
                </p>
                <h2 class="font-serif text-3xl font-semibold lg:text-4xl">
                    Three windows into the trust
                </h2>
                <p class="text-base text-muted-foreground">
                    Each gallery gathers a different part of the trust\u2019s work
                    \u2014 the daily, the festive, and the people who carry both.
                </p>
            </div>

            <div class="mx-auto mt-12 grid max-w-5xl grid-cols-1 gap-6 sm:grid-cols-3">
                {#each [
                    { slug: 'sacred-rituals', title: 'Sacred Rituals', subtitle: 'From the children\u2019s-care chapter' },
                    { slug: 'sacred-festivals', title: 'Sacred Festivals', subtitle: 'Community moments across the year' },
                    { slug: 'community-cultural', title: 'Community & Cultural', subtitle: 'People and the work they do' },
                ] as card (card.slug)}
                    <a
                        href={`/gallery/${card.slug}`}
                        class="group flex flex-col"
                        aria-label={`Open the ${card.title} gallery`}
                    >
                        <div
                            class="aspect-square w-full overflow-hidden rounded-md border border-border/40 bg-ivory transition group-hover:border-primary/40"
                        >
                            <div
                                class="flex h-full w-full items-center justify-center bg-gradient-to-br from-primary/15 via-ivory to-primary/10"
                            >
                                <MandalaDecoration size={200} tint="gold" />
                            </div>
                        </div>
                        <p
                            class="mt-4 text-xs font-semibold uppercase tracking-[0.18em] text-primary"
                        >
                            {card.subtitle}
                        </p>
                        <h3
                            class="mt-1 font-serif text-xl font-semibold lg:text-2xl"
                        >
                            {card.title}
                        </h3>
                    </a>
                {/each}
            </div>
        </div>
    </section>

    <!-- ═══ PLAN YOUR VISIT ═══ -->
    <section class="container py-20 lg:py-28">
        <div class="mx-auto max-w-2xl space-y-4 text-center">
            <p
                class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
            >
                {content.visit.eyebrow}
            </p>
            <h2 class="font-serif text-3xl font-semibold lg:text-4xl">
                {content.visit.title}
            </h2>
            <p class="text-base leading-relaxed text-muted-foreground lg:text-lg">
                {content.visit.body}
            </p>
        </div>

        <div
            class="mx-auto mt-14 grid max-w-5xl grid-cols-1 gap-8 lg:grid-cols-2"
        >
            <div
                class="rounded-md border border-border/40 bg-card p-6 space-y-4"
            >
                <div class="flex items-start gap-3">
                    <MapPin
                        class="mt-1 h-5 w-5 flex-shrink-0 text-primary"
                        aria-hidden="true"
                    />
                    <div>
                        <p
                            class="text-xs font-semibold uppercase tracking-[0.18em] text-primary"
                        >
                            Address
                        </p>
                        <p class="mt-1 whitespace-pre-line text-sm leading-relaxed text-foreground">
                            {content.visit.address}
                        </p>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <Phone
                        class="mt-1 h-5 w-5 flex-shrink-0 text-primary"
                        aria-hidden="true"
                    />
                    <div>
                        <p
                            class="text-xs font-semibold uppercase tracking-[0.18em] text-primary"
                        >
                            Phone
                        </p>
                        <a
                            href={`tel:${content.visit.phone.replace(/[^\d+]/g, '')}`}
                            class="mt-1 block text-sm leading-relaxed text-foreground hover:text-primary"
                        >
                            {content.visit.phone}
                        </a>
                    </div>
                </div>
                {#if content.visit.map_url}
                    <a
                        href={content.visit.map_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-2 text-sm font-semibold text-primary transition hover:gap-3"
                    >
                        <span>Open in Google Maps</span>
                        <ExternalLink class="h-4 w-4" aria-hidden="true" />
                    </a>
                {/if}
            </div>

            <div
                class="rounded-md border border-border/40 bg-card p-6 space-y-4"
            >
                <div>
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.18em] text-primary"
                    >
                        Timings
                    </p>
                    <p class="mt-1 whitespace-pre-line text-sm leading-relaxed text-foreground">
                        {content.visit.timings}
                    </p>
                </div>
                <div>
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.18em] text-primary"
                    >
                        Dress Code
                    </p>
                    <p class="mt-1 text-sm leading-relaxed text-foreground">
                        {content.visit.dress_code}
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- ═══ DONATE CTA BAND (CMS-driven) ═══ -->
    <DonateCtaBand content={content.donate_cta} />

    <!-- ═══ TRUST STRIP ═══ -->
    <section class="border-y border-border/40 bg-ivory py-8">
        <div class="container">
            <TrustBadgeRow />
        </div>
    </section>

    <!-- ═══ OPTIONAL CMS PROSE BODY ═══ -->
    {#if html && html.trim() !== ''}
        <section class="container py-12 lg:py-16">
            <div class="prose prose-stone mx-auto max-w-3xl">
                {@html html}
            </div>
        </section>
    {/if}
</PublicLayout>