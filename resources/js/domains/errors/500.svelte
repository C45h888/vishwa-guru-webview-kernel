<script lang="ts">
    /**
     * 500.svelte — generic server error page.
     * Rendered by App\Exceptions\Handler when an Inertia request
     * raises an unexpected exception (status 500).
     */
    import type { AppPageProps } from '$shared/lib/inertia';

    let { appName, message, status }: AppPageProps<{
        message: string;
        status: number;
    }> = $props();
</script>

<svelte:head>
    <meta name="robots" content="noindex, nofollow" />
    <title>Server error — {appName}</title>
</svelte:head>

<main class="flex min-h-screen flex-col items-center justify-center bg-background px-4">
    <div class="w-full max-w-md space-y-6 text-center">
        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-muted-foreground">
            Error {status}
        </p>
        <h1 class="font-serif text-3xl font-semibold tracking-tight">
            Something went wrong
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
            <a
                href="/admin"
                class="inline-flex items-center gap-1 rounded-md border border-primary bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
            >
                Go to admin dashboard
            </a>
        </div>
    </div>
</main>
