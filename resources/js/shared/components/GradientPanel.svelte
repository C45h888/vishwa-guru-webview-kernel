<script lang="ts">
    interface Props {
        aspectRatio?: 'video' | 'square' | 'portrait' | 'banner';
        variant?: 'gold' | 'ivory' | 'warm';
        label?: string;
        class?: string;
    }

    let {
        aspectRatio = 'video',
        variant = 'gold',
        label,
        class: className = '',
    }: Props = $props();

    const aspectClass = $derived(
        aspectRatio === 'square'
            ? 'aspect-square'
            : aspectRatio === 'portrait'
              ? 'aspect-[3/4]'
              : aspectRatio === 'banner'
                ? 'aspect-[21/9]'
                : 'aspect-video',
    );

    const gradientClass = $derived(
        variant === 'ivory'
            ? 'bg-gradient-to-br from-ivory via-muted to-ivory'
            : variant === 'warm'
              ? 'bg-gradient-to-br from-muted via-primary/10 to-muted'
              : 'bg-gradient-to-br from-primary/15 via-ivory to-primary/15',
    );
</script>

<div
    class="relative overflow-hidden rounded-md border border-border/40 {gradientClass} {className}"
>
    <div class="{aspectClass} flex items-center justify-center">
        {#if label}
            <div
                class="rounded-full border border-primary/30 bg-primary/5 px-3 py-1 text-xs font-medium text-primary/70"
            >
                {label}
            </div>
        {/if}
    </div>
    <div
        class="pointer-events-none absolute inset-0 opacity-30"
        style="background: radial-gradient(circle at 30% 20%, hsl(42 70% 48% / 0.08), transparent 50%), radial-gradient(circle at 70% 80%, hsl(42 70% 48% / 0.06), transparent 50%);"
        aria-hidden="true"
    ></div>
</div>