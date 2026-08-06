<script lang="ts">
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import EventHeroSlideshow from '$shared/components/EventHeroSlideshow.svelte';
    import BottomCtaBand from '$shared/components/BottomCtaBand.svelte';
    import { EVENT_SLIDES } from '$domains/events/event-slides';
import { Pencil } from 'lucide-svelte';
import { page } from '@inertiajs/svelte';
    import type { AppPageProps } from '$shared/lib/inertia';

    interface PastArticleCard {
        slug: string;
        category: string;
        category_label: string;
        eyebrow: string;
        title: string;
        excerpt: string;
        image: string;
        image_alt: string;
    }

    let {
        pastArticles,
        appName,
    }: AppPageProps<{
        pastArticles: PastArticleCard[];
    }> = $props();

    const hasArticles = $derived(pastArticles.length > 0);

    // Current events: the recurring featured entries from the journal
    // catalog (the three that anchor the hero slideshow).
    const currentSlugs = [
        'varalakshmi-vratam',
        'bharatanatyam-vrinda-samsthanam',
        'brahmotsavam',
    ];
    const currentArticles = $derived(
        currentSlugs
            .map((slug) => pastArticles.find((a) => a.slug === slug))
            .filter((a): a is PastArticleCard => a !== undefined),
    );
    const hasCurrent = $derived(currentArticles.length > 0);
const isAdmin = $derived($page.props.authUser?.role === 'admin');
</script>

<svelte:head>
    <title>Events — {appName}</title>
</svelte:head>

<PublicLayout>
    {#if isAdmin}
        <section class="border-y border-border/40 bg-background">
            <div class="container flex items-center justify-between py-3 text-xs text-muted-foreground">
                <nav aria-label="Breadcrumb">
                    <ol class="flex items-center gap-1">
                        <li><a href="/" class="hover:text-foreground">Home</a></li>
                        <li aria-hidden="true">›</li>
                        <li aria-current="page" class="text-foreground">Events</li>
                    </ol>
                </nav>
                <a
                    href="/admin/events/new"
                    class="inline-flex items-center gap-1.5 rounded-md border border-primary/30 bg-primary/5 px-2.5 py-1 font-medium text-primary transition-colors hover:bg-primary/10"
                >
                    <Pencil class="h-3 w-3" />
                    <span>New event</span>
                </a>
            </div>
        </section>
    {/if}
    <EventHeroSlideshow slides={EVENT_SLIDES} />

    {#if hasCurrent}
        <section class="container space-y-6 py-12 lg:py-16">
            <div class="flex items-end justify-between gap-4">
                <div class="space-y-2">
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                    >
                        Current events
                    </p>
                    <h2 class="font-serif text-3xl font-semibold lg:text-4xl">
                        Featured at the temple
                    </h2>
                </div>
                <a
                    href="/events/journal"
                    class="inline-flex items-center gap-1 text-sm font-medium text-primary transition-transform hover:translate-x-0.5"
                >
                    Read the events journal
                    <span aria-hidden="true">→</span>
                </a>
            </div>

            <div class="space-y-4">
                {#each currentArticles as article (article.slug)}
                    <a
                        href={`/events/journal/${article.slug}`}
                        class="group block overflow-hidden rounded-md border border-border/60 bg-background transition-colors hover:border-primary/40"
                    >
                        <div class="grid grid-cols-1 sm:grid-cols-12">
                            <div class="sm:col-span-4">
                                <div
                                    class="aspect-[4/3] w-full overflow-hidden sm:aspect-auto sm:h-full"
                                >
                                    <img
                                        src={article.image}
                                        alt={article.image_alt}
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
                                    {article.eyebrow} · {article.category_label}
                                </p>
                                <h3
                                    class="font-serif text-xl font-semibold leading-tight transition-colors group-hover:text-primary lg:text-2xl"
                                >
                                    {article.title}
                                </h3>
                                <p
                                    class="text-sm leading-relaxed text-muted-foreground"
                                >
                                    {article.excerpt}
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
    {/if}

    <section class="container space-y-6 py-12 lg:py-16">
        <div class="flex items-end justify-between gap-4">
            <h2 class="font-serif text-3xl font-semibold lg:text-4xl">
                Past events
            </h2>
            <a
                href="/events/journal"
                class="inline-flex items-center gap-1 text-sm font-medium text-primary transition-transform hover:translate-x-0.5"
            >
                Read the events journal
                <span aria-hidden="true">→</span>
            </a>
        </div>

        {#if hasArticles}
            <div class="space-y-4">
                {#each pastArticles as article (article.slug)}
                    <a
                        href={`/events/journal/${article.slug}`}
                        class="group block overflow-hidden rounded-md border border-border/60 bg-background transition-colors hover:border-primary/40"
                    >
                        <div class="grid grid-cols-1 sm:grid-cols-12">
                            <div class="sm:col-span-4">
                                <div
                                    class="aspect-[4/3] w-full overflow-hidden sm:aspect-auto sm:h-full"
                                >
                                    <img
                                        src={article.image}
                                        alt={article.image_alt}
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
                                    {article.eyebrow} · {article.category_label}
                                </p>
                                <h3
                                    class="font-serif text-xl font-semibold leading-tight transition-colors group-hover:text-primary lg:text-2xl"
                                >
                                    {article.title}
                                </h3>
                                <p
                                    class="text-sm leading-relaxed text-muted-foreground"
                                >
                                    {article.excerpt}
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
        {:else}
            <p class="text-sm text-muted-foreground">
                No past events yet.
            </p>
        {/if}
    </section>

    <BottomCtaBand
        title="Support our events"
        body="Festival sponsorships and event seva keep our traditions alive."
        ctaLabel="Sponsor an event"
        ctaHref="/donate"
    />
</PublicLayout>
