<script lang="ts">
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import PublicMediaImage from '$shared/components/PublicMediaImage.svelte';
    import MandalaDecoration from '$shared/components/MandalaDecoration.svelte';
    import TrustBadgeRow from '$shared/components/TrustBadgeRow.svelte';
    import DonateCtaBand from '$shared/components/DonateCtaBand.svelte';
    import PlaceholderCard from '$shared/components/PlaceholderCard.svelte';
    import { Separator } from '$shared/ui/separator';
    import { Users } from 'lucide-svelte';
    import { FALLBACK_ABOUT_PAGE_CONTENT } from './about-fallbacks';
    import type { AboutPageProps } from './types';

    let { page, aboutContent, heroBanners, html, appName }: AboutPageProps =
        $props();

    const content = $derived(
        aboutContent === null ? FALLBACK_ABOUT_PAGE_CONTENT : aboutContent,
    );

    const trustees = $derived(content.trustees);
    const hasTrustees = $derived(trustees.length > 0);
</script>

<svelte:head>
    <title>{page.title} — {appName}</title>
    {#if page.meta_description}
        <meta name="description" content={page.meta_description} />
    {/if}
</svelte:head>

<PublicLayout>
    <!-- ═══ HERO ═══ -->
    <section class="relative overflow-hidden bg-background">
        <div
            class="pointer-events-none absolute right-0 top-0 opacity-15"
            aria-hidden="true"
        >
            <MandalaDecoration size={180} tint="gold" />
        </div>

        <div class="container relative py-14 lg:py-20">
            <div class="mx-auto max-w-3xl space-y-5 text-center">
                <h1 class="font-serif text-4xl font-semibold lg:text-5xl">
                    {page.title}
                </h1>
                {#if page.meta_description}
                    <p class="text-base text-muted-foreground lg:text-lg">
                        {page.meta_description}
                    </p>
                {/if}
                <div class="pt-1">
                    <TrustBadgeRow />
                </div>
            </div>
        </div>
    </section>

    <!-- ═══ VALUES ═══ -->
    <section class="container py-20 lg:py-28">
        <div
            class="grid grid-cols-1 items-center gap-10 lg:grid-cols-12 lg:gap-16"
        >
            <div class="space-y-5 lg:col-span-7">
                <p
                    class="text-xs font-semibold uppercase tracking-[0.2em] text-primary"
                >
                    {content.values.eyebrow}
                </p>
                <h2
                    class="font-serif text-3xl font-semibold leading-tight lg:text-4xl"
                >
                    {content.values.title}
                </h2>
                <p
                    class="max-w-xl text-base leading-relaxed text-muted-foreground lg:text-lg"
                >
                    {content.values.body}
                </p>
            </div>
            <div class="relative lg:col-span-5">
                <div
                    class="pointer-events-none absolute -right-12 -top-12 opacity-15"
                    aria-hidden="true"
                >
                    <MandalaDecoration size={260} tint="gold" />
                </div>
                <div class="relative">
                    {#if content.values.image}
                        <div
                            class="overflow-hidden rounded-md border border-border/40"
                        >
                            <PublicMediaImage
                                media={content.values.image}
                                alt={content.values.alt_text ??
                                    content.values.title}
                                class="aspect-square w-full object-cover"
                            />
                        </div>
                    {:else}
                        <div
                            class="aspect-square overflow-hidden rounded-md border border-border/40 bg-gradient-to-br from-primary/15 via-ivory to-primary/10"
                        >
                            <div
                                class="flex h-full w-full items-center justify-center"
                            >
                                <div
                                    class="rounded-full border border-primary/30 bg-primary/5 px-3 py-1 text-xs font-medium text-primary/70"
                                >
                                    {content.values.eyebrow}
                                </div>
                            </div>
                            <div
                                class="pointer-events-none absolute inset-0 opacity-30"
                                style="background: radial-gradient(circle at 30% 20%, hsl(42 70% 48% / 0.08), transparent 50%), radial-gradient(circle at 70% 80%, hsl(42 70% 48% / 0.06), transparent 50%);"
                                aria-hidden="true"
                            ></div>
                        </div>
                    {/if}
                </div>
            </div>
        </div>
    </section>

    <Separator class="my-0" />

    <!-- ═══ TIMELINE ═══ -->
    <section class="bg-muted/30 py-20 lg:py-28">
        <div class="container">
            <div class="mx-auto max-w-2xl space-y-4 text-center">
                <p
                    class="text-xs font-semibold uppercase tracking-[0.2em] text-primary"
                >
                    Our Journey
                </p>
                <h2 class="font-serif text-3xl font-semibold lg:text-4xl">
                    Milestones in the trust's life
                </h2>
                <p class="text-base text-muted-foreground">
                    The arc of the trust — from the founding of the temple
                    to the launch of this public digital platform.
                </p>
            </div>

            <ol class="mx-auto mt-12 max-w-3xl space-y-10">
                {#each content.timeline as entry (entry.year)}
                    <li class="grid grid-cols-[auto,1fr] gap-x-6">
                        <div class="flex flex-col items-center">
                            <div
                                class="font-serif text-4xl font-semibold text-primary lg:text-5xl"
                            >
                                {entry.year}
                            </div>
                            <div
                                class="mt-2 h-full w-px flex-1 bg-primary/30"
                                aria-hidden="true"
                            ></div>
                        </div>
                        <div class="space-y-2 pb-2">
                            <h3
                                class="font-serif text-xl font-semibold lg:text-2xl"
                            >
                                {entry.title}
                            </h3>
                            <p
                                class="text-base leading-relaxed text-muted-foreground"
                            >
                                {entry.description}
                            </p>
                        </div>
                    </li>
                {/each}
            </ol>
        </div>
    </section>

    <!-- ═══ TRUSTEES ═══ -->
    <section class="container py-20 lg:py-28">
        <div class="mx-auto max-w-2xl space-y-4 text-center">
            <p
                class="text-xs font-semibold uppercase tracking-[0.2em] text-primary"
            >
                Leadership
            </p>
            <h2 class="font-serif text-3xl font-semibold lg:text-4xl">
                Trustees of the trust
            </h2>
            <p class="text-base text-muted-foreground">
                The board and officers responsible for the day-to-day
                stewardship of the temple.
            </p>
        </div>

        <div
            class="mt-12 grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3"
        >
            {#if hasTrustees}
                {#each trustees as trustee (trustee.name)}
                    <article
                        class="overflow-hidden rounded-md border border-border/40 bg-card"
                    >
                        {#if trustee.photo}
                            <PublicMediaImage
                                media={trustee.photo}
                                alt={trustee.name}
                                class="aspect-[3/4] w-full object-cover"
                            />
                        {:else}
                            <div
                                class="flex aspect-[3/4] w-full items-center justify-center bg-gradient-to-br from-primary/15 via-ivory to-primary/10"
                            >
                                <div
                                    class="rounded-full border border-primary/30 bg-primary/5 px-3 py-1 text-xs font-medium text-primary/70"
                                >
                                    {trustee.role}
                                </div>
                            </div>
                        {/if}
                        <div class="space-y-2 p-5">
                            <h3
                                class="font-serif text-lg font-semibold lg:text-xl"
                            >
                                {trustee.name}
                            </h3>
                            <p
                                class="text-xs font-semibold uppercase tracking-[0.2em] text-primary"
                            >
                                {trustee.role}
                            </p>
                            {#if trustee.bio}
                                <p
                                    class="text-sm leading-relaxed text-muted-foreground"
                                >
                                    {trustee.bio}
                                </p>
                            {/if}
                        </div>
                    </article>
                {/each}
            {:else}
                {#each [1, 2, 3] as slot (slot)}
                    <PlaceholderCard
                        icon={Users}
                        title="Trustee profile"
                        hint="A trustee card will appear here when published"
                    />
                {/each}
            {/if}
        </div>
    </section>

    <!-- ═══ DONATE CTA BAND (CMS-driven) ═══ -->
    <DonateCtaBand content={content.donate_cta} />

    <!-- ═══ TRUST STRIP ═══ -->
    <section class="border-y border-border/40 bg-ivory py-8">
        <div class="container">
            <TrustBadgeRow />
        </div>
    </section>

    <!-- ═══ OPTIONAL CMS PROSE BODY ═══ -->
    {#if html && html.trim() !== ''}
        <section class="container py-12 lg:py-16">
            <div class="prose prose-stone mx-auto max-w-3xl">
                {@html html}
            </div>
        </section>
    {/if}
</PublicLayout>
