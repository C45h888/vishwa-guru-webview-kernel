<script lang="ts">
    import type { EventSummaryProps } from '$shared/lib/inertia';

    /**
     * Date-sorted list of upcoming events. Each row shows a date block,
     * event title, venue, and a Register CTA. No card chrome — the list
     * is the design.
     */

    interface Props {
        events: EventSummaryProps[];
        limit?: number;
    }

    let { events, limit = 5 }: Props = $props();

    const upcoming = $derived(
        events
            .filter((event) => event.is_upcoming)
            .sort(
                (a, b) =>
                    new Date(a.starts_at).getTime() -
                    new Date(b.starts_at).getTime(),
            )
            .slice(0, limit),
    );

    const hasEvents = $derived(upcoming.length > 0);

    function dayLabel(iso: string): string {
        const d = new Date(iso);
        if (Number.isNaN(d.getTime())) return '';
        return d.toLocaleDateString('en-IN', {
            day: '2-digit',
            timeZone: 'Asia/Kolkata',
        });
    }

    function monthLabel(iso: string): string {
        const d = new Date(iso);
        if (Number.isNaN(d.getTime())) return '';
        return d
            .toLocaleDateString('en-IN', { month: 'short', timeZone: 'Asia/Kolkata' })
            .toUpperCase();
    }

    function timeLabel(iso: string, tz: string): string {
        const d = new Date(iso);
        if (Number.isNaN(d.getTime())) return '';
        try {
            return new Intl.DateTimeFormat('en-IN', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: true,
                timeZone: tz || 'Asia/Kolkata',
            }).format(d);
        } catch {
            return '';
        }
    }
</script>

<section class="bg-ivory py-20 lg:py-28">
    <div class="container">
        <div class="mb-10 flex items-end justify-between gap-4">
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

        {#if hasEvents}
            <ul class="divide-y divide-border/60 border-y border-border/60">
                {#each upcoming as event (event.id)}
                    <li>
                        <a
                            href={`/events/${event.slug}`}
                            class="group grid grid-cols-12 items-center gap-4 py-6 transition-colors hover:bg-background/50 lg:gap-8"
                        >
                            <div
                                class="col-span-3 flex items-baseline gap-2 sm:col-span-2"
                            >
                                <span
                                    class="font-serif text-2xl font-semibold text-primary lg:text-3xl"
                                >
                                    {dayLabel(event.starts_at)}
                                </span>
                                <span
                                    class="text-[10px] font-semibold uppercase tracking-[0.2em] text-muted-foreground lg:text-xs"
                                >
                                    {monthLabel(event.starts_at)}
                                </span>
                            </div>

                            <div class="col-span-9 space-y-1 sm:col-span-7">
                                <h3
                                    class="font-serif text-lg font-semibold leading-tight text-foreground transition-colors group-hover:text-primary lg:text-xl"
                                >
                                    {event.title}
                                </h3>
                                {#if event.venue}
                                    <p
                                        class="text-sm text-muted-foreground"
                                    >
                                        {event.venue}
                                        {#if event.venue_address}
                                            · {event.venue_address}
                                        {/if}
                                    </p>
                                {/if}
                                <p class="text-xs text-muted-foreground/80">
                                    {timeLabel(event.starts_at, event.timezone)}
                                </p>
                            </div>

                            <div
                                class="col-span-12 flex items-center justify-end sm:col-span-3"
                            >
                                <span
                                    class="inline-flex items-center gap-1.5 text-sm font-medium text-primary transition-transform group-hover:translate-x-0.5"
                                >
                                    Register
                                    <span aria-hidden="true">→</span>
                                </span>
                            </div>
                        </a>
                    </li>
                {/each}
            </ul>
        {:else}
            <div
                class="rounded-md border border-dashed border-border/60 bg-background/40 py-16 text-center"
            >
                <p class="font-serif text-lg text-foreground/80">
                    No upcoming events scheduled.
                </p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Check back soon, or browse the full events archive.
                </p>
            </div>
        {/if}

        <div class="mt-8 text-center sm:hidden">
            <a
                href="/events"
                class="inline-flex text-sm font-medium text-primary hover:underline"
            >
                View all events →
            </a>
        </div>
    </div>
</section>
