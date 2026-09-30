<script lang="ts">
    import { Card, CardContent } from '$shared/ui/card';
    import EventCard from '$shared/components/EventCard.svelte';
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import PublicMediaImage from '$shared/components/PublicMediaImage.svelte';
    import MandalaDecoration from '$shared/components/MandalaDecoration.svelte';
    import TrustBadgeRow from '$shared/components/TrustBadgeRow.svelte';
    import AdminEditOverlay from '$shared/components/AdminEditOverlay.svelte';

    import GradientPanel from '$shared/components/GradientPanel.svelte';
    import { CalendarDays, MapPin, Clock } from 'lucide-svelte';
    import { page } from '@inertiajs/svelte';
    import SeoHead from '$shared/components/SeoHead.svelte';
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
        appUrl,
    }: AppPageProps<{ event: EventDetailProps }> = $props();

    const related = $derived(event.related ?? []);
    const heroImage = $derived(event.banner_image ?? null);
    const hasImage = $derived(heroImage !== null);
    const isAdmin = $derived($page.props.authUser?.role === "admin");
    const adminEditHref = $derived(`/admin/events/${event.id}/edit`);

    function formatDate(iso: string | null, tz: string): string {
        if (!iso) return '';
        try {
            return new Intl.DateTimeFormat('en-IN', {
                dateStyle: 'full',
                timeStyle: 'short',
                timeZone: tz || 'Asia/Kolkata',
            }).format(new Date(iso));
        } catch {
            return iso;
        }
    }
</script>

<SeoHead
    title={event.title}
    {appName}
    {appUrl}
    description={event.short_description}
    image={heroImage?.url ?? null}
    imageAlt={heroImage?.alt_text ?? null}
/>

<PublicLayout>
    <article>
        <!-- ═══ HERO ═══ -->
        <section class="relative overflow-hidden bg-background">
            <div
                class="pointer-events-none absolute -right-20 top-0 opacity-[0.08]"
                aria-hidden="true"
            >
                <MandalaDecoration size={320} tint="gold" />
            </div>

            <div class="container relative py-12 lg:py-20">
                <div class="grid grid-cols-1 items-center gap-10 lg:grid-cols-12 lg:gap-16">
                    <div class="space-y-5 lg:col-span-7">
                        <p
                            class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                        >
                            Event
                        </p>
                        <h1
                            class="font-serif text-3xl font-semibold leading-tight lg:text-5xl"
                        >
                            {event.title}
                        </h1>
                        {#if event.short_description}
                            <p
                                class="max-w-xl text-base leading-relaxed text-muted-foreground lg:text-lg"
                            >
                                {event.short_description}
                            </p>
                        {/if}

                        <div
                            class="flex flex-wrap gap-2 pt-1 text-[10px] font-semibold uppercase tracking-[0.18em]"
                        >
                            <span
                                class="rounded-full border border-primary/30 bg-primary/5 px-2.5 py-1 text-primary"
                            >
                                {event.state}
                            </span>
                            {#if event.is_upcoming}
                                <span
                                    class="rounded-full bg-primary px-2.5 py-1 text-primary-foreground"
                                >
                                    Upcoming
                                </span>
                            {/if}
                        </div>

                        <div class="pt-1">
                            <TrustBadgeRow />
                        </div>
                    </div>

                    <div class="relative lg:col-span-5">
                        {#if isAdmin}
                            <AdminEditOverlay href={adminEditHref} srLabel={`Edit event: ${event.title}`} />
                        {/if}
                        {#if hasImage}
                            <div
                                class="overflow-hidden rounded-md border border-border/40"
                            >
                                <PublicMediaImage
                                    media={heroImage!}
                                    alt={event.title}
                                    class="aspect-[4/5] w-full object-cover"
                                />
                            </div>
                        {:else}
                            <GradientPanel
                                aspectRatio="portrait"
                                variant="gold"
                            />
                        {/if}
                    </div>
                </div>
            </div>
        </section>

        <!-- ═══ WHEN & WHERE ═══ -->
        <section class="bg-ivory py-14 lg:py-20">
            <div class="container">
                <div class="mx-auto max-w-3xl space-y-6">
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                    >
                        When &amp; where
                    </p>
                    <h2
                        class="font-serif text-2xl font-semibold lg:text-3xl"
                    >
                        Plan your visit
                    </h2>

                    <div
                        class="grid grid-cols-1 gap-4 md:grid-cols-3"
                    >
                        <div
                            class="flex items-start gap-3 rounded-md border border-border/40 bg-background p-4"
                        >
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                            >
                                <CalendarDays class="h-4 w-4" aria-hidden="true" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p
                                    class="text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground"
                                >
                                    When
                                </p>
                                <p class="mt-0.5 text-sm font-medium">
                                    {formatDate(
                                        event.starts_at,
                                        event.timezone,
                                    )}
                                </p>
                            </div>
                        </div>

                        {#if event.venue}
                            <div
                                class="flex items-start gap-3 rounded-md border border-border/40 bg-background p-4"
                            >
                                <div
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                                >
                                    <MapPin class="h-4 w-4" aria-hidden="true" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground"
                                    >
                                        Where
                                    </p>
                                    <p class="mt-0.5 text-sm font-medium">
                                        {event.venue}
                                    </p>
                                    {#if event.venue_address}
                                        <p class="text-xs text-muted-foreground">
                                            {event.venue_address}
                                        </p>
                                    {/if}
                                </div>
                            </div>
                        {/if}

                        <div
                            class="flex items-start gap-3 rounded-md border border-border/40 bg-background p-4"
                        >
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                            >
                                <Clock class="h-4 w-4" aria-hidden="true" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p
                                    class="text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground"
                                >
                                    Timezone
                                </p>
                                <p class="mt-0.5 text-sm font-medium">
                                    {event.timezone}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ═══ DESCRIPTION ═══ -->
        {#if event.description}
            <section class="container py-20 lg:py-28">
                <div class="mx-auto max-w-3xl space-y-4">
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                    >
                        About this event
                    </p>
                    <h2
                        class="font-serif text-2xl font-semibold lg:text-3xl"
                    >
                        What to expect
                    </h2>
                    <div
                        class="prose prose-stone max-w-none prose-a:text-primary"
                    >
                        <p>{event.description}</p>
                    </div>
                </div>
            </section>
        {/if}

        <!-- ═══ RELATED EVENTS ═══ -->
        {#if related.length > 0}
            <section class="bg-ivory py-20 lg:py-28">
                <div class="container">
                    <div class="mx-auto max-w-5xl space-y-8">
                        <div class="space-y-2">
                            <p
                                class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                            >
                                More from the temple
                            </p>
                            <h2
                                class="font-serif text-2xl font-semibold lg:text-3xl"
                            >
                                Related events
                            </h2>
                        </div>
                        <div
                            class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3"
                        >
                            {#each related as rel (rel.id)}
                                <EventCard
                                    event={rel}
                                    href={`/events/${rel.slug}`}
                                    adminEditHref={isAdmin ? `/admin/events/${rel.id}/edit` : null}
                                />
                            {/each}
                        </div>
                    </div>
                </div>
            </section>
        {/if}
    </article>
</PublicLayout>
