<script lang="ts">
    // Stub for commit 5 — full implementation lands in commit 8.
    let { campaigns, pagination, appName }: {
        campaigns: Array<Record<string, unknown>>;
        pagination: Record<string, unknown>;
        appName: string;
    } = $props();
</script>

<h1>Campaigns</h1>
<p>Stub. {campaigns.length} campaigns, page {String(pagination.page)}.</p>
