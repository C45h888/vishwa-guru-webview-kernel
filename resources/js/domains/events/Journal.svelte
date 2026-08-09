<script lang="ts">
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import MandalaDecoration from '$shared/components/MandalaDecoration.svelte';
    import TrustBadgeRow from '$shared/components/TrustBadgeRow.svelte';
    import BottomCtaBand from '$shared/components/BottomCtaBand.svelte';
    import Filter from 'lucide-svelte/icons/filter';
    import type { AppPageProps } from '$shared/lib/inertia';

    interface JournalCard {
        slug: string;
        category: string;
        category_label: string;
        eyebrow: string;
        title: string;
        excerpt: string;
        image: string;
        image_alt: string;
    }

    interface JournalCategory {
        key: string;
        label: string;
        count: number;
    }

    let {
        cards,
        categories,
        appName,
    }: AppPageProps<{
        cards: JournalCard[];
        categories: JournalCategory[];
    }> = $props();

    // Group cards by category, preserving catalog order
    const cardsByCategory = $derived.by(() => {
        const groups: { key: string; label: string; cards: JournalCard[] }[] = [];
        const seen = new Map<string, number>();
        for (const card of cards) {
            const idx = seen.get(card.category);
            if (idx === undefined) {
                seen.set(card.category, groups.length);
                groups.push({ key: card.category, label: card.category_label, cards: [card] });
            } else {
                groups[idx].cards.push(card);
            }
        }
        return groups;
    });

    let activeCategory = $state<string | null>(null);

    const visibleGroups = $derived(
        activeCategory === null
            ? cardsByCategory
            : cardsByCategory.filter((g) => g.key === activeCategory),
    );
</script>

<svelte:head>
    <title>Events Journal — {appName}</title>
</svelte:head>

<PublicLayout>
    <section class="relative overflow-hidden bg-background">
        <div
            class="pointer-events-none absolute -right-20 top-0 opacity-[0.08]"
            aria-hidden="true"
        >
            <MandalaDecoration size={320} tint="gold" />
        </div>

        <div class="container relative py-16 lg:py-20">
            <div class="mx-auto max-w-3xl space-y-5 text-center">
                <p
                    class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                >
                    Past events
                </p>
                <h1 class="font-serif text-4xl font-semibold lg:text-5xl">
                    The Events Journal
                </h1>
                <p class="text-base text-muted-foreground lg:text-lg">
                    Festivals, ceremonies, performances, and community
                    gatherings at the temple — the year-round rhythm of
                    practice and the people who make it possible.
                </p>
                <div class="pt-2">
                    <TrustBadgeRow />
                </div>
            </div>
        </div>
    </section>

    <section class="container space-y-6 py-8 lg:py-10">
        <div
            class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground"
            role="tablist"
            aria-label="Event categories"
        >
            <Filter class="h-3.5 w-3.5" aria-hidden="true" />
            <button
                type="button"
                role="tab"
                aria-selected={activeCategory === null}
                class="rounded-full border px-3 py-1 transition-colors {activeCategory ===
                null
                    ? 'border-primary bg-primary text-primary-foreground'
                    : 'border-border bg-background text-foreground/80 hover:border-primary/40'}"
                onclick={() => (activeCategory = null)}
            >
                All ({cards.length})
            </button>
            {#each categories as cat (cat.key)}
                <button
                    type="button"
                    role="tab"
                    aria-selected={activeCategory === cat.key}
                    class="rounded-full border px-3 py-1 transition-colors {activeCategory ===
                    cat.key
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'border-border bg-background text-foreground/80 hover:border-primary/40'}"
                    onclick={() => (activeCategory = cat.key)}
                >
                    {cat.label} ({cat.count})
                </button>
            {/each}
        </div>
    </section>

    {#each visibleGroups as group (group.key)}
        <section class="container space-y-6 py-8 lg:py-10" id={group.key}>
            <div class="space-y-2">
                <p
                    class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                >
                    {group.label}
                </p>
                <h2 class="font-serif text-2xl font-semibold lg:text-3xl">
                    {group.label}
                    <span class="ml-2 text-base font-normal text-muted-foreground">
                        {group.cards.length} {group.cards.length === 1 ? 'entry' : 'entries'}
                    </span>
                </h2>
            </div>

            <div class="space-y-4">
                {#each group.cards as card (card.slug)}
                    <a
                        href={`/events/journal/${card.slug}`}
                        class="group block overflow-hidden rounded-md border border-border/60 bg-background transition-colors hover:border-primary/40"
                    >
                        <div class="grid grid-cols-1 sm:grid-cols-12">
                            <div class="sm:col-span-4">
                                <div class="aspect-[4/3] w-full overflow-hidden sm:aspect-auto sm:h-full">
                                    <img
                                        src={card.image}
                                        alt={card.image_alt}
                                        class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.03]"
                                        loading="lazy"
                                        decoding="async"
                                    />
                                </div>
                            </div>
                            <div class="space-y-2 p-5 sm:col-span-8 sm:p-6">
                                <p
                                    class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                                >
                                    {card.eyebrow}
                                </p>
                                <h3
                                    class="font-serif text-xl font-semibold leading-tight transition-colors group-hover:text-primary lg:text-2xl"
                                >
                                    {card.title}
                                </h3>
                                <p
                                    class="text-sm leading-relaxed text-muted-foreground"
                                >
                                    {card.excerpt}
                                </p>
                                <span
                                    class="inline-flex items-center gap-1 pt-1 text-sm font-medium text-primary transition-transform group-hover:translate-x-0.5"
                                >
                                    Read the entry
                                    <span aria-hidden="true">→</span>
                                </span>
                            </div>
                        </div>
                    </a>
                {/each}
            </div>
        </section>
    {/each}

    <BottomCtaBand
        title="Support the next chapter"
        body="The journal records the trust\u2019s recent work and the progress of the land acquisition campaign. Your donation moves the next chapter forward."
        ctaLabel="Donate to the land acquisition"
        ctaHref="/donate"
    />
</PublicLayout>
