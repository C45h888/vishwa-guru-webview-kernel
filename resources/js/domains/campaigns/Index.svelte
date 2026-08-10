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
     * These canonical campaign images are staged in the public CMS media
     * directory. They are intentionally kept full-frame; the existing
     * square presentation handles the responsive slot without destructive
     * source cropping.
     */
    const PILLARS = [
        {
            src: '/storage/cms-media/LAND.jpg',
            alt: 'The land acquisition campaign',
            eyebrow: 'Current \u00b7 Stage 1',
            title: 'Acquire the land for the new campus',
            body: 'The trust is raising funds to acquire land near Nanjangud, outside Mysore. The land is under discussion; the acquisition requires additional capital. Until the land is secured, no construction begins.',
        },
        {
            src: '/storage/cms-media/GAUSHALA.jpg',
            alt: 'The proposed campus vision',
            eyebrow: 'Planned \u00b7 Stage 2',
            title: 'Build the Gaushala, temple, and healing environment',
            body: 'On the secured land, the trust intends to build a Gaushala for the care and protection of cows, a simple Shiva temple with Kamadhenu and Shiva-family shrines, and a disciplined healing environment. Construction begins after land acquisition closes.',
        },
        {
            src: '/storage/cms-media/WELFARE.jpg',
            alt: 'The long-term care of the cows',
            eyebrow: 'Planned \u00b7 Stage 3',
            title: 'Care and welfare of the cows',
            body: 'Once the campus is operational, the trust will focus on the long-term care and welfare of the cows resident at the Gaushala, with future plans for cow-related farming and products for devotees in accordance with the project\u2019s operating and legal framework.',
        },
    ];
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
                class="grid grid-cols-1 gap-6 sm:grid-cols-3 sm:gap-5 lg:gap-8"
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
                                    Browse all causes
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
                    A campaign is a specific call to action
                </h1>
                <div
                    class="space-y-4 text-base leading-relaxed text-muted-foreground lg:text-lg"
                >
                    <p>
                        Every campaign at {appName} names a specific stage of
                        the trust\u2019s work \u2014 the land acquisition, the
                        construction of the proposed campus, or the long-term
                        care of the cows. The current campaign is the land
                        acquisition. Each campaign has a clear goal, a clear
                        timeline, and a clear use for every rupee given.
                    </p>
                    <p>
                        Your offering goes to the campaign you choose, not to
                        a general fund. The trust treats each campaign as its
                        own ledger, with progress visible on every page. When
                        a campaign closes, the trust publishes how the
                        offerings were spent \u2014 so the giving stays accountable
                        and the next stage can begin.
                    </p>
                </div>

                <div class="pt-3">
                    <Button href="/donate" size="lg" variant="outline">
                        Donate to any cause
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
        title="Support the land acquisition campaign"
        body="Every contribution moves the proposed healing and service campus one step closer to breaking ground."
        ctaLabel="Donate Now"
        ctaHref="/donate"
    />
</PublicLayout>
