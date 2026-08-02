<script lang="ts">
    /**
     * AdminNav — top nav for the admin kernel.
     *
     * Doctrine (AGENTS.md §"Phase 4: Admin Kernel"):
     *   - Two placeholder links (Campaigns, Events) plus the live
     *     Dashboard. Pass 2 lights up the Campaigns link; Pass 3
     *     lights up the Events link. Keeping the placeholder visible
     *     gives the admin a stable mental map of the surface before
     *     each pass lands.
     *   - Right side: user name + logout form. POST /logout is
     *     Breeze canonical (not a DELETE; logout is mutating but
     *     conceptually a session operation, not a resource op).
     */
    import { page } from '@inertiajs/svelte';
    import { router } from '@inertiajs/svelte';

    type Props = Record<string, never>;

    let {}: Props = $props();

    function isCurrent(name: string): boolean {
        const current = $page.url;
        if (name === 'admin.dashboard') return current === '/admin';
        return current.startsWith(`/${name.replace('admin.', 'admin/')}`);
    }

    function logout() {
        router.post('/logout');
    }

    const authUser = $derived($page.props.authUser);
</script>

<nav class="flex items-center gap-6">
    <div class="flex items-center gap-1 text-sm">
        <a
            href="/admin"
            class="rounded-md px-3 py-1.5 font-medium {isCurrent('admin.dashboard')
                ? 'bg-primary/10 text-primary'
                : 'text-muted-foreground hover:bg-muted hover:text-foreground'}"
        >
            Dashboard
        </a>
        <a
            href="/admin/campaigns"
            class="rounded-md px-3 py-1.5 font-medium {isCurrent('admin.campaigns')
                ? 'bg-primary/10 text-primary'
                : 'text-muted-foreground hover:bg-muted hover:text-foreground'}"
        >
            Campaigns
        </a>
        <a
            href="/admin/events"
            class="rounded-md px-3 py-1.5 font-medium {isCurrent('admin.events')
                ? 'bg-primary/10 text-primary'
                : 'text-muted-foreground hover:bg-muted hover:text-foreground'}"
        >
            Events
        </a>
    </div>

    {#if authUser}
        <div class="flex items-center gap-3 border-l border-border pl-6 text-sm">
            <span class="text-muted-foreground">
                {authUser.name}
            </span>
            <button
                type="button"
                onclick={logout}
                class="rounded-md border border-border bg-background px-3 py-1.5 font-medium hover:bg-muted"
            >
                Sign out
            </button>
        </div>
    {/if}
</nav>
