<script lang="ts">
    /**
     * Dashboard — post-login landing for the canonical admin.
     *
     * Doctrine (AGENTS.md §"Phase 4: Admin Kernel"):
     *   - Confirms the auth loop works end-to-end: user lands here
     *     after /login, sees their name + role, and has two CTAs
     *     into the future authoring surfaces (Pass 2/3).
     *   - Stats are placeholders in Pass 1. Pass 2 will populate
     *     `campaigns_count`; Pass 3 will populate `events_count` +
     *     `upcoming_events`. The Svelte component doesn't need to
     *     change shape — it already handles `null` gracefully.
     */
    import AdminLayout from '$shared/components/AdminLayout.svelte';
    import type { PageComponentProps } from '$shared/lib/inertia';

    type Stats = {
        campaigns_count: number | null;
        events_count: number | null;
        upcoming_events: Array<{ id: string; title: string }>;
    };

    type Props = PageComponentProps<{
        stats: Stats;
    }>;

    let { appName, stats }: Props = $props();
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
                    Live count arrives in Pass 2.
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
                    Live count + upcoming list arrive in Pass 3.
                </p>
            </div>
        </div>

        <div
            class="rounded-lg border border-dashed border-border bg-card/50 p-5 text-sm text-muted-foreground"
        >
            <p>
                This is the Pass 1 dashboard — proves the admin auth loop
                works end-to-end (login → middleware → role check →
                landing). The Campaigns and Events authoring surfaces are
                wired in subsequent passes.
            </p>
        </div>
    </div>
</AdminLayout>
