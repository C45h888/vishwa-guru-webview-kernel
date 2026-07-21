<script lang="ts">
    import type { HTMLAttributes } from 'svelte/elements';
    import { cn } from '$shared/lib/utils';

    type Variant = 'default' | 'secondary' | 'outline' | 'destructive';

    type Props = HTMLAttributes<HTMLSpanElement> & {
        variant?: Variant;
        class?: string;
        children?: import('svelte').Snippet;
    };

    let {
        variant = 'default',
        class: className = '',
        children,
        ...rest
    }: Props = $props();

    const variantClasses: Record<Variant, string> = {
        default: 'border-transparent bg-primary text-primary-foreground hover:bg-primary/80',
        secondary: 'border-transparent bg-secondary text-secondary-foreground hover:bg-secondary/80',
        outline: 'text-foreground',
        destructive: 'border-transparent bg-destructive text-destructive-foreground hover:bg-destructive/80',
    };
</script>

<span
    class={cn(
        'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2',
        variantClasses[variant],
        className
    )}
    {...rest}
>
    {#if children}{@render children()}{/if}
</span>
