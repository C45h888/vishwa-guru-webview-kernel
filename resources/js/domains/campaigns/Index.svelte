<script lang="ts">
    import { router, page } from '@inertiajs/svelte';
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import MandalaDecoration from '$shared/components/MandalaDecoration.svelte';
    import CampaignCard from '$shared/components/CampaignCard.svelte';
    import TrustBadgeRow from '$shared/components/TrustBadgeRow.svelte';
    import BottomCtaBand from '$shared/components/BottomCtaBand.svelte';
    import { Button } from '$shared/ui/button';
    import {
        ArrowRight,
        ChevronDown,
        Filter,
        Inbox,
        Pencil,
        X,
    } from 'lucide-svelte';
    import type {
        CampaignSummaryProps,
        PaginationProps,
        AppPageProps,
    } from '$shared/lib/inertia';

    type SortKey = 'featured' | 'newest' | 'ending';

    let {
        campaigns,
        pagination,
        categories = [],
        totalCampaigns = 0,
        currentCategory = null,
        currentSort = 'featured' as SortKey,
        appName,
    }: AppPageProps<{
        campaigns: (CampaignSummaryProps & {
            raised_amount_minor?: number | null;
            donor_count?: number | null;
        })[];
        pagination: PaginationProps;
        categories?: string[];
        totalCampaigns?: number;
        currentCategory?: string | null;
        currentSort?: SortKey;
    }> = $props();

    const SORT_OPTIONS: { key: SortKey; label: string }[] = [
        { key: 'featured', label: 'Featured first' },
        { key: 'newest', label: 'Newest' },
        { key: 'ending', label: 'Ending soonest' },
    ];

    const activeCategory = $derived(currentCategory ?? null);

    function pushQuery(params: Record<string, string | null>) {
        const url: Record<string, string> = {};
        for (const [k, v] of Object.entries(params)) {
            if (v !== null && v !== '') url[k] = v;
        }
        router.get('/campaigns', url, { preserveScroll: true });
    }

    function setCategory(category: string | null) {
        pushQuery({ category, sort: currentSort, page: null });
    }

    function setSort(sort: SortKey) {
        pushQuery({ category: activeCategory, sort, page: null });
    }

    function clearFilters() {
        router.get('/campaigns', {}, { preserveScroll: true });
    }

    const hasActiveFilter = $derived(activeCategory !== null);

    const visibleStart = $derived(
        pagination.total === 0
            ? 0
            : (pagination.page - 1) * pagination.per_page + 1,
    );
    const visibleEnd = $derived(
        Math.min(pagination.page * pagination.per_page, pagination.total),
    );

    const hasContent = $derived(campaigns.length > 0);

    /**
     * The 3 pillar cards. These are the Isha-style "Three Pillars"
     * applied to the campaigns page — each is a large 1:1 image with
     * a short editorial block beneath. Clicking just scrolls to the grid
     * below; the user picks the campaign they want from there.
     *
     * The three images are mapped explicitly to the manually prepared
     * canonical files. This keeps each image paired with the cause it
     * explains even when older public-media records point at stale assets.
     */
    const PILLAR_COPY = [
        {
            src: '/storage/cms-media-upscaled/canonical/kids-event.png',
            alt: 'Daily annadanam at the schools',
            eyebrow: 'Ongoing \u00b7 daily',
            title: 'Sustain the schools: food, water, events',
            body: 'Every school day the trust provides daily annadanam for children in need of care — including blind and deaf pupils on free education. Contributions sustain meals, drinking water, and school events.',
        },
        {
            src: '/storage/cms-media-upscaled/canonical/LAND.jpg',
            alt: 'Open land near the proposed campus site',
            eyebrow: 'Campus · land acquisition',
            title: 'Acquire the land for the new campus',
            body: 'Alongside the schools, the trust is raising funds to acquire land near Nanjangud, outside Mysore, for the proposed healing and service campus. The land is under discussion; construction begins after it is secured.',
        },
        {
            src: '/storage/cms-media-upscaled/canonical/GAUSHALA.jpg',
            alt: 'Illustrative concept for a future Gaushala and temple campus',
            eyebrow: 'Campus · future stage',
            title: 'Build the Gaushala, temple, and cow care',
            body: 'The pooled fund includes the planned Gaushala, a simple Shiva temple, a disciplined healing environment, and long-term cow care as the campus stages proceed.',
        },
    ];

    const PILLARS = PILLAR_COPY;
</script>

<svelte:head>
    <title>Campaigns — {appName}</title>
</svelte:head>

