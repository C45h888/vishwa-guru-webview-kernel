<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import CampaignCard from '$shared/components/CampaignCard.svelte';
    import MandalaDecoration from '$shared/components/MandalaDecoration.svelte';
    import TrustBadgeRow from '$shared/components/TrustBadgeRow.svelte';
    import BottomCtaBand from '$shared/components/BottomCtaBand.svelte';
    import { Button } from '$shared/ui/button';
    import type {
        CampaignSummaryProps,
        PaginationProps,
        AppPageProps,
    } from '$shared/lib/inertia';

    let {
        campaigns,
        pagination,
        appName,
    }: AppPageProps<{
        campaigns: CampaignSummaryProps[];
        pagination: PaginationProps;
    }> = $props();

    const featuredCampaigns = $derived(
        campaigns.filter((c) => c.is_featured),
    );
    const otherCampaigns = $derived(
        campaigns.filter((c) => !c.is_featured),
    );
    const hasContent = $derived(campaigns.length > 0);

    function goToPage(page: number) {
        router.get('/campaigns', { page }, { preserveScroll: true });
    }
</script>

<svelte:head>
    <title>Campaigns — {appName}</title>
</svelte:head>

<PublicLayout>
    <!-- HERO -->
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
                    Campaigns
                </h1>
                <p class="text-base text-muted-foreground lg:text-lg">
                    Support causes that sustain the temple — pooja, annadanam,
                    renovation, education.
                </p>
                <div class="pt-1">
                    <TrustBadgeRow />
                </div>
                <div
                    class="flex flex-wrap items-center justify-center gap-3 pt-2"
                >
                    <Button href="/donate" size="lg">
                        Donate to any cause
                    </Button>
                    <Button href="/gallery" size="lg" variant="outline">
                        View gallery
                    </Button>
                </div>
            </div>
        </div>
    </section>

    {#if hasContent}
        <!-- FEATURED STRIP -->
        {#if featuredCampaigns.length > 0}
            <section class="container space-y-6 py-12 lg:py-16">
                <h2 class="font-serif text-3xl font-semibold lg:text-4xl">
                    Featured causes
                </h2>
                <div
                    class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3"
                >
                    {#each featuredCampaigns as campaign (campaign.id)}
                        <CampaignCard
                            {campaign}
                            href={`/campaigns/${campaign.slug}`}
                        />
                    {/each}
                </div>
            </section>
        {/if}

        <!-- ALL CAMPAIGNS -->
        {#if otherCampaigns.length > 0}
            <section class="container space-y-6 py-12 lg:py-16">
                <h2 class="font-serif text-3xl font-semibold lg:text-4xl">
                    All campaigns
                    <span
                        class="ml-2 text-base font-normal text-muted-foreground"
                    >
                        {pagination.total}
                    </span>
                </h2>
                <div
                    class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3"
                >
                    {#each otherCampaigns as campaign (campaign.id)}
                        <CampaignCard
                            {campaign}
                            href={`/campaigns/${campaign.slug}`}
                        />
                    {/each}
                </div>
            </section>
        {/if}

        <!-- PAGINATION -->
        {#if pagination.has_more || pagination.page > 1}
            <nav
                class="container flex items-center justify-between pb-12"
                aria-label="Pagination"
            >
                <Button
                    variant="outline"
                    size="sm"
                    disabled={pagination.page <= 1}
                    onclick={() => goToPage(pagination.page - 1)}
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
                    onclick={() => goToPage(pagination.page + 1)}
                >
                    Next →
                </Button>
            </nav>
        {/if}
    {:else}
        <section class="container py-16 lg:py-20">
            <div
                class="mx-auto max-w-xl rounded-md border border-dashed border-border bg-muted/30 p-8 text-center"
            >
                <p class="text-sm text-muted-foreground">
                    No campaigns yet. The site is being prepared.
                </p>
            </div>
        </section>
    {/if}

    <BottomCtaBand
        title="Support a cause today"
        body="Every contribution sustains daily pooja, annadanam, and temple maintenance."
        ctaLabel="Donate Now"
        ctaHref="/donate"
    />
</PublicLayout>