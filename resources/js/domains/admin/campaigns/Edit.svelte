<script lang="ts">
    /**
     * Admin/Campaigns/Edit.svelte — "edit campaign" form page.
     * Renders the shared CampaignForm in PUT mode, prefilled with
     * the row's current values.
     */
    import AdminLayout from '$shared/components/AdminLayout.svelte';
    import CampaignForm from './CampaignForm.svelte';
    import type { PageComponentProps } from '$shared/lib/inertia';

    type CampaignRow = {
        id: string;
        slug: string;
        title: string;
        description: string | null;
        short_description: string | null;
        category: string;
        currency_code: string;
        target_amount_minor: number | null;
        state: string;
        starts_at: string | null;
        ends_at: string | null;
        is_featured: boolean;
        display_order: number;
        cover_image_file_id: string | null;
    };

    type Props = PageComponentProps<{
        campaign: CampaignRow;
        states: string[];
        status?: string | null;
    }>;

    let { appName, appUrl, authUser, razorpayMode, campaign, states, status }: Props = $props();

    // Convert datetime strings from ISO (server) to datetime-local (HTML).
    function toLocal(iso: string | null): string {
        if (!iso) return '';
        // datetime-local expects YYYY-MM-DDTHH:MM (no seconds, no zone).
        // We trim seconds and the trailing Z/offset.
        return iso.slice(0, 16);
    }

    const initial = {
        slug: campaign.slug,
        title: campaign.title,
        description: campaign.description ?? '',
        short_description: campaign.short_description ?? '',
        category: campaign.category,
        currency_code: campaign.currency_code,
        target_amount_minor: campaign.target_amount_minor?.toString() ?? '',
        state: campaign.state,
        starts_at: toLocal(campaign.starts_at),
        ends_at: toLocal(campaign.ends_at),
        is_featured: campaign.is_featured,
        display_order: campaign.display_order.toString(),
        cover_image_file_id: campaign.cover_image_file_id ?? '',
    };
</script>

<AdminLayout {appName}>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold tracking-tight">Edit campaign</h1>
                <p class="text-sm text-muted-foreground">
                    {campaign.title} · <code class="text-xs">{campaign.id}</code>
                </p>
            </div>
            <div class="flex items-center gap-3">
                <a href="/admin/campaigns" class="text-sm text-muted-foreground hover:text-foreground">
                    ← Back to campaigns
                </a>
                <a
                    href="/campaigns/{campaign.slug}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-sm text-primary hover:underline"
                >
                    View on public site ↗
                </a>
            </div>
        </div>

        {#if status}
            <div class="rounded-md border border-primary/30 bg-primary/5 px-3 py-2 text-sm text-primary" role="status">
                {status}
            </div>
        {/if}

        <div class="rounded-lg border border-border bg-card p-6">
            {#key campaign.id}
                <CampaignForm
                    method="put"
                    action="/admin/campaigns/{campaign.id}"
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
