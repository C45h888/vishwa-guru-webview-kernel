<script lang="ts">
    // Stub for commit 5 — full implementation lands in commit 8.
    let { upcoming, past, pagination, appName }: {
        upcoming: Array<Record<string, unknown>>;
        past: Array<Record<string, unknown>>;
        pagination: Record<string, unknown>;
        appName: string;
    } = $props();
</script>

<h1>Events</h1>
<p>Stub. {upcoming.length} upcoming, {past.length} past.</p>
