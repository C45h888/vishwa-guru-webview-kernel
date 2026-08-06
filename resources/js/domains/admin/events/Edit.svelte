<script lang="ts">
    /**
     * Edit.svelte — admin "edit event" form.
     * Includes the "End event" button with a confirmation modal.
     */
    import AdminLayout from '$shared/components/AdminLayout.svelte';
    import EventForm from './EventForm.svelte';
    import { Button } from '$shared/ui/button';
    import { CheckCircle2, X } from 'lucide-svelte';
    import type { PageComponentProps } from '$shared/lib/inertia';

    type EventRow = {
        id: string;
        slug: string;
        title: string;
        description: string | null;
        short_description: string | null;
        banner_file_id: string | null;
        starts_at: string;
        ends_at: string | null;
        timezone: string;
        venue: string | null;
        venue_address: string | null;
        state: string;
        is_featured: boolean;
        display_order: number;
    };

    type Props = PageComponentProps<{
        event: EventRow;
        states: string[];
        status?: string | null;
    }>;

    let { appName, appUrl, authUser, razorpayMode, event, states, status }: Props = $props();

    function toLocal(iso: string | null): string {
        if (!iso) return '';
        return iso.slice(0, 16);
    }

    const initial = {
        slug: event.slug,
        title: event.title,
        description: event.description ?? '',
        short_description: event.short_description ?? '',
        banner_file_id: event.banner_file_id ?? '',
        starts_at: toLocal(event.starts_at),
        ends_at: toLocal(event.ends_at),
        timezone: event.timezone,
        venue: event.venue ?? '',
        venue_address: event.venue_address ?? '',
        state: event.state,
        is_featured: event.is_featured,
        display_order: event.display_order.toString(),
    };

    let showEndConfirm = $state(false);

    const isCompleted = $derived(event.state === 'completed');
</script>

<AdminLayout {appName}>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold tracking-tight">Edit event</h1>
                <p class="text-sm text-muted-foreground">
                    {event.title} · <code class="text-xs">{event.id}</code>
                    · <span class="text-xs">state: {event.state}</span>
                </p>
            </div>
            <div class="flex items-center gap-3">
                <a href="/admin/events" class="text-sm text-muted-foreground hover:text-foreground">
                    ← Back to events
                </a>
                <a href="/events/{event.slug}" target="_blank" rel="noopener noreferrer"
                    class="text-sm text-primary hover:underline">
                    View on public site ↗
                </a>
            </div>
        </div>

        {#if status}
            <div class="rounded-md border border-primary/30 bg-primary/5 px-3 py-2 text-sm text-primary" role="status">
                {status}
            </div>
        {/if}

        <!-- End event action -->
        {#if !isCompleted}
            <div class="flex items-center justify-between rounded-lg border border-border bg-card p-4">
                <div class="space-y-1">
                    <p class="text-sm font-semibold">End this event</p>
                    <p class="text-xs text-muted-foreground">
                        Marks the event as completed. It moves to the past-events surface and stays accessible to the public.
                    </p>
                </div>
                <Button variant="outline" onclick={() => (showEndConfirm = true)}>
                    <CheckCircle2 class="h-4 w-4" />
                    <span>End event</span>
                </Button>
            </div>
        {:else}
            <div class="flex items-center gap-2 rounded-lg border border-border bg-muted/40 p-4 text-sm">
                <CheckCircle2 class="h-4 w-4 text-muted-foreground" />
                <span class="text-muted-foreground">This event is completed.</span>
            </div>
        {/if}

        <div class="rounded-lg border border-border bg-card p-6">
            {#key event.id}
                <EventForm
                    method="put"
                    action="/admin/events/{event.id}"
                    submit_label="Save changes"
                    {initial}
                    {states}
                    {appName}
                    {appUrl}
                    {authUser}
                    {razorpayMode}
                />
            {/key}
        </div>
    </div>
</AdminLayout>

<!-- End event confirmation modal -->
{#if showEndConfirm}
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-background/80 backdrop-blur"
        role="dialog" aria-modal="true" aria-labelledby="end-event-title">
        <div class="w-full max-w-md rounded-lg border border-border bg-card p-6 shadow-xl">
            <div class="flex items-start gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-primary">
                    <CheckCircle2 class="h-5 w-5" />
                </div>
                <div class="flex-1 space-y-2">
                    <h2 id="end-event-title" class="text-lg font-semibold">Mark as completed?</h2>
                    <p class="text-sm text-muted-foreground">
                        The event will move to the past-events surface. You can still edit it later if needed.
                    </p>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <Button variant="outline" onclick={() => (showEndConfirm = false)}>
                    <X class="h-4 w-4" />
                    <span>Cancel</span>
                </Button>
                <form method="post" action="/admin/events/{event.id}/end">
                    <Button type="submit">
                        <CheckCircle2 class="h-4 w-4" />
                        <span>Mark as completed</span>
                    </Button>
                </form>
            </div>
        </div>
    </div>
{/if}
