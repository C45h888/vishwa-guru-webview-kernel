<script lang="ts">
    /**
     * Admin/Campaigns/Create.svelte — "new campaign" form page.
     * Renders the shared CampaignForm in POST mode.
     */
    import AdminLayout from '$shared/components/AdminLayout.svelte';
    import CampaignForm from './CampaignForm.svelte';
    import type { PageComponentProps } from '$shared/lib/inertia';

    type Props = PageComponentProps<{
        states: string[];
        defaults: {
            state: string;
            currency_code: string;
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
        category: '',
        currency_code: defaults.currency_code,
        target_amount_minor: '',
        state: defaults.state,
        starts_at: '',
        ends_at: '',
        is_featured: defaults.is_featured,
        display_order: String(defaults.display_order),
        cover_image_file_id: '',
    };
</script>

<AdminLayout {appName}>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold tracking-tight">New campaign</h1>
                <p class="text-sm text-muted-foreground">
                    Create a new campaign. Save as <span class="font-medium">draft</span> to keep it private, or
                    <span class="font-medium">active</span> to publish immediately.
                </p>
            </div>
            <a href="/admin/campaigns" class="text-sm text-muted-foreground hover:text-foreground">
                ← Back to campaigns
            </a>
        </div>

        {#if status}
            <div class="rounded-md border border-primary/30 bg-primary/5 px-3 py-2 text-sm text-primary" role="status">
                {status}
            </div>
        {/if}

        <div class="rounded-lg border border-border bg-card p-6">
            <CampaignForm
                method="post"
                action="/admin/campaigns"
                submit_label="Create campaign"
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
