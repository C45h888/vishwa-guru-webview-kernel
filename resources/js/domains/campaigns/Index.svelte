<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import CampaignCard from '$shared/components/CampaignCard.svelte';
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

    function goToPage(page: number) {
        router.get('/campaigns', { page }, { preserveScroll: true });
    }
</script>

<svelte:head>
    <title>Campaigns — {appName}</title>
</svelte:head>

<PublicLayout>
    <section class="space-y-6">
        <header class="space-y-2">
            <h1 class="text-3xl font-semibold">Campaigns</h1>
            <p class="text-sm text-muted-foreground">
                {pagination.total} active campaign{pagination.total === 1 ? '' : 's'}
            </p>
        </header>

        {#if campaigns.length === 0}
            <p class="text-sm text-muted-foreground">
                No campaigns yet. The site is being prepared.
            </p>
        {:else}
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                {#each campaigns as campaign (campaign.id)}
                    <CampaignCard
                        {campaign}
                        href={`/campaigns/${campaign.slug}`}
                    />
                {/each}
            </div>

            {#if pagination.has_more || pagination.page > 1}
                <nav class="flex items-center justify-between" aria-label="Pagination">
                    <button
                        type="button"
                        class="text-sm disabled:opacity-50"
                        disabled={pagination.page <= 1}
                        onclick={() => goToPage(pagination.page - 1)}
                    >
                        ← Previous
                    </button>
                    <span class="text-sm text-muted-foreground">
                        Page {pagination.page}
                    </span>
                    <button
                        type="button"
                        class="text-sm disabled:opacity-50"
                        disabled={!pagination.has_more}
                        onclick={() => goToPage(pagination.page + 1)}
                    >
                        Next →
                    </button>
                </nav>
            {/if}
        {/if}
    </section>
</PublicLayout>
