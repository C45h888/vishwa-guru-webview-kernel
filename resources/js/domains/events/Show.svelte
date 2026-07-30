<script lang="ts">
    import { Card, CardContent, CardHeader, CardTitle } from '$shared/ui/card';
    import EventCard from '$shared/components/EventCard.svelte';
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import type {
        EventSummaryProps,
        AppPageProps,
    } from '$shared/lib/inertia';

    interface EventDetailProps extends EventSummaryProps {
        description?: string | null;
        published_at?: string | null;
        completed_at?: string | null;
        display_order: number;
        related?: EventSummaryProps[];
        created_at?: string;
        metadata?: Record<string, unknown>;
    }

    let {
        event,
        appName,
    }: AppPageProps<{ event: EventDetailProps }> = $props();

    const related = $derived(event.related ?? []);
</script>

<svelte:head>
    <title>{event.title} — {appName}</title>
    {#if event.short_description}
        <meta name="description" content={event.short_description} />
    {/if}
</svelte:head>

<PublicLayout>
    <article class="space-y-6">
        <header class="space-y-2">
            <h1 class="text-3xl font-semibold">{event.title}</h1>
            {#if event.short_description}
                <p class="text-sm text-muted-foreground">{event.short_description}</p>
            {/if}
            <div class="flex flex-wrap gap-2 text-xs uppercase tracking-wide">
                <span class="rounded-full bg-secondary px-2 py-0.5">{event.state}</span>
                {#if event.is_upcoming}
                    <span class="rounded-full bg-primary px-2 py-0.5 text-primary-foreground">Upcoming</span>
                {/if}
            </div>
        </header>

        <Card>
            <CardHeader>
                <CardTitle>When &amp; where</CardTitle>
            </CardHeader>
            <CardContent class="space-y-2 text-sm">
                {#if event.venue}
                    <p><span class="text-muted-foreground">Venue:</span> {event.venue}</p>
                {/if}
                {#if event.venue_address}
                    <p><span class="text-muted-foreground">Address:</span> {event.venue_address}</p>
                {/if}
                <p><span class="text-muted-foreground">Timezone:</span> {event.timezone}</p>
            </CardContent>
        </Card>

        {#if event.description}
            <section aria-labelledby="description-title" class="prose max-w-none">
                <h2 id="description-title" class="text-xl font-semibold">About this event</h2>
                <p>{event.description}</p>
            </section>
        {/if}

        {#if related.length > 0}
            <section aria-labelledby="related-title" class="space-y-3">
                <h2 id="related-title" class="text-xl font-semibold">Related events</h2>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    {#each related as rel (rel.id)}
                        <EventCard event={rel} href={`/events/${rel.slug}`} />
                    {/each}
                </div>
            </section>
        {/if}
    </article>
</PublicLayout>
