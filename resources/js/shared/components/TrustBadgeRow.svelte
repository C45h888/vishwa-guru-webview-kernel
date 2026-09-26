<script lang="ts">
    /**
     * TrustBadgeRow — small cluster of trust signals that appear next to
     * CTAs across the public site. Rendered in the campaign hero, the
     * donate flow, etc.
     *
     * Pass `tone="inverse"` when the row sits on a dark background
     * (e.g. the campaign hero overlay). Default is the muted ink colour
     * for ivory backgrounds.
     */
    import { Shield } from 'lucide-svelte';

    interface Props {
        tone?: 'default' | 'inverse';
    }

    let { tone = 'default' }: Props = $props();

    const signals = ['Razorpay Secure', 'Nanjangud, Karnataka'];

    const inkClass = $derived(
        tone === 'inverse' ? 'text-white/75' : 'text-muted-foreground',
    );
    const iconClass = $derived(
        tone === 'inverse' ? 'text-white/70' : 'text-primary/70',
    );
    const dividerClass = $derived(
        tone === 'inverse' ? 'text-white/30' : 'text-border',
    );
</script>

<div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-2 text-sm {inkClass} sm:justify-start">
    {#each signals as label, i (label)}
        <span class="inline-flex items-center gap-1.5">
            <Shield class="h-3.5 w-3.5 {iconClass}" aria-hidden="true" />
            <span>{label}</span>
        </span>
        {#if i < signals.length - 1}
            <span class="{dividerClass}" aria-hidden="true">·</span>
        {/if}
    {/each}
</div>
