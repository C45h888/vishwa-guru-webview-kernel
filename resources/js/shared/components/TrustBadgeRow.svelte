<script lang="ts">
    import { Shield } from 'lucide-svelte';

    const signals = ['80G Tax Benefit', 'Razorpay Secure', 'Registered Trust'];
</script>

<div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-2 text-sm text-muted-foreground">
    {#each signals as label, i (label)}
        <span class="inline-flex items-center gap-1.5">
            <Shield class="h-3.5 w-3.5 text-primary/70" aria-hidden="true" />
            <span>{label}</span>
        </span>
        {#if i < signals.length - 1}
            <span class="text-border" aria-hidden="true">·</span>
        {/if}
    {/each}
</div>