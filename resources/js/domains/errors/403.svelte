<script lang="ts">
    /**
     * 403.svelte — access denied.
     */
    import type { AppPageProps } from '$shared/lib/inertia';

    let { appName, message }: AppPageProps<{
        message: string;
    }> = $props();
</script>

<svelte:head>
    <title>Access denied — {appName}</title>
</svelte:head>

<main class="flex min-h-screen flex-col items-center justify-center bg-background px-4">
    <div class="w-full max-w-md space-y-6 text-center">
        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-muted-foreground">
            403
        </p>
        <h1 class="font-serif text-3xl font-semibold tracking-tight">
            Access denied
        </h1>
        <p class="text-sm leading-relaxed text-muted-foreground">
            {message}
        </p>
        <div class="flex items-center justify-center gap-3 pt-2">
            <a
                href="/"
                class="inline-flex items-center gap-1 rounded-md border border-border bg-background px-4 py-2 text-sm font-medium hover:bg-muted"
            >
                ← Back to home
            </a>
        </div>
    </div>
</main>
