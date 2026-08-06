<script lang="ts">
    /**
     * Admin/Campaigns/Index.svelte — list of all campaigns (incl. drafts).
     *
     * Doctrine (AGENTS.md §"Phase 4: Admin Kernel"):
     *   - Lists every campaign with state pill, slug, edit link.
     *     Sorted by updated_at DESC (server-side).
     *   - State pill colour reflects the state: draft=neutral,
     *     active=primary, completed=muted.
     *   - Pagination: prev/next links, server-driven via ?page=.
     *   - "New campaign" CTA routes to /admin/campaigns/new.
     */
    import AdminLayout from '$shared/components/AdminLayout.svelte';
    import { Button } from '$shared/ui/button';
    import { PlusCircle, ChevronLeft, ChevronRight } from 'lucide-svelte';
    import type { PageComponentProps } from '$shared/lib/inertia';

    type CampaignRow = {
        id: string;
        slug: string;
        title: string;
        state: string;
        category: string;
        is_featured: boolean;
        display_order: number;
        updated_at: string;
        starts_at: string | null;
        ends_at: string | null;
        cover_image_file_id: string | null;
        target_amount_minor: number | null;
        currency_code: string;
    };

    type Pagination = {
        page: number;
        per_page: number;
        total: number;
        has_more: boolean;
    };

    type Props = PageComponentProps<{
        campaigns: CampaignRow[];
        pagination: Pagination;
    }>;

    let { appName, campaigns, pagination }: Props = $props();

    function stateClass(state: string): string {
        switch (state) {
            case 'active': return 'bg-primary/15 text-primary';
            case 'completed': return 'bg-muted text-muted-foreground';
            case 'draft':
            default: return 'bg-background text-muted-foreground ring-1 ring-border';
        }
    }

    function money(minor: number | null, code: string): string {
        if (minor === null) return '—';
        const major = minor / 100;
        return new Intl.NumberFormat('en-IN', {
            style: 'currency',
            currency: code,
            maximumFractionDigits: 0,
        }).format(major);
    }
</script>

<AdminLayout {appName}>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold tracking-tight">Campaigns</h1>
                <p class="text-sm text-muted-foreground">
                    {pagination.total} total
                    · showing {(pagination.page - 1) * pagination.per_page + 1}–{Math.min(pagination.page * pagination.per_page, pagination.total)}
                </p>
            </div>
            <Button href="/admin/campaigns/new">
                <PlusCircle class="h-4 w-4" />
                <span>New campaign</span>
            </Button>
        </div>

        <div class="overflow-hidden rounded-lg border border-border bg-card">
            <table class="w-full text-sm">
                <thead class="border-b border-border bg-muted/40 text-xs uppercase tracking-wider text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">Title</th>
                        <th class="px-4 py-3 text-left font-medium">Slug</th>
                        <th class="px-4 py-3 text-left font-medium">Category</th>
                        <th class="px-4 py-3 text-left font-medium">State</th>
                        <th class="px-4 py-3 text-right font-medium">Target</th>
                        <th class="px-4 py-3 text-right font-medium">Updated</th>
                        <th class="px-4 py-3 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    {#each campaigns as c (c.id)}
                        <tr class="hover:bg-muted/30">
                            <td class="px-4 py-3 font-medium">
                                <div class="flex items-center gap-2">
                                    {#if c.is_featured}
                                        <span
                                            class="rounded-full bg-primary/10 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-primary"
                                            title="Featured"
                                        >
                                            Featured
                                        </span>
                                    {/if}
                                    <span class="truncate">{c.title}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{c.slug}</td>
                            <td class="px-4 py-3 text-muted-foreground">{c.category}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {stateClass(c.state)}">
                                    {c.state}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                {money(c.target_amount_minor, c.currency_code)}
                            </td>
                            <td class="px-4 py-3 text-right text-xs text-muted-foreground">
                                {new Date(c.updated_at).toLocaleDateString()}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a
                                    href="/admin/campaigns/{c.id}/edit"
                                    class="font-medium text-primary hover:underline"
                                >
                                    Edit
                                </a>
                            </td>
                        </tr>
                    {/each}
                    {#if campaigns.length === 0}
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-muted-foreground">
                                No campaigns yet.
                                <a href="/admin/campaigns/new" class="text-primary hover:underline">
                                    Create the first one.
                                </a>
                            </td>
                        </tr>
                    {/if}
                </tbody>
            </table>
        </div>

        {#if pagination.total > pagination.per_page}
            <div class="flex items-center justify-between">
                <Button
                    variant="outline"
                    disabled={pagination.page <= 1}
                    href="/admin/campaigns?page={pagination.page - 1}"
                >
                    <ChevronLeft class="h-4 w-4" />
                    <span>Previous</span>
                </Button>
                <span class="text-sm text-muted-foreground">
                    Page {pagination.page} of {Math.ceil(pagination.total / pagination.per_page)}
                </span>
                <Button
                    variant="outline"
                    disabled={!pagination.has_more}
                    href="/admin/campaigns?page={pagination.page + 1}"
                >
                    <span>Next</span>
                    <ChevronRight class="h-4 w-4" />
                </Button>
            </div>
        {/if}
    </div>
</AdminLayout>
