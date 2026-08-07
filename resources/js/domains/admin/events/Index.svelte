<script lang="ts">
    /**
     * Index.svelte — admin events list (incl. drafts).
     */
    import AdminLayout from '$shared/components/AdminLayout.svelte';
    import { Button } from '$shared/ui/button';
    import { PlusCircle, ChevronLeft, ChevronRight } from 'lucide-svelte';
    import { formatDbDate } from '$shared/lib/dates';
    import type { PageComponentProps } from '$shared/lib/inertia';

    type EventRow = {
        id: string;
        slug: string;
        title: string;
        state: string;
        venue: string | null;
        starts_at: string;
        ends_at: string | null;
        timezone: string;
        is_featured: boolean;
        display_order: number;
        is_upcoming: boolean;
        banner_file_id: string | null;
    };

    type Pagination = {
        page: number;
        per_page: number;
        total: number;
        has_more: boolean;
    };

    type Props = PageComponentProps<{
        events: EventRow[];
        pagination: Pagination;
    }>;

    let { appName, events, pagination }: Props = $props();

    function stateClass(state: string): string {
        switch (state) {
            case 'published': return 'bg-primary/15 text-primary';
            case 'completed': return 'bg-muted text-muted-foreground';
            case 'draft':
            default: return 'bg-background text-muted-foreground ring-1 ring-border';
        }
    }
</script>

<AdminLayout {appName}>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold tracking-tight">Events</h1>
                <p class="text-sm text-muted-foreground">
                    {pagination.total} total
                    · showing {(pagination.page - 1) * pagination.per_page + 1}–{Math.min(pagination.page * pagination.per_page, pagination.total)}
                </p>
            </div>
            <Button href="/admin/events/new">
                <PlusCircle class="h-4 w-4" />
                <span>New event</span>
            </Button>
        </div>

        <div class="overflow-hidden rounded-lg border border-border bg-card">
            <table class="w-full text-sm">
                <thead class="border-b border-border bg-muted/40 text-xs uppercase tracking-wider text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">Title</th>
                        <th class="px-4 py-3 text-left font-medium">Venue</th>
                        <th class="px-4 py-3 text-left font-medium">When</th>
                        <th class="px-4 py-3 text-left font-medium">State</th>
                        <th class="px-4 py-3 text-right font-medium">Updated</th>
                        <th class="px-4 py-3 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    {#each events as e (e.id)}
                        <tr class="hover:bg-muted/30">
                            <td class="px-4 py-3 font-medium">
                                <div class="flex items-center gap-2">
                                    {#if e.is_featured}
                                        <span class="rounded-full bg-primary/10 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-primary">Featured</span>
                                    {/if}
                                    <span class="truncate">{e.title}</span>
                                </div>
                                <div class="text-xs text-muted-foreground">/{e.slug}</div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{e.venue ?? '—'}</td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {formatDbDate(e.starts_at, e.starts_at, { dateStyle: 'medium', timeStyle: 'short' })}
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {stateClass(e.state)}">
                                    {e.state}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right text-xs text-muted-foreground">
                                {e.is_upcoming ? 'upcoming' : 'past'}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="/admin/events/{e.id}/edit" class="font-medium text-primary hover:underline">
                                    Edit
                                </a>
                            </td>
                        </tr>
                    {/each}
                    {#if events.length === 0}
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-muted-foreground">
                                No events yet.
                                <a href="/admin/events/new" class="text-primary hover:underline">
                                    Create the first one.
                                </a>
                            </td>
                        </tr>
                    {/if}
                </tbody>
            </table>
        </div>

        {#if pagination.total > pagination.per_page}
            <div class="flex items-center justify-between">
                <Button variant="outline" disabled={pagination.page <= 1}
                    href="/admin/events?page={pagination.page - 1}">
                    <ChevronLeft class="h-4 w-4" />
                    <span>Previous</span>
                </Button>
                <span class="text-sm text-muted-foreground">
                    Page {pagination.page} of {Math.ceil(pagination.total / pagination.per_page)}
                </span>
                <Button variant="outline" disabled={!pagination.has_more}
                    href="/admin/events?page={pagination.page + 1}">
                    <span>Next</span>
                    <ChevronRight class="h-4 w-4" />
                </Button>
            </div>
        {/if}
    </div>
</AdminLayout>
