<script lang="ts">
    import {
        Card,
        CardHeader,
        CardTitle,
        CardDescription,
        CardContent,
    } from '$shared/ui/card';
    import type { GallerySummaryProps } from '$shared/lib/inertia';

    let {
        gallery,
        href = null,
        class: className = '',
    }: {
        gallery: GallerySummaryProps;
        href?: string | null;
        class?: string;
    } = $props();
</script>

<Card class={`h-full ${className}`}>
    <CardHeader>
        <CardTitle>
            {#if href}
                <a href={href} class="hover:underline">{gallery.title}</a>
            {:else}
                {gallery.title}
            {/if}
        </CardTitle>
        <CardDescription>
            {gallery.short_description ?? 'Gallery'}
        </CardDescription>
    </CardHeader>
    <CardContent class="space-y-2 text-sm">
        <div class="flex items-baseline justify-between">
            <span class="text-muted-foreground">Photos</span>
            <!-- image_count badge wrapped in a span; will be replaced with
                 shadcn Badge primitive in commit 10. -->
            <span
                class="inline-flex items-center rounded-full bg-secondary px-2 py-0.5 text-xs font-medium"
                aria-label={`${gallery.image_count} photos`}
            >
                {gallery.image_count}
            </span>
        </div>
        {#if gallery.is_featured}
            <div class="text-xs uppercase tracking-wide text-primary">Featured</div>
        {/if}
    </CardContent>
</Card>
