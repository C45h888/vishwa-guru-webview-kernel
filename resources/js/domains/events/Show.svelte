<script lang="ts">
    // Stub for commit 5 — full implementation lands in commit 8.
    let { event, appName }: {
        event: Record<string, unknown>;
        appName: string;
    } = $props();
</script>

<h1>{String(event.title)}</h1>
<p>Stub detail.</p>
