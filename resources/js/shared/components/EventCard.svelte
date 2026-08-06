<script lang="ts">
    import {
        Card,
        CardHeader,
        CardTitle,
        CardDescription,
        CardContent,
    } from '$shared/ui/card';
    import type { EventSummaryProps } from '$shared/lib/inertia';
    import PublicMediaImage from './PublicMediaImage.svelte';
    import AdminEditOverlay from './AdminEditOverlay.svelte';

    let {
        event,
        href = null,
        class: className = '',
        adminEditHref = null,
    }: {
        event: EventSummaryProps;
        href?: string | null;
        class?: string;
        /**
         * Phase 4: Admin Kernel — when set, the card surfaces a
         * floating pencil overlay in the top-right corner that links
         * to the admin edit page for this event.
         */
        adminEditHref?: string | null;
    } = $props();

    function formatDate(iso: string | null, tz: string): string {
        if (!iso) return '';
        try {
            return new Intl.DateTimeFormat('en-IN', {
                dateStyle: 'medium',
                timeStyle: 'short',
                timeZone: tz || 'Asia/Kolkata',
            }).format(new Date(iso));
        } catch {
            return iso;
        }
    }
</script>

<Card class={`group relative h-full overflow-hidden ${className}`}>
    {#if adminEditHref}
        <AdminEditOverlay href={adminEditHref} srLabel={`Edit event: ${event.title}`} />
    {/if}
    {#if event.banner_image}
        <PublicMediaImage media={event.banner_image} alt={event.title} class="aspect-[16/9] w-full object-cover" />
    {/if}
    <CardHeader>
        <CardTitle>
            {#if href}
                <a href={href} class="hover:underline">{event.title}</a>
            {:else}
                {event.title}
            {/if}
        </CardTitle>
        <CardDescription>
            {event.short_description ?? 'Event'}
        </CardDescription>
    </CardHeader>
    <CardContent class="space-y-2 text-sm">
        <div class="flex items-baseline justify-between">
            <span class="text-muted-foreground">When</span>
            <span class="font-medium">{formatDate(event.starts_at, event.timezone)}</span>
        </div>
        {#if event.venue}
            <div class="flex items-baseline justify-between">
                <span class="text-muted-foreground">Where</span>
                <span class="font-medium">{event.venue}</span>
            </div>
        {/if}
        <div class="flex items-baseline justify-between">
            <span class="text-muted-foreground">State</span>
            <span class="font-medium">{event.state}</span>
        </div>
    </CardContent>
</Card>
