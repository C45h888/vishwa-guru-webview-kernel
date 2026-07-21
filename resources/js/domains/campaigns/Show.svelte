<script lang="ts">
    import { Card, CardContent, CardHeader, CardTitle } from '$shared/ui/card';
    import Money from '$shared/components/Money.svelte';
    import CampaignProgress from '$shared/components/CampaignProgress.svelte';
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import type {
        CampaignDetailProps,
        CampaignProgressProps,
        AppPageProps,
    } from '$shared/lib/inertia';

    let {
        campaign,
        progress,
        appName,
    }: AppPageProps<{
        campaign: CampaignDetailProps;
        progress: CampaignProgressProps[];
    }> = $props();

    // One bar per currency. The controller does not pair progress rows
    // with the campaign's target_amount_minor by currency (the schema
    // has one campaign-level target); for currencies other than the
    // campaign's own currency_code we render against the per-currency
    // raised amount only.
    const targetByCurrency = $derived({
        [campaign.currency_code]: campaign.target_amount_minor,
    } as Record<string, number | null>);
</script>

<svelte:head>
    <title>{campaign.title} — {appName}</title>
    {#if campaign.short_description}
        <meta name="description" content={campaign.short_description} />
    {/if}
</svelte:head>

<PublicLayout>
    <article class="space-y-6">
        <header class="space-y-2">
            <h1 class="text-3xl font-semibold">{campaign.title}</h1>
            {#if campaign.short_description}
                <p class="text-sm text-muted-foreground">{campaign.short_description}</p>
            {/if}
            <div class="flex flex-wrap gap-2 text-xs uppercase tracking-wide">
                <span class="rounded-full bg-secondary px-2 py-0.5">{campaign.state}</span>
                {#if campaign.is_active}
                    <span class="rounded-full bg-secondary px-2 py-0.5">Active</span>
                {/if}
                {#if campaign.is_featured}
                    <span class="rounded-full bg-primary px-2 py-0.5 text-primary-foreground">Featured</span>
                {/if}
            </div>
        </header>

        <Card>
            <CardHeader>
                <CardTitle>Progress</CardTitle>
            </CardHeader>
            <CardContent>
                {#if progress.length === 0}
                    <p class="text-sm text-muted-foreground">
                        No donations yet for this campaign.
                    </p>
                {:else}
                    <CampaignProgress {progress} {targetByCurrency} class="space-y-4" />
                {/if}
            </CardContent>
        </Card>

        {#if campaign.description}
            <section aria-labelledby="description-title" class="prose max-w-none">
                <h2 id="description-title" class="text-xl font-semibold">About this campaign</h2>
                <p>{campaign.description}</p>
            </section>
        {/if}

        <Card>
            <CardHeader>
                <CardTitle>Contribute</CardTitle>
            </CardHeader>
            <CardContent class="space-y-2 text-sm">
                <p>
                    Target:
                    {#if campaign.target_amount_minor !== null}
                        <Money
                            amountMinor={campaign.target_amount_minor}
                            currencyCode={campaign.currency_code}
                        />
                    {:else}
                        <span class="text-muted-foreground">not set</span>
                    {/if}
                </p>
                <a
                    href={`/donate?campaign=${campaign.slug}`}
                    class="inline-flex items-center rounded-md bg-primary px-4 py-2 font-medium text-primary-foreground hover:opacity-90"
                >
                    Donate to this campaign
                </a>
            </CardContent>
        </Card>
    </article>
</PublicLayout>
