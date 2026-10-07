<script lang="ts">
    /**
     * 503.svelte — service unavailable. Rendered when the database
     * is unreachable (Postgres/Neon outage). Shows a calm retry
     * affordance instead of a stack trace.
     */
    import { router } from '@inertiajs/svelte';
    import type { AppPageProps } from '$shared/lib/inertia';

    let { appName, message, retry_after }: AppPageProps<{
        message: string;
        retry_after: number;
    }> = $props();

    let reloading = $state(false);

    function retry() {
        reloading = true;
        router.reload({ only: [] });
        setTimeout(() => { reloading = false; }, 800);
    }
</script>

<svelte:head>
    <meta name="robots" content="noindex, nofollow" />
    <title>Temporarily unavailable — {appName}</title>
</svelte:head>

<main class="flex min-h-screen flex-col items-center justify-center bg-background px-4">
    <div class="w-full max-w-md space-y-6 text-center">
        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-muted-foreground">
            Temporarily unavailable
        </p>
        <h1 class="font-serif text-3xl font-semibold tracking-tight">
            We'll be right back
        </h1>
        <p class="text-sm leading-relaxed text-muted-foreground">
            {message}
        </p>
        <p class="text-xs text-muted-foreground">
            Retry in approximately {retry_after} seconds.
        </p>
        <div class="flex items-center justify-center gap-3 pt-2">
            <button
                type="button"
                onclick={retry}
                disabled={reloading}
                class="inline-flex items-center gap-1 rounded-md border border-primary bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-60"
            >
                {reloading ? 'Retrying…' : 'Try again'}
            </button>
            <a
                href="/"
                class="inline-flex items-center gap-1 rounded-md border border-border bg-background px-4 py-2 text-sm font-medium hover:bg-muted"
            >
                Back to home
            </a>
        </div>
    </div>
</main>
