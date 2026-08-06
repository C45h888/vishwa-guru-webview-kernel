<script lang="ts">
    /**
     * Dashboard — post-login landing for the canonical admin.
     *
     * Doctrine (AGENTS.md §"Phase 4: Admin Kernel"):
     *   - Confirms the auth loop works end-to-end.
     *   - Pass 2 surfaces real campaign count + featured campaigns.
     *   - Pass 3 will populate events_count + upcoming_events.
     */
    import AdminLayout from '$shared/components/AdminLayout.svelte';
    import { Button } from '$shared/ui/button';
    import { PlusCircle, CalendarDays } from 'lucide-svelte';
    import type { PageComponentProps } from '$shared/lib/inertia';

    type Stats = {
        campaigns_count: number | null;
        events_count: number | null;
        upcoming_events: Array<{
            id: string;
            slug: string;
            title: string;
            starts_at: string;
            timezone: string;
            venue: string | null;
        }>;
    };

    type CampaignSummary = {
        id: string;
        slug: string;
        title: string;
        state: string;
        cover_image_file_id: string | null;
    };

    type Props = PageComponentProps<{
        stats: Stats;
        featured_campaigns: CampaignSummary[];
    }>;

    let { appName, stats, featured_campaigns }: Props = $props();

    function formatDate(iso: string, tz: string): string {
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

<AdminLayout {appName}>
    <div class="space-y-6">
        <div class="space-y-1">
            <h1 class="text-2xl font-semibold tracking-tight">
                Welcome back
            </h1>
            <p class="text-sm text-muted-foreground">
                Authoring console for temple trust campaigns and events.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-lg border border-border bg-card p-5">
                <p
                    class="text-xs font-semibold uppercase tracking-wider text-muted-foreground"
                >
                    Campaigns
                </p>
                <p class="mt-2 text-3xl font-semibold">
                    {stats.campaigns_count ?? '—'}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Including drafts and completed campaigns.
                </p>
            </div>

            <div class="rounded-lg border border-border bg-card p-5">
                <p
                    class="text-xs font-semibold uppercase tracking-wider text-muted-foreground"
                >
                    Events
                </p>
                <p class="mt-2 text-3xl font-semibold">
                    {stats.events_count ?? '—'}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Including drafts and completed events.
                </p>
            </div>
        </div>

        {#if stats.upcoming_events.length > 0}
            <div class="rounded-lg border border-border bg-card p-5">
                <div class="mb-4 flex items-center justify-between">
                    <p class="text-sm font-semibold">Upcoming events</p>
                    <a
                        href="/admin/events"
                        class="text-xs text-muted-foreground hover:text-foreground"
                    >
                        View all →
                    </a>
                </div>
                <div class="space-y-2">
                    {#each stats.upcoming_events as e (e.id)}
                        <a
                            href="/admin/events/{e.id}/edit"
                            class="flex items-center justify-between rounded-md border border-border bg-background p-3 hover:bg-muted/50"
                        >
                            <div class="flex items-center gap-3 min-w-0">
                                <CalendarDays
                                    class="h-4 w-4 shrink-0 text-primary"
                                    aria-hidden="true"
                                />
                                <div class="min-w-0">
                                    <p class="truncate font-medium">{e.title}</p>
                                    {#if e.venue}
                                        <p class="text-xs text-muted-foreground">
                                            {e.venue}
                                        </p>
                                    {/if}
                                </div>
                            </div>
                            <span class="shrink-0 text-xs text-muted-foreground">
                                {formatDate(e.starts_at, e.timezone)}
                            </span>
                        </a>
                    {/each}
                </div>
            </div>
        {/if}

        {#if featured_campaigns.length > 0}
            <div class="rounded-lg border border-border bg-card p-5">
                <div class="mb-4 flex items-center justify-between">
                    <p class="text-sm font-semibold">Featured campaigns</p>
                    <a
                        href="/admin/campaigns"
                        class="text-xs text-muted-foreground hover:text-foreground"
                    >
                        View all →
                    </a>
                </div>
                <div class="space-y-2">
                    {#each featured_campaigns as c (c.id)}
                        <a
                            href="/admin/campaigns/{c.id}/edit"
                            class="flex items-center justify-between rounded-md border border-border bg-background p-3 hover:bg-muted/50"
                        >
                            <span class="font-medium">{c.title}</span>
                            <span class="text-xs text-muted-foreground">
                                {c.state} · /{c.slug}
                            </span>
                        </a>
                    {/each}
                </div>
            </div>
        {/if}

        <div class="flex items-center gap-3">
            <Button href="/admin/campaigns/new">
                <PlusCircle class="h-4 w-4" />
                <span>New campaign</span>
            </Button>
            <Button href="/admin/events/new" variant="outline">
                <CalendarDays class="h-4 w-4" />
                <span>New event</span>
            </Button>
        </div>
    </div>
</AdminLayout>
