<script lang="ts">
    import type { HTMLAttributes } from 'svelte/elements';
    import { cn } from '$shared/lib/utils';

    type Props = HTMLAttributes<HTMLElement> & {
        class?: string;
        orientation?: 'horizontal' | 'vertical';
    };

    let {
        class: className = '',
        orientation = 'horizontal',
        ...rest
    }: Props = $props();
</script>

<div
    role="separator"
    aria-orientation={orientation}
    class={cn(
        'shrink-0 bg-border',
        orientation === 'horizontal' ? 'h-[1px] w-full' : 'h-full w-[1px]',
        className
    )}
    {...rest}
></div>
