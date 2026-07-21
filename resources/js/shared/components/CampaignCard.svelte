<script lang="ts">
    import {
        Card,
        CardHeader,
        CardTitle,
        CardDescription,
        CardContent,
    } from '$shared/ui/card';
    import Money from './Money.svelte';
    import type { CampaignSummaryProps } from '$shared/lib/inertia';

    let {
        campaign,
        href = null,
        class: className = '',
    }: {
        campaign: CampaignSummaryProps;
        href?: string | null;
        class?: string;
    } = $props();
</script>

<Card class={`h-full ${className}`}>
    <CardHeader>
        <CardTitle>
            {#if href}
                <a href={href} class="hover:underline">{campaign.title}</a>
            {:else}
                {campaign.title}
            {/if}
        </CardTitle>
        <CardDescription>
            {campaign.short_description ?? 'Campaign'}
        </CardDescription>
    </CardHeader>
    <CardContent class="space-y-2 text-sm">
        {#if campaign.target_amount_minor !== null}
            <div class="flex items-baseline justify-between">
                <span class="text-muted-foreground">Target</span>
                <span class="font-medium">
                    <Money
                        amountMinor={campaign.target_amount_minor}
                        currencyCode={campaign.currency_code}
                    />
                </span>
            </div>
        {/if}
        <div class="flex items-baseline justify-between">
            <span class="text-muted-foreground">State</span>
            <span class="font-medium">{campaign.state}</span>
        </div>
        {#if campaign.is_featured}
            <div class="text-xs uppercase tracking-wide text-primary">Featured</div>
        {/if}
    </CardContent>
</Card>
