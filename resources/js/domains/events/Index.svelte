<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import EventCard from '$shared/components/EventCard.svelte';
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
</script>

<svelte:head>
    <title>Events — {appName}</title>
</svelte:head>

<PublicLayout>
    <div class="space-y-12">
        <section aria-labelledby="upcoming-title" class="space-y-4">
            <header class="space-y-1">
                <h1 id="upcoming-title" class="text-3xl font-semibold">Upcoming events</h1>
                <p class="text-sm text-muted-foreground">
                    {upcoming.length} upcoming
                </p>
            </header>
            {#if upcoming.length === 0}
                <p class="text-sm text-muted-foreground">No upcoming events.</p>
            {:else}
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {#each upcoming as event (event.id)}
                        <EventCard {event} href={`/events/${event.slug}`} />
                    {/each}
                </div>
            {/if}
        </section>

        <section aria-labelledby="past-title" class="space-y-4">
            <header class="space-y-1">
                <h2 id="past-title" class="text-2xl font-semibold">Past events</h2>
                <p class="text-sm text-muted-foreground">
                    {pagination.total} archived
                </p>
            </header>
            {#if past.length === 0}
                <p class="text-sm text-muted-foreground">No past events yet.</p>
            {:else}
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {#each past as event (event.id)}
                        <EventCard {event} href={`/events/${event.slug}`} />
                    {/each}
                </div>
                {#if pagination.has_more || pagination.page > 1}
                    <nav class="flex items-center justify-between" aria-label="Pagination">
                        <button
                            type="button"
                            class="text-sm disabled:opacity-50"
                            disabled={pagination.page <= 1}
                            onclick={() => goToPage(pagination.page - 1)}
                        >
                            ← Previous
                        </button>
                        <span class="text-sm text-muted-foreground">
                            Page {pagination.page}
                        </span>
                        <button
                            type="button"
                            class="text-sm disabled:opacity-50"
                            disabled={!pagination.has_more}
                            onclick={() => goToPage(pagination.page + 1)}
                        >
                            Next →
                        </button>
                    </nav>
                {/if}
            {/if}
        </section>
    </div>
</PublicLayout>
