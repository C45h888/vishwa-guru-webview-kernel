<script lang="ts">
    import type { HTMLAttributes } from 'svelte/elements';
    import { cn } from '$shared/lib/utils';

    type Variant = 'default' | 'destructive' | 'warning';

    type Props = HTMLAttributes<HTMLDivElement> & {
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
        default: 'bg-background text-foreground',
        destructive: 'border-destructive/50 text-destructive [&>svg]:text-destructive',
        warning: 'border-yellow-500/50 text-yellow-700 dark:text-yellow-300 [&>svg]:text-yellow-600',
    };
</script>

<div
    role="alert"
    class={cn(
        'relative w-full rounded-lg border p-4 [&>svg~*]:pl-7 [&>svg+div]:translate-y-[-3px] [&>svg]:absolute [&>svg]:left-4 [&>svg]:top-4 [&>svg]:h-4 [&>svg]:w-4',
        variantClasses[variant],
        className
    )}
    {...rest}
>
    {#if children}{@render children()}{/if}
</div>
