<script lang="ts">
    /**
     * AdminEditOverlay — pencil-style edit affordance for admins.
     *
     * Doctrine (AGENTS.md §"Phase 4: Admin Kernel"):
     *   - Renders a small floating pencil button in the top-right
     *     corner of its parent. Used by the public Campaigns +
     *     Events pages to give admins a fast path into the authoring
     *     surface without leaving the public site.
     *   - The component is a no-op when `authUser.role !== 'admin'` —
     *     the wrapping <slot> still renders, but the pencil does not.
     *     Doctrine: zero DOM impact for non-admin visitors.
     *   - The pencil links to the admin edit page (or admin create
     *     page when used for "new"). It is a regular <a>, not a
     *     button, so right-click → open-in-new-tab works.
     *
     * Usage:
     *   <AdminEditOverlay href="/admin/campaigns/{id}/edit" />
     *   <AdminEditOverlay href="/admin/campaigns/new" label="New campaign" />
     */
    import { Pencil } from 'lucide-svelte';
    import { page } from '@inertiajs/svelte';

    type Props = {
        href: string;
        label?: string;
        /** Accessible label override (default: "Edit"). */
        srLabel?: string;
    };

    let { href, label = 'Edit', srLabel = 'Edit' }: Props = $props();

    const isAdmin = $derived($page.props.authUser?.role === 'admin');
</script>

{#if isAdmin}
    <a
        {href}
        title={srLabel}
        aria-label={srLabel}
        class="absolute right-3 top-3 z-10 inline-flex h-8 w-8 items-center justify-center rounded-full border border-border bg-background/95 text-foreground shadow-sm backdrop-blur transition-all hover:border-primary hover:bg-primary hover:text-primary-foreground focus:outline-none focus:ring-2 focus:ring-primary/40"
    >
        <Pencil class="h-3.5 w-3.5" aria-hidden="true" />
        {#if label !== 'Edit'}
            <span class="sr-only">{label}</span>
        {/if}
    </a>
{/if}
