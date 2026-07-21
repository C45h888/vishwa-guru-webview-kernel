<script lang="ts">
    import type { HTMLAttributes } from 'svelte/elements';
    import { cn } from '$shared/lib/utils';

    type Props = HTMLAttributes<HTMLHeadingElement> & {
        class?: string;
        children?: import('svelte').Snippet;
    };

    let { class: className = '', children, ...rest }: Props = $props();
</script>

<h3
    class={cn('text-2xl font-semibold leading-none tracking-tight', className)}
    {...rest}
>
    {#if children}{@render children()}{/if}
</h3>
