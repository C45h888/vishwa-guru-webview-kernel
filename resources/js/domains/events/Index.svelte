<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import EventCard from '$shared/components/EventCard.svelte';
    import MandalaDecoration from '$shared/components/MandalaDecoration.svelte';
    import TrustBadgeRow from '$shared/components/TrustBadgeRow.svelte';
    import BottomCtaBand from '$shared/components/BottomCtaBand.svelte';
    import { Button } from '$shared/ui/button';
    import type {
        EventSummaryProps,
        PaginationProps,
        AppPageProps,
    } from '$shared/lib/inertia';

    let {
        upcoming,
        past,
        pagination,
        appName,
    }: AppPageProps<{
        upcoming: EventSummaryProps[];
        past: EventSummaryProps[];
        pagination: PaginationProps;
    }> = $props();

    function goToPage(page: number) {
        router.get('/events', { page }, { preserveScroll: true });
    }

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

<svelte:head>
    <title>Events — {appName}</title>
</svelte:head>

<PublicLayout>
    <!-- HERO -->
    <section class="relative overflow-hidden bg-background">
        <div
            class="pointer-events-none absolute right-0 top-0 opacity-15"
            aria-hidden="true"
        >
            <MandalaDecoration size={180} tint="gold" />
        </div>

        <div class="container relative py-14 lg:py-20">
            <div class="mx-auto max-w-3xl space-y-5 text-center">
                <h1 class="font-serif text-4xl font-semibold lg:text-5xl">
                    Events
                </h1>
                <p class="text-base text-muted-foreground lg:text-lg">
                    Festivals, ceremonies, and community gatherings at the
                    temple.
                </p>
                <div class="pt-1">
                    <TrustBadgeRow />
                </div>
                <div
                    class="flex flex-wrap items-center justify-center gap-3 pt-2"
                >
                    <Button href="/donate" size="lg">
                        Donate to support festivals
                    </Button>
                    <Button href="/gallery" size="lg" variant="outline">
                        View gallery
                    </Button>
                </div>
            </div>
        </div>
    </section>

    <!-- UPCOMING -->
    <section class="container space-y-6 py-12 lg:py-16">
        <h2 class="font-serif text-3xl font-semibold lg:text-4xl">
            Upcoming
        </h2>
        {#if upcoming.length === 0}
            <p class="text-sm text-muted-foreground">
                No upcoming events scheduled. Follow our
                <a
                    href="/gallery"
                    class="font-medium text-primary hover:underline"
                >
                    gallery
                </a>
                for the latest.
            </p>
        {:else}
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                {#each upcoming as event (event.id)}
                    <EventCard {event} href={`/events/${event.slug}`} />
                {/each}
            </div>
        {/if}
    </section>

    <!-- PAST -->
    <section class="container space-y-6 py-12 lg:py-16">
        <div class="flex items-end justify-between gap-4">
            <h2 class="font-serif text-3xl font-semibold lg:text-4xl">
                Past events
            </h2>
            <span class="text-sm text-muted-foreground">
                {pagination.total} archived
            </span>
        </div>

        {#if past.length === 0}
            <p class="text-sm text-muted-foreground">
                No past events yet.
            </p>
        {:else}
            <div class="space-y-3">
                {#each past as event (event.id)}
                    <a
                        href={`/events/${event.slug}`}
                        class="flex flex-col gap-2 rounded-md border border-border/60 bg-background p-5 transition-colors hover:border-primary/40 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="space-y-1">
                            <h3
                                class="font-serif text-lg font-semibold"
                            >
                                {event.title}
                            </h3>
                            {#if event.short_description}
                                <p class="text-sm text-muted-foreground">
                                    {event.short_description}
                                </p>
                            {/if}
                        </div>
                        <div
                            class="text-sm text-muted-foreground sm:text-right"
                        >
                            <div class="font-medium">
                                {formatDate(event.starts_at, event.timezone)}
                            </div>
                            {#if event.venue}
                                <div class="text-xs">{event.venue}</div>
                            {/if}
                        </div>
                    </a>
                {/each}
            </div>

            {#if pagination.has_more || pagination.page > 1}
                <nav
                    class="flex items-center justify-between pt-4"
                    aria-label="Pagination"
                >
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={pagination.page <= 1}
                        onclick={() => goToPage(pagination.page - 1)}
                    >
                        ← Previous
                    </Button>
                    <span class="text-sm text-muted-foreground">
                        Page {pagination.page}
                    </span>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!pagination.has_more}
                        onclick={() => goToPage(pagination.page + 1)}
                    >
                        Next →
                    </Button>
                </nav>
            {/if}
        {/if}
    </section>

    <BottomCtaBand
        title="Support our events"
        body="Festival sponsorships and event seva keep our traditions alive."
        ctaLabel="Sponsor an event"
        ctaHref="/donate"
    />
</PublicLayout>