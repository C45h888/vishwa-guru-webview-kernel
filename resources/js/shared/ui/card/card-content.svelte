<script lang="ts">
    import type { HTMLAttributes } from 'svelte/elements';
    import { cn } from '$shared/lib/utils';

    type Props = HTMLAttributes<HTMLElement> & {
        class?: string;
        children?: import('svelte').Snippet;
    };

    let { class: className = '', children, ...rest }: Props = $props();
</script>

<div class={cn('p-6 pt-0', className)} {...rest}>
    {#if children}{@render children()}{/if}
</div>