<PublicLayout>
    <!-- ═══ 1. THREE PILLARS — main header (Isha-style 3-image row) ═══ -->
    <section class="relative overflow-hidden bg-ivory">
        <div
            class="pointer-events-none absolute -right-24 top-0 opacity-[0.07]"
            aria-hidden="true"
        >
            <MandalaDecoration size={420} tint="gold" />
        </div>

        <div class="container relative py-12 lg:py-20">
            <ul
                class="grid grid-cols-1 gap-6 sm:grid-cols-2 sm:gap-5 lg:grid-cols-3 lg:gap-8"
                aria-label="What the campaigns sustain"
            >
                {#each PILLARS as pillar, i (i)}
                    <li>
                        <a
                            href="#campaigns-grid"
                            class="group block focus:outline-none focus:ring-2 focus:ring-primary/40"
                        >
                            <div
                                class="relative aspect-square overflow-hidden rounded-md border border-border/40 bg-ivory"
                            >
                                <img
                                    src={pillar.src}
                                    alt={pillar.alt}
                                    class="absolute inset-0 h-full w-full object-cover transition-transform duration-700 group-hover:scale-[1.04]"
                                    loading="lazy"
                                    decoding="async"
                                    onerror={(event) => {
                                        const fallback = PILLAR_COPY[i].src;
                                        if (event.currentTarget.src !== new URL(fallback, window.location.href).href) {
                                            event.currentTarget.src = fallback;
                                        }
                                    }}
                                />
                                <div
                                    class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/55 via-black/10 to-transparent opacity-90"
                                    aria-hidden="true"
                                ></div>
                                <div
                                    class="pointer-events-none absolute inset-0 opacity-0 transition-opacity duration-500 group-hover:opacity-100"
                                    aria-hidden="true"
                                >
                                    <div
                                        class="absolute inset-0"
                                        style="background: radial-gradient(circle at 30% 30%, hsl(25 90% 48% / 0.15), transparent 60%);"
                                    ></div>
                                </div>
                            </div>

                            <div class="mt-4 space-y-2">
                                <p
                                    class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                                >
                                    {pillar.eyebrow}
                                </p>
                                <h3
                                    class="font-serif text-xl font-semibold leading-tight lg:text-2xl"
                                >
                                    {pillar.title}
                                </h3>
                                <p
                                    class="text-sm leading-relaxed text-muted-foreground"
                                >
                                    {pillar.body}
                                </p>
                                <span
                                    class="inline-flex items-center gap-1 pt-1 text-sm font-medium text-primary transition-transform group-hover:translate-x-0.5"
                                >
                                    {pillar.eyebrow.startsWith('Campus · future')
                                        ? 'See future campus stage'
                                        : 'Explore pooled fund'}
                                    <ArrowRight
                                        class="h-3.5 w-3.5"
                                        aria-hidden="true"
                                    />
                                </span>
                            </div>
                        </a>
                    </li>
                {/each}
            </ul>
        </div>
    </section>

    <!-- ═══ 2. BREADCRUMB — between pillars and editorial copy ═══ -->
    <section class="border-y border-border/40 bg-background">
        <div class="container flex items-center justify-between py-3 text-xs text-muted-foreground">
            <div>
                <a href="/" class="hover:text-primary">Home</a>
                <span class="mx-2" aria-hidden="true">/</span>
                <span class="text-foreground/70">Campaigns</span>
            </div>
            {#if $page.props.authUser?.role === 'admin'}
                <a
                    href="/admin/campaigns/new"
                    class="inline-flex items-center gap-1.5 rounded-md border border-border bg-background px-2.5 py-1 text-xs font-medium hover:border-primary hover:bg-primary hover:text-primary-foreground"
                    title="New campaign"
                >
                    <Pencil class="h-3 w-3" aria-hidden="true" />
                    <span>New campaign</span>
                </a>
            {/if}
        </div>
    </section>

    <!-- ═══ 3. EDITORIAL COPY — what the campaigns page is ═══ -->
    <section class="bg-background">
        <div class="container py-12 lg:py-20">
            <div class="mx-auto max-w-3xl space-y-5 text-center">
                <p
                    class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                >
                    How the giving works
                </p>
                <h1
                    class="font-serif text-3xl font-semibold leading-tight lg:text-4xl"
                >
                    One pooled fund, several stages of service
                </h1>
                <div
                    class="space-y-4 text-base leading-relaxed text-muted-foreground lg:text-lg"
                >
                    <p>
                        Donations go to one pooled fund across daily school
                        operations and the proposed campus programme, including
                        land acquisition, planned Gaushala and temple work, the
                        healing environment, and cow care as those stages
                        proceed.
                    </p>
                    <p>
                        The fund is pooled rather than split into separate
                        stage-specific donation options. The cards below explain
                        the different parts of the work; every contribution uses
                        the same giving destination.
                    </p>
                </div>

                <div class="pt-3">
                    <Button href="/donate" size="lg" variant="outline">
                        View donation options
                        <ArrowRight
                            class="ml-2 h-4 w-4"
                            aria-hidden="true"
                        />
                    </Button>
                </div>

                <div class="pt-2">
                    <TrustBadgeRow />
                </div>
            </div>
        </div>
    </section>

    <!-- ═══ 4. FILTER + SORT TOOLBAR ═══ -->
    <section id="campaigns-grid" class="container py-8 lg:py-10">
        <div
            class="flex flex-col items-stretch gap-4 lg:flex-row lg:items-center lg:justify-between"
        >
            <div class="flex flex-wrap items-center gap-2">
                <span
                    class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground"
                >
                    <Filter class="h-3.5 w-3.5" aria-hidden="true" />
                    Filter
                </span>
                <button
                    type="button"
                    class="rounded-full border px-3 py-1 text-xs font-medium transition-colors {activeCategory ===
                    null
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'border-border bg-background text-foreground/80 hover:border-primary/40'}"
                    aria-pressed={activeCategory === null}
                    onclick={() => setCategory(null)}
                >
                    All
                </button>
                {#each categories as category (category)}
                    <button
                        type="button"
                        class="rounded-full border px-3 py-1 text-xs font-medium transition-colors {activeCategory ===
                        category
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-border bg-background text-foreground/80 hover:border-primary/40'}"
                        aria-pressed={activeCategory === category}
                        onclick={() => setCategory(category)}
                    >
                        {category
                            .replace(/_/g, ' ')
                            .replace(/\b\w/g, (c) => c.toUpperCase())}
                    </button>
                {/each}
                {#if hasActiveFilter}
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium text-muted-foreground transition-colors hover:text-primary"
                        onclick={clearFilters}
                    >
                        <X class="h-3 w-3" aria-hidden="true" />
                        Clear
                    </button>
                {/if}
            </div>

            <label class="inline-flex items-center gap-2 text-xs">
                <span
                    class="font-semibold uppercase tracking-[0.2em] text-muted-foreground"
                >
                    Sort
                </span>
                <span class="relative">
                    <select
                        value={currentSort}
                        onchange={(e) =>
                            setSort(
                                (e.currentTarget as HTMLSelectElement)
                                    .value as SortKey,
                            )}
                        class="h-8 appearance-none rounded-sm border border-border bg-background pl-3 pr-8 text-xs font-medium text-foreground/80 focus:border-primary/40 focus:outline-none"
                    >
                        {#each SORT_OPTIONS as opt (opt.key)}
                            <option value={opt.key}>{opt.label}</option>
                        {/each}
                    </select>
                    <ChevronDown
                        class="pointer-events-none absolute right-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                </span>
            </label>
        </div>

        <div class="mt-4 flex items-baseline justify-between text-xs text-muted-foreground">
            <span>
                {#if hasContent}
                    Showing <span class="text-foreground/80">{visibleStart}–{visibleEnd}</span>
                    of <span class="text-foreground/80">{totalCampaigns || pagination.total}</span> campaigns
                {:else}
                    <span class="text-foreground/80">0</span> campaigns
                {/if}
                {#if hasActiveFilter}
                    <span class="ml-1">
                        in <span class="text-foreground/80">{activeCategory
                                ?.replace(/_/g, ' ')
                                .replace(/\b\w/g, (c) => c.toUpperCase())}</span>
                    </span>
                {/if}
            </span>
        </div>
    </section>

    <!-- ═══ 5. GRID ═══ -->
    <section class="container pb-12 lg:pb-16">
        {#if hasContent}
            <div
                class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3"
            >
                {#each campaigns as campaign (campaign.id)}
                    <CampaignCard
                        {campaign}
                        href={`/campaigns/${campaign.slug}`}
                        adminEditHref={`/admin/campaigns/${campaign.id}/edit`}
                    />
                {/each}
            </div>
        {:else}
            <div
                class="mx-auto flex max-w-xl flex-col items-center gap-3 rounded-md border border-dashed border-border bg-ivory/60 p-12 text-center"
            >
                <Inbox
                    class="h-8 w-8 text-primary/60"
                    aria-hidden="true"
                />
                <p class="text-sm font-medium text-foreground/80">
                    No campaigns match this filter
                </p>
                <p class="text-xs text-muted-foreground">
                    Try a different category or clear the active filter to
                    see everything.
                </p>
                <button
                    type="button"
                    onclick={clearFilters}
                    class="mt-1 inline-flex h-8 items-center justify-center rounded-sm border border-border bg-background px-4 text-xs font-semibold uppercase tracking-[0.18em] text-foreground/80 transition-colors hover:border-primary/40"
                >
                    Clear filters
                </button>
            </div>
        {/if}
    </section>

    <!-- ═══ 6. PAGINATION ═══ -->
    {#if pagination.has_more || pagination.page > 1}
        <nav
            class="container flex items-center justify-between pb-12"
            aria-label="Pagination"
        >
            <Button
                variant="outline"
                size="sm"
                disabled={pagination.page <= 1}
                onclick={() =>
                    pushQuery({
                        category: activeCategory,
                        sort: currentSort,
                        page: String(pagination.page - 1),
                    })}
            >
                ← Previous
            </Button>
            <span class="text-sm text-muted-foreground">
                Page {pagination.page}
            </span>
            <Button
                variant="outline"
                size="sm"
                disabled={!pagination.has_more}
                onclick={() =>
                    pushQuery({
                        category: activeCategory,
                        sort: currentSort,
                        page: String(pagination.page + 1),
                    })}
            >
                Next →
            </Button>
        </nav>
    {/if}

    <BottomCtaBand
        title="Support the schools and the campus"
        body="Sustain daily annadanam at the schools — or move the proposed healing and service campus one step closer. Every donation is acknowledged with an official receipt."
        ctaLabel="Donate Now"
        ctaHref="/donate"
    />
</PublicLayout>
