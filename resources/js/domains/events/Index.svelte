<script lang="ts">
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import EventHeroSlideshow from '$shared/components/EventHeroSlideshow.svelte';
    import EventCard from '$shared/components/EventCard.svelte';
    import BottomCtaBand from '$shared/components/BottomCtaBand.svelte';
    import { EVENT_SLIDES } from '$domains/events/event-slides';
    import { Pencil } from 'lucide-svelte';
    import { page } from '@inertiajs/svelte';
    import type { AppPageProps, EventSummaryProps } from '$shared/lib/inertia';
    import SeoHead from '$shared/components/SeoHead.svelte';

    let {
        upcoming,
        past,
        pagination,
        appName,
        appUrl,
    }: AppPageProps<{
        upcoming: EventSummaryProps[];
        past: EventSummaryProps[];
        pagination: { page: number; per_page: number; total: number; has_more: boolean };
    }> = $props();

    const hasUpcoming = $derived(upcoming.length > 0);
    const hasPast = $derived(past.length > 0);
    const isAdmin = $derived($page.props.authUser?.role === 'admin');
</script>

<SeoHead />

<PublicLayout>
    {#if isAdmin}
        <section class="border-y border-border/40 bg-background">
            <div class="container flex items-center justify-between py-3 text-xs text-muted-foreground">
                <nav aria-label="Breadcrumb">
                    <ol class="flex items-center gap-1">
                        <li><a href="/" class="hover:text-foreground">Home</a></li>
                        <li aria-hidden="true">›</li>
                        <li aria-current="page" class="text-foreground">Events</li>
                    </ol>
                </nav>
                <a
                    href="/admin/events/new"
                    class="inline-flex items-center gap-1.5 rounded-md border border-primary/30 bg-primary/5 px-2.5 py-1 font-medium text-primary transition-colors hover:bg-primary/10"
                >
                    <Pencil class="h-3 w-3" />
                    <span>New event</span>
                </a>
            </div>
        </section>
    {/if}
    <EventHeroSlideshow slides={EVENT_SLIDES} />

    {#if hasUpcoming}
        <section class="container space-y-6 py-12 lg:py-16">
            <div class="space-y-2">
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-primary">
                    What's ahead
                </p>
                <h2 class="font-serif text-3xl font-semibold lg:text-4xl">
                    Upcoming events
                </h2>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                {#each upcoming as event (event.id)}
                    <EventCard
                        {event}
                        href={`/events/${event.slug}`}
                        adminEditHref={isAdmin ? `/admin/events/${event.id}/edit` : null}
                    />
                {/each}
            </div>
        </section>
    {/if}

    <section class="container space-y-6 py-12 lg:py-16">
        <div class="space-y-2">
            <p class="text-xs font-semibold uppercase tracking-[0.25em] text-primary">
                Past gatherings
            </p>
            <h2 class="font-serif text-3xl font-semibold lg:text-4xl">
                Past events
            </h2>
        </div>

        {#if hasPast}
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                {#each past as event (event.id)}
                    <EventCard
                        {event}
                        href={`/events/${event.slug}`}
                        adminEditHref={isAdmin ? `/admin/events/${event.id}/edit` : null}
                    />
                {/each}
            </div>
        {:else}
            <p class="text-sm text-muted-foreground">
                No past events yet.
            </p>
        {/if}
    </section>

    <BottomCtaBand
        title="Project updates and community moments"
        body="The trust shares its progress on the land acquisition campaign and marks the year with devotional and community events."
        ctaLabel="Support the land acquisition"
        ctaHref="/donate"
    />
</PublicLayout>
