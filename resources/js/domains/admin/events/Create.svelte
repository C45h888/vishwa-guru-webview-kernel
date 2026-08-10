<script lang="ts">
    /**
     * Create.svelte — admin "new event" form.
     */
    import AdminLayout from '$shared/components/AdminLayout.svelte';
    import EventForm from './EventForm.svelte';
    import type { PageComponentProps } from '$shared/lib/inertia';

    type Props = PageComponentProps<{
        states: string[];
        defaults: {
            state: string;
            timezone: string;
            is_featured: boolean;
            display_order: number;
        };
        status?: string | null;
    }>;

    let {
        appName,
        appShortName,
        appUrl,
        authUser,
        razorpayMode,
        states,
        defaults,
        status,
    }: Props = $props();

    const initial = {
        slug: '',
        title: '',
        description: '',
        short_description: '',
        banner_file_id: '',
        starts_at: '',
        ends_at: '',
        timezone: defaults.timezone,
        venue: '',
        venue_address: '',
        state: defaults.state,
        is_featured: defaults.is_featured,
        display_order: String(defaults.display_order),
    };
</script>

<AdminLayout {appName}>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold tracking-tight">New event</h1>
                <p class="text-sm text-muted-foreground">
                    Create a new event. Save as <span class="font-medium">draft</span> to keep it private, or
                    <span class="font-medium">published</span> for upcoming visibility.
                </p>
            </div>
            <a href="/admin/events" class="text-sm text-muted-foreground hover:text-foreground">
                ← Back to events
            </a>
        </div>

        {#if status}
            <div class="rounded-md border border-primary/30 bg-primary/5 px-3 py-2 text-sm text-primary" role="status">
                {status}
            </div>
        {/if}

        <div class="rounded-lg border border-border bg-card p-6">
            <EventForm
                method="post"
                action="/admin/events"
                submit_label="Create event"
                {initial}
                {states}
                {appName}
                {appShortName}
                {appUrl}
                {authUser}
                {razorpayMode}
            />
        </div>
    </div>
</AdminLayout>
