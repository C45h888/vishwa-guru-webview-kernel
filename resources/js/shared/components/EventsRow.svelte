<script lang="ts">
    import EventCard from './EventCard.svelte';
    import PlaceholderCard from './PlaceholderCard.svelte';
    import { CalendarDays } from 'lucide-svelte';
    import type { EventSummaryProps } from '$shared/lib/inertia';

    /**
     * Phase 4: Admin Kernel — accept an optional adminEditHrefFn that
     * produces the per-event edit URL when the current user is the
     * canonical admin. The parent (cms/Home.svelte) gates the function
     * itself; EventsRow is just a pass-through so EventCard can render
     * its AdminEditOverlay.
     */
    let {
        events,
        adminEditHrefFn = null,
    }: {
        events: EventSummaryProps[];
        adminEditHrefFn?: ((event: EventSummaryProps) => string | null) | null;
    } = $props();

    const hasEvents = $derived(events.length > 0);
</script>

<section class="container py-16 lg:py-20">
    <div class="mb-8 flex items-end justify-between gap-4">
        <div class="space-y-2">
            <p
                class="text-xs font-semibold uppercase tracking-[0.2em] text-primary"
            >
                Events
            </p>
            <h2 class="font-serif text-3xl font-semibold lg:text-4xl">
                Upcoming at the temple
            </h2>
        </div>
        <a
            href="/events"
            class="hidden text-sm font-medium text-primary hover:underline sm:inline"
        >
            View all events →
        </a>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
        {#if hasEvents}
            {#each events.slice(0, 3) as event (event.id)}
                <EventCard
                    {event}
                    href={`/events/${event.slug}`}
                    adminEditHref={adminEditHrefFn?.(event) ?? null}
                />
            {/each}
        {:else}
            {#each [1, 2, 3] as slot (slot)}
                <PlaceholderCard
                    icon={CalendarDays}
                    title="Event placeholder"
                    hint="An upcoming event will appear here when published"
                />
            {/each}
        {/if}
    </div>
</section>
