<script lang="ts">
    // Stub for commit 5 — full implementation lands in commit 8.
    let { campaign, progress, appName }: {
        campaign: Record<string, unknown>;
        progress: Array<Record<string, unknown>>;
        appName: string;
    } = $props();
</script>

<h1>{String(campaign.title)}</h1>
<p>Stub detail. {progress.length} progress rows.</p>
