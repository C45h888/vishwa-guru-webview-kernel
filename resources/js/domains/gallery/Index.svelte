<script lang="ts">
    // Stub for commit 5 — full implementation lands in commit 8.
    let { galleries, pagination, appName }: {
        galleries: Array<Record<string, unknown>>;
        pagination: Record<string, unknown>;
        appName: string;
    } = $props();
</script>

<h1>Gallery</h1>
<p>Stub. {galleries.length} galleries, page {String(pagination.page)}.</p>
