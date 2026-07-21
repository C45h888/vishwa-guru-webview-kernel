<script lang="ts">
    import type { HTMLAttributes } from 'svelte/elements';
    import { cn } from '$shared/lib/utils';

    type Props = HTMLAttributes<HTMLParagraphElement> & {
        class?: string;
        children?: import('svelte').Snippet;
    };

    let { class: className = '', children, ...rest }: Props = $props();
</script>

<p
    class={cn('text-sm text-muted-foreground', className)}
    {...rest}
>
    {#if children}{@render children()}{/if}
</p>
