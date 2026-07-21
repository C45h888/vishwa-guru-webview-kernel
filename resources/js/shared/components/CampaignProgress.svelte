<script lang="ts">
    import Money from './Money.svelte';
    import { percentOf } from '$shared/lib/utils';
    import type { CampaignProgressProps } from '$shared/lib/inertia';

    /**
     * One progress bar per CampaignProgressDTO row.
     *
     * `progress: CampaignProgressProps[]` is the array emitted by
     * CampaignsQueryContract::progressFor() — one entry per currency_code
     * present in successful donations. `targetMinor` is the campaign's
     * `target_amount_minor` for this same currency (the controller does
     * not pair them; the UI does). Pass a Map keyed by currency_code.
     */
    let {
        progress,
        targetByCurrency = {},
        class: className = '',
    }: {
        progress: CampaignProgressProps[];
        targetByCurrency?: Record<string, number | null>;
        class?: string;
    } = $props();
</script>

{#each progress as row (row.currency_code)}
    {@const target = targetByCurrency[row.currency_code] ?? null}
    {@const pct = percentOf(row.raised_amount_minor, target)}
    <div class={`space-y-1 ${className}`}>
        <div class="flex items-baseline justify-between text-sm">
            <span class="font-medium">{row.currency_code}</span>
            <span class="text-muted-foreground">
                <Money amountMinor={row.raised_amount_minor} currencyCode={row.currency_code} />
                {#if target !== null}
                    / <Money amountMinor={target} currencyCode={row.currency_code} />
                {/if}
            </span>
        </div>
        <div
            class="h-2 w-full overflow-hidden rounded-full bg-secondary"
            role="progressbar"
            aria-valuenow={pct}
            aria-valuemin="0"
            aria-valuemax="100"
            aria-label={`${row.currency_code} ${pct}%`}
        >
            <div
                class="h-full bg-primary transition-all"
                style:width={`${pct}%`}
            ></div>
        </div>
        <div class="flex items-baseline justify-between text-xs text-muted-foreground">
            <span>{pct}% of target</span>
            <span>
                {row.donation_count} donation{row.donation_count === 1 ? '' : 's'}
                · {row.distinct_donor_count} donor{row.distinct_donor_count === 1 ? '' : 's'}
            </span>
        </div>
    </div>
{/each}
