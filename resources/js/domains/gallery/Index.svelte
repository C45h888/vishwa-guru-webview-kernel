<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import GalleryCard from '$shared/components/GalleryCard.svelte';
    import type {
        GallerySummaryProps,
        PaginationProps,
        AppPageProps,
    } from '$shared/lib/inertia';

    let {
        galleries,
        pagination,
        appName,
    }: AppPageProps<{
        galleries: GallerySummaryProps[];
        pagination: PaginationProps;
    }> = $props();

    function goToPage(page: number) {
        router.get('/gallery', { page }, { preserveScroll: true });
    }
</script>

<svelte:head>
    <title>Gallery — {appName}</title>
</svelte:head>

<PublicLayout>
    <section class="space-y-6">
        <header class="space-y-2">
            <h1 class="text-3xl font-semibold">Gallery</h1>
            <p class="text-sm text-muted-foreground">
                {pagination.total} published gallery{pagination.total === 1 ? '' : 'ies'}
            </p>
        </header>

        {#if galleries.length === 0}
            <p class="text-sm text-muted-foreground">
                No galleries yet. The site is being prepared.
            </p>
        {:else}
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                {#each galleries as gallery (gallery.id)}
                    <GalleryCard
                        {gallery}
                        href={`/gallery/${gallery.slug}`}
                    />
                {/each}
            </div>

            {#if pagination.has_more || pagination.page > 1}
                <nav class="flex items-center justify-between" aria-label="Pagination">
                    <button
                        type="button"
                        class="text-sm disabled:opacity-50"
                        disabled={pagination.page <= 1}
                        onclick={() => goToPage(pagination.page - 1)}
                    >
                        ← Previous
                    </button>
                    <span class="text-sm text-muted-foreground">
                        Page {pagination.page}
                    </span>
                    <button
                        type="button"
                        class="text-sm disabled:opacity-50"
                        disabled={!pagination.has_more}
                        onclick={() => goToPage(pagination.page + 1)}
                    >
                        Next →
                    </button>
                </nav>
            {/if}
        {/if}
    </section>
</PublicLayout>
