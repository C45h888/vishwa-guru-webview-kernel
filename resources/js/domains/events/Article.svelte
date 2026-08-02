<script lang="ts">
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import PublicMediaImage from '$shared/components/PublicMediaImage.svelte';
    import MandalaDecoration from '$shared/components/MandalaDecoration.svelte';
    import TrustBadgeRow from '$shared/components/TrustBadgeRow.svelte';
    import BottomCtaBand from '$shared/components/BottomCtaBand.svelte';
    import type { AppPageProps } from '$shared/lib/inertia';

    interface Article {
        slug: string;
        category: string;
        category_label: string;
        eyebrow: string;
        title: string;
        excerpt: string;
        image: string;
        image_alt: string;
        body: string[];
    }

    interface RelatedArticle {
        slug: string;
        title: string;
        eyebrow: string;
        excerpt: string;
        image: string;
        image_alt: string;
    }

    let {
        article,
        related,
        appName,
    }: AppPageProps<{
        article: Article;
        related: RelatedArticle[];
    }> = $props();

    const hasRelated = $derived(related.length > 0);
</script>

<svelte:head>
    <title>{article.title} — {appName}</title>
    {#if article.excerpt}
        <meta name="description" content={article.excerpt} />
    {/if}
</svelte:head>

<PublicLayout>
    <article>
        <!-- ═══ HERO IMAGE ═══ -->
        <section class="relative overflow-hidden bg-ivory">
            <div
                class="pointer-events-none absolute -right-20 top-0 opacity-[0.07]"
                aria-hidden="true"
            >
                <MandalaDecoration size={320} tint="gold" />
            </div>

            <div class="container relative pt-8 lg:pt-12">
                <a
                    href="/events/journal"
                    class="inline-flex items-center gap-1 text-sm text-muted-foreground transition-colors hover:text-primary"
                >
                    <span aria-hidden="true">←</span>
                    Back to the journal
                </a>
            </div>

            <div class="container relative pb-10 pt-6 lg:pb-16">
                <div class="mx-auto max-w-4xl space-y-4 text-center">
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                    >
                        {article.eyebrow} · {article.category_label}
                    </p>
                    <h1
                        class="font-serif text-3xl font-semibold leading-tight lg:text-5xl"
                    >
                        {article.title}
                    </h1>
                    <p
                        class="mx-auto max-w-2xl text-base leading-relaxed text-muted-foreground lg:text-lg"
                    >
                        {article.excerpt}
                    </p>
                </div>
            </div>

            <div class="container relative pb-10 lg:pb-16">
                <div
                    class="overflow-hidden rounded-md border border-border/40 bg-background"
                >
                    <img
                        src={article.image}
                        alt={article.image_alt}
                        class="aspect-[16/9] w-full object-cover"
                        loading="eager"
                        decoding="async"
                    />
                </div>
            </div>
        </section>

        <!-- ═══ BODY ═══ -->
        <section class="container py-12 lg:py-20">
            <div class="mx-auto max-w-3xl space-y-6">
                <p
                    class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                >
                    The entry
                </p>
                <div class="space-y-5 text-base leading-relaxed text-foreground/90 lg:text-lg">
                    {#each article.body as paragraph, i (i)}
                        <p>{paragraph}</p>
                    {/each}
                </div>
            </div>
        </section>

        <!-- ═══ RELATED ═══ -->
        {#if hasRelated}
            <section class="bg-ivory py-16 lg:py-20">
                <div class="container space-y-6">
                    <div class="space-y-2">
                        <p
                            class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                        >
                            More from {article.category_label}
                        </p>
                        <h2
                            class="font-serif text-2xl font-semibold lg:text-3xl"
                        >
                            Related entries
                        </h2>
                    </div>
                    <div
                        class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3"
                    >
                        {#each related as rel (rel.slug)}
                            <a
                                href={`/events/journal/${rel.slug}`}
                                class="group block overflow-hidden rounded-md border border-border/60 bg-background transition-colors hover:border-primary/40"
                            >
                                <div class="aspect-[4/3] w-full overflow-hidden">
                                    <img
                                        src={rel.image}
                                        alt={rel.image_alt}
                                        class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.03]"
                                        loading="lazy"
                                        decoding="async"
                                    />
                                </div>
                                <div class="space-y-2 p-5">
                                    <p
                                        class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                                    >
                                        {rel.eyebrow}
                                    </p>
                                    <h3
                                        class="font-serif text-lg font-semibold leading-tight transition-colors group-hover:text-primary"
                                    >
                                        {rel.title}
                                    </h3>
                                    <p
                                        class="text-sm leading-relaxed text-muted-foreground"
                                    >
                                        {rel.excerpt}
                                    </p>
                                </div>
                            </a>
                        {/each}
                    </div>
                </div>
            </section>
        {/if}
    </article>

    <BottomCtaBand
        title="Sustain the work behind every entry"
        body="The journal is a record of what the trust does on a daily, weekly, and seasonal basis. Your donation keeps the next entry possible."
        ctaLabel="Donate to the trust"
        ctaHref="/donate"
    />
</PublicLayout>
