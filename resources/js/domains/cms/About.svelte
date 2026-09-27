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

    let {
        page,
        aboutContent,
        heroBanners,
        featuredGalleries,
        html,
        appName,
    }: AboutPageProps = $props();

    const sourceContent = $derived(
        aboutContent === null ? FALLBACK_ABOUT_PAGE_CONTENT : aboutContent,
    );

    // Keep the CMS-managed layout and imagery, but carry the approved pooled
    // allocation rule through this page even when its stored copy is older.
    const content = $derived.by(() => ({
        ...sourceContent,
        story: {
            ...sourceContent.story,
            body: 'Sri Vishwaguru Sri Sri Sriram Shishyavrundham Mahasamsthanam (VSRSMS) is a charitable trust led by Sri Ram Ram Das Guruji. Guruji came from a teaching background and became a respected ritual and spiritual guide in Mysore. In 2012, he received Power of Attorney for the trust and began the service endeavour that has shaped its public work since then. Since 2007 the trust has schooled children in need of care — including blind and deaf pupils who receive free education — with schooling, life skills, and cultural training including Bharatanatyam. The schools run today, sustained by daily annadanam. The next chapter is a proposed three-acre healing and service campus near Nanjangud. Donations enter one pooled Schools & Campus Fund for daily school operations and the staged campus programme, including land acquisition and planned Gaushala, temple, healing-environment, and cow-care work. A gift is not restricted to one sub-project.',
        },
        stats: sourceContent.stats.map((stat) => stat.number === 'Daily'
            ? { ...stat, description: 'Every school day the trust provides meals, drinking water, and events for the children in its care. These daily needs are supported through the pooled fund alongside the staged campus programme.' }
            : stat),
        programs: sourceContent.programs.map((program, index) => {
            if (index === 0) return {
                ...program,
                body: 'The trust runs schools where children in need of care — including blind and deaf pupils — receive free education, with cultural training including Bharatanatyam. The schools run today. Their daily needs — food, water, and events — are supported through the pooled Schools & Campus Fund.',
            };
            if (index === 1) return {
                ...program,
                body: 'Every school day the trust provides meals, drinking water, and events for the children in its care. These daily needs are supported through one pooled fund alongside the staged campus programme; this is not a separate donation destination.',
            };
            if (index === 2) return {
                ...program,
                eyebrow: 'Campus · land acquisition',
                body: 'The pooled fund supports land acquisition near Nanjangud for the proposed three-acre campus, alongside daily school operations and planned later stages. The land is under discussion; construction follows once it is secured. Gifts are not restricted to land acquisition alone.',
            };
            return {
                ...program,
                eyebrow: 'Campus · future stage',
                body: 'The pooled fund includes the planned Gaushala, simple Shiva temple, healing environment, and long-term cow care as the proposed campus stages proceed. These are planned uses within one shared donation pool, not separate fundraising options.',
            };
        }),
        timeline: sourceContent.timeline.map((entry) => entry.year === 2026
            ? {
                ...entry,
                title: 'Schools and campus pooled fund',
                description: 'The trust consolidates daily school support and the staged proposed campus programme into one pooled fund, including land acquisition, planned Gaushala and temple work, the healing environment, and cow care.',
            }
            : entry),
        visit: {
            ...sourceContent.visit,
            title: 'Trust office in Mysore · schools and proposed campus near Nanjangud',
            body: 'The trust office is in Mysore. The schools and proposed campus are near Nanjangud; contact the office in advance to coordinate a visit and confirm current hours.',
            address: 'Trust office\nNo. 19/B, 2nd Cross, A.G. Block, N.R. Mohalla, Mysore — 570 007\nSchools and proposed campus: near Nanjangud, Karnataka',
        },
        donate_cta: {
            ...sourceContent.donate_cta,
            eyebrow: 'One pooled fund · schools and campus',
            title: 'Support the Schools & Campus Pooled Fund',
            body: 'One pooled fund supports daily school operations and the staged campus programme near Nanjangud, including land acquisition, planned Gaushala and temple work, the healing environment, and cow care. Your gift is not restricted to a single sub-project and is acknowledged with an official receipt.',
            cta_label: 'Support the pooled fund',
        },
    }));

    const trustees = $derived(content.trustees);
    const hasTrustees = $derived(trustees.length > 0);
    const stats = $derived(content.stats);
    const programs = $derived(content.programs);
    const pillars = $derived(content.values.pillars);
    const timeline = $derived(content.timeline);
    const hasValuesImage = $derived(content.values.image !== null);
    const hasStoryImage = $derived(content.story.image !== null);
    const hasFeaturedGalleries = $derived(featuredGalleries.length > 0);

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
        land_acquisition: MapPin,
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
                    <p class="max-w-2xl text-sm leading-relaxed text-muted-foreground">
                        Donations are pooled through one Schools &amp; Campus Fund
                        across daily school support and the staged campus programme,
                        including land acquisition and planned Gaushala work. Gifts
                        are not restricted to a single sub-project.
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
                    schools, the daily annadanam, and
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

        {#if hasValuesImage && content.values.image}
            <div
                class="relative mx-auto mt-14 max-w-4xl overflow-hidden rounded-md border border-border/40 bg-ivory"
            >
                <PublicMediaImage
                    media={content.values.image}
                    alt={imageAlt(content.values.image, content.values.title)}
                    class="aspect-video w-full object-cover"
                />
            </div>
        {/if}

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
                Ongoing work and planned stages, told honestly
            </h2>
            <p class="text-base text-muted-foreground">
                Daily school operations and the proposed campus stages are
                supported through one pooled Schools &amp; Campus Fund. Land
                acquisition, planned Gaushala and temple work, the healing
                environment, and cow care are included as the programme
                progresses; donations are not split into separate campaign
                destinations.
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
                    The arc of the trust\u2019s work \u2014 from the first
                    school cohorts to the schools running today and the land
                    fund for the campus.
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

    <!-- ═══ THREE WINDOWS — featured galleries ═══ -->
    {@const windows = (featuredGalleries ?? []).slice(0, 3)}
    {#if windows.length > 0}
        <section class="bg-muted/30 py-20 lg:py-28">
            <div class="container">
                <div class="mx-auto max-w-2xl space-y-4 text-center">
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                    >
                        From the gallery
                    </p>
                    <h2 class="font-serif text-3xl font-semibold lg:text-4xl">
                        {windows.length === 3
                        ? 'Three windows into the trust'
                        : 'Windows into the trust'}
                    </h2>
                    <p class="text-base text-muted-foreground">
                        Each gallery gathers a different part of the
                        trust’s work — the daily, the festive, and the
                        people who carry both.
                    </p>
                </div>

                <div class="mx-auto mt-12 grid max-w-5xl grid-cols-1 gap-6 sm:grid-cols-3">
                    {#each windows as gallery (gallery.id)}
                        <a
                            href={`/gallery/${gallery.slug}`}
                            class="group flex flex-col focus:outline-none focus:ring-2 focus:ring-primary/40"
                            aria-label={`Open the ${gallery.title} gallery`}
                        >
                            <div
                                class="aspect-square w-full overflow-hidden rounded-md border border-border/40 bg-ivory transition group-hover:border-primary/40"
                            >
                                {#if gallery.cover_image}
                                    <PublicMediaImage
                                        media={gallery.cover_image}
                                        alt={gallery.cover_image.alt_text ??
                                            gallery.title}
                                        class="h-full w-full object-cover transition-transform duration-700 group-hover:scale-[1.04]"
                                        loading="lazy"
                                        fetchpriority="low"
                                    />
                                {:else}
                                    <div
                                        class="flex h-full w-full items-center justify-center bg-gradient-to-br from-primary/15 via-ivory to-primary/10"
                                    >
                                        <MandalaDecoration
                                            size={200}
                                            tint="gold"
                                        />
                                    </div>
                                {/if}
                            </div>
                            <p
                                class="mt-4 text-xs font-semibold uppercase tracking-[0.18em] text-primary"
                            >
                                {gallery.image_count} image{gallery.image_count === 1
                                    ? ''
                                    : 's'}
                            </p>
                            <h3
                                class="mt-1 font-serif text-xl font-semibold lg:text-2xl"
                            >
                                {gallery.title}
                            </h3>
                            {#if gallery.short_description}
                                <p
                                    class="mt-1 text-sm leading-relaxed text-muted-foreground"
                                >
                                    {gallery.short_description}
                                </p>
                            {/if}
                        </a>
                    {/each}
                </div>
            </div>
        </section>
    {/if}

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
