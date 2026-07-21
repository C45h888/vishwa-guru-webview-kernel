<script lang="ts">
    // Stub for commit 5 — full implementation lands in commit 8.
    let { gallery, appName }: {
        gallery: Record<string, unknown>;
        appName: string;
    } = $props();
</script>

<h1>{String(gallery.title)}</h1>
<p>Stub detail.</p>
