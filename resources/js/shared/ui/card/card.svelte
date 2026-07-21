<script lang="ts">
    import type { HTMLAttributes } from 'svelte/elements';
    import { cn } from '$shared/lib/utils';

    type Props = HTMLAttributes<HTMLElement> & {
        class?: string;
        children?: import('svelte').Snippet;
    };

    let { class: className = '', children, ...rest }: Props = $props();
</script>

<div
    class={cn(
        'rounded-lg border bg-card text-card-foreground shadow-sm',
        className
    )}
    {...rest}
>
    {#if children}{@render children()}{/if}
</div>
