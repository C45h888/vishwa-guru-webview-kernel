<script lang="ts">
    import { ImagePlus } from 'lucide-svelte';

    interface Props {
        icon?: typeof ImagePlus;
        title: string;
        hint?: string;
        aspectRatio?: 'video' | 'square' | 'portrait' | 'banner';
        compact?: boolean;
    }

    let {
        icon: Icon = ImagePlus,
        title,
        hint = 'Will appear here once content is published',
        aspectRatio = 'video',
        compact = false,
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
</script>

<div
    class="group relative overflow-hidden rounded-md border border-dashed border-primary/30 bg-gradient-to-br from-primary/[0.07] via-muted/40 to-primary/[0.04] transition-all hover:border-primary/50 hover:shadow-md"
>
    <div
        class="{aspectClass} flex flex-col items-center justify-center gap-2 p-6 text-center"
    >
        <div
            class="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary/60 transition-transform duration-500 group-hover:scale-110 group-hover:rotate-3"
        >
            <Icon class="h-6 w-6" />
        </div>
        <p class="text-sm font-medium text-muted-foreground">{title}</p>
        {#if !compact}
            <p class="max-w-[200px] text-xs text-muted-foreground/60">
                {hint}
            </p>
        {/if}
    </div>
    <div
        class="absolute right-2 top-2 rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-primary/70"
    >
        Placeholder
    </div>
</div>