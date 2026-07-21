<script lang="ts">
    import type { HTMLLabelAttributes } from 'svelte/elements';
    import { cn } from '$shared/lib/utils';

    type Props = HTMLLabelAttributes & {
        class?: string;
        children?: import('svelte').Snippet;
    };

    let { class: className = '', children, ...rest }: Props = $props();
</script>

<label
    class={cn(
        'text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70',
        className
    )}
    {...rest}
>
    {#if children}{@render children()}{/if}
</label>
