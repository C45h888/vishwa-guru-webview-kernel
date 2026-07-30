<script lang="ts">
    import GradientPanel from './GradientPanel.svelte';
    import PublicMediaImage from './PublicMediaImage.svelte';
    import type { PublicMediaProps } from '$shared/lib/inertia';

    interface Props {
        eyebrow: string;
        title: string;
        body: string;
        image?: PublicMediaProps | null;
        altText?: string;
        reverse?: boolean;
        variant?: 'gold' | 'ivory' | 'warm';
        aspectRatio?: 'video' | 'square' | 'portrait';
    }

    let {
        eyebrow,
        title,
        body,
        image = null,
        altText,
        reverse = false,
        variant = 'gold',
        aspectRatio = 'video',
    }: Props = $props();
</script>

<section class="py-16 lg:py-20">
    <div class="container">
        <div
            class="grid grid-cols-1 items-center gap-10 lg:gap-16 {reverse
                ? 'lg:grid-flow-col-dense'
                : ''}"
        >
            <div class={reverse ? 'lg:col-start-2' : ''}>
                {#if image}
                    <div
                        class="overflow-hidden rounded-md border border-border/40"
                    >
                        <PublicMediaImage
                            media={image}
                            alt={altText ?? image.alt_text ?? title}
                            class="aspect-video w-full object-cover"
                        />
                    </div>
                {:else}
                    <GradientPanel {aspectRatio} {variant} />
                {/if}
            </div>
            <div
                class="space-y-4 lg:max-w-xl {reverse
                    ? 'lg:col-start-1 lg:row-start-1'
                    : ''}"
            >
                <p
                    class="text-xs font-semibold uppercase tracking-[0.2em] text-primary"
                >
                    {eyebrow}
                </p>
                <h3 class="font-serif text-2xl font-semibold lg:text-3xl">
                    {title}
                </h3>
                <p class="text-base leading-relaxed text-muted-foreground">
                    {body}
                </p>
            </div>
        </div>
    </div>
</section>