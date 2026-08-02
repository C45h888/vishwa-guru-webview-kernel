<script lang="ts">
    /**
     * AdminLayout — chrome for every /admin/* page.
     *
     * Doctrine (AGENTS.md §"Phase 4: Admin Kernel"):
     *   - Distinct from PublicLayout. No mandala decoration, no
     *     ivory/saffron public-site palette; the admin kernel uses
     *     neutral card surfaces so editors focus on the form.
     *   - Slot pattern: pages pass `<AdminLayout>{...}</AdminLayout>`.
     *     The header carries the brand mark, the admin nav, and a
     *     user menu with logout. The body is a container-bound main.
     *   - No mobile-first rework here in Pass 1 — the admin is a
     *     desktop-first surface, mobile is a future polish.
     */
    import type { Snippet } from 'svelte';
    import AdminNav from './AdminNav.svelte';

    type Props = {
        appName?: string;
        children?: Snippet;
    };

    let { appName = 'Temple Trust Admin', children }: Props = $props();
</script>

<div class="min-h-screen bg-background">
    <header
        class="border-b border-border bg-card"
    >
        <div class="container flex h-16 items-center justify-between gap-4">
            <a href="/admin" class="flex items-center gap-2">
                <span class="text-lg font-semibold tracking-tight">
                    {appName}
                </span>
                <span
                    class="rounded-md border border-border bg-background px-2 py-0.5 text-xs uppercase tracking-wider text-muted-foreground"
                >
                    Admin
                </span>
            </a>
            <AdminNav />
        </div>
    </header>

    <main class="container py-8">
        {#if children}{@render children()}{/if}
    </main>
</div>
