<script lang="ts">
    import type { HTMLAnchorAttributes, HTMLButtonAttributes } from 'svelte/elements';
    import { cn } from '$shared/lib/utils';

    type Variant = 'default' | 'ghost' | 'outline' | 'secondary' | 'destructive' | 'link';
    type Size = 'default' | 'sm' | 'lg' | 'icon';

    type Props = (HTMLButtonAttributes | HTMLAnchorAttributes) & {
        variant?: Variant;
        size?: Size;
        href?: string;
        class?: string;
        children?: import('svelte').Snippet;
    };

    let {
        variant = 'default',
        size = 'default',
        href = undefined,
        class: className = '',
        children,
        ...rest
    }: Props = $props();

    const variantClasses: Record<Variant, string> = {
        default: 'bg-primary text-primary-foreground hover:bg-primary/90',
        ghost: 'hover:bg-accent hover:text-accent-foreground',
        outline: 'border border-input bg-background hover:bg-accent hover:text-accent-foreground',
        secondary: 'bg-secondary text-secondary-foreground hover:bg-secondary/80',
        destructive: 'bg-destructive text-destructive-foreground hover:bg-destructive/90',
        link: 'text-primary underline-offset-4 hover:underline',
    };

    const sizeClasses: Record<Size, string> = {
        default: 'h-10 px-4 py-2',
        sm: 'h-9 rounded-md px-3 text-sm',
        lg: 'h-11 rounded-md px-8',
        icon: 'h-10 w-10',
    };

    const base = 'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50';
</script>

{#if href}
    <a {href} class={cn(base, variantClasses[variant], sizeClasses[size], className)} {...rest as HTMLAnchorAttributes}>
        {#if children}{@render children()}{/if}
    </a>
{:else}
    <button class={cn(base, variantClasses[variant], sizeClasses[size], className)} {...rest as HTMLButtonAttributes}>
        {#if children}{@render children()}{/if}
    </button>
{/if}
