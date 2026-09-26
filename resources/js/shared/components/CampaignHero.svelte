<script lang="ts">
    /**
     * CampaignHero — full-viewport campaign cover.
     *
     * Doctrine:
     *   - The image IS the welcome. The hero takes 100vh on first paint
     *     and the title sits in a quiet overlay at the bottom-left so the
     *     photo stays the visual lead.
     *   - Reduced motion: no parallax, no fade-in. The image loads with
     *     `eager` (it's the LCP element on this page). The M2 motion pass
     *     will gate any future entry animation on the viewport kernel's
     *     `reducedMotion` flag.
     *   - Admin: when `$page.props.authUser?.role === 'admin'`, the
     *     AdminEditOverlay floats over the image with a link to
     *     `/admin/campaigns/{id}/edit`. Non-admin visitors see zero
     *     DOM impact.
     *
     * Props are intentionally the campaign-detail shape that the page
     * already has — no remapping. The fallback image (DEFAULT_HERO_IMAGE)
     * is used only when the campaign row has no `cover_image_file_id`.
     */
    import { page } from '@inertiajs/svelte';
    import {
        ArrowRight,
        Clock,
        Heart,
    } from 'lucide-svelte';
    import PublicMediaImage from './PublicMediaImage.svelte';
    import AdminEditOverlay from './AdminEditOverlay.svelte';
    import TrustBadgeRow from './TrustBadgeRow.svelte';
    import type { CampaignDetailProps } from '$shared/lib/inertia';

    export const DEFAULT_HERO_IMAGE = '/show-images/hero.png';

    interface Props {
        campaign: CampaignDetailProps;
        donateHref: string;
        categoryLabel: string;
        causeEyebrow: string;
        stateLabel: string;
        dateLabel: string;
        isFeatured: boolean;
    }

    let {
        campaign,
        donateHref,
        categoryLabel,
        causeEyebrow,
        stateLabel,
        dateLabel,
        isFeatured,
    }: Props = $props();

    const heroImage = $derived(campaign.cover_image ?? null);
    const hasImage = $derived(heroImage !== null);
</script>

<section class="relative h-[100svh] min-h-[640px] w-full overflow-hidden bg-foreground">
    {#if $page.props.authUser?.role === 'admin'}
        <AdminEditOverlay
            href={`/admin/campaigns/${campaign.id}/edit`}
            srLabel={`Edit campaign: ${campaign.title}`}
        />
    {/if}

    {#if hasImage}
        <PublicMediaImage
            media={heroImage!}
            alt={campaign.title}
            class="absolute inset-0 h-full w-full object-cover"
            loading="eager"
            fetchpriority="high"
        />
    {:else}
        <img
            src={DEFAULT_HERO_IMAGE}
            alt={campaign.title}
            class="absolute inset-0 h-full w-full object-cover"
            loading="eager"
            fetchpriority="high"
            decoding="async"
        />
    {/if}

    <!-- Scrim: bottom 60% darkened so the overlay text reads. Top stays
         open so the photograph still breathes. -->
    <div
        class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/80 via-black/35 to-transparent"
        aria-hidden="true"
    ></div>

    <!-- Mandala watermark, top-right. Subtle, behind the scrim. -->
    <div
        class="pointer-events-none absolute -right-24 top-8 hidden opacity-[0.10] sm:block"
        aria-hidden="true"
    >
        <svg
            viewBox="0 0 200 200"
            class="h-64 w-64 text-white"
            fill="none"
            stroke="currentColor"
            stroke-width="0.5"
        >
            <circle cx="100" cy="100" r="95" />
            <circle cx="100" cy="100" r="75" />
            <circle cx="100" cy="100" r="55" />
            {#each Array.from({ length: 12 }) as _, i (i)}
                <line
                    x1="100"
                    y1="5"
                    x2="100"
                    y2="195"
                    transform={`rotate(${i * 15} 100 100)`}
                />
            {/each}
        </svg>
    </div>

    <!-- Bottom-left overlay: title + meta + CTA -->
    <div class="absolute inset-x-0 bottom-0">
        <div class="container pb-10 lg:pb-16">
            <div class="max-w-3xl space-y-5 text-white">
                <p
                    class="text-xs font-semibold uppercase tracking-[0.3em] text-saffron-200"
                >
                    {causeEyebrow}
                </p>

                <div
                    class="flex flex-wrap items-center gap-2 text-[10px] font-semibold uppercase tracking-[0.2em]"
                >
                    <span
                        class="rounded-sm border border-white/40 bg-white/10 px-2 py-0.5 text-white backdrop-blur-sm"
                    >
                        {categoryLabel}
                    </span>
                    {#if isFeatured}
                        <span
                            class="rounded-sm bg-primary px-2 py-0.5 text-primary-foreground"
                        >
                            Featured
                        </span>
                    {/if}
                    <span
                        class="rounded-sm border border-white/30 bg-black/20 px-2 py-0.5 text-white/85 backdrop-blur-sm"
                    >
                        {stateLabel}
                    </span>
                    <span
                        class="inline-flex items-center gap-1.5 rounded-sm border border-white/30 bg-black/20 px-2 py-0.5 text-white/85 backdrop-blur-sm"
                    >
                        <Clock class="h-3 w-3" aria-hidden="true" />
                        {dateLabel}
                    </span>
                </div>

                <h1
                    class="font-serif text-4xl font-semibold leading-[1.05] tracking-tight lg:text-6xl"
                >
                    {campaign.title}
                </h1>

                {#if campaign.short_description}
                    <p
                        class="max-w-2xl text-base leading-relaxed text-white/85 lg:text-lg"
                    >
                        {campaign.short_description}
                    </p>
                {/if}

                <div class="flex flex-wrap items-center gap-3 pt-2">
                    <a
                        href={donateHref}
                        class="inline-flex h-12 items-center justify-center whitespace-nowrap rounded-sm bg-primary px-7 text-xs font-semibold uppercase tracking-[0.2em] text-primary-foreground transition-colors hover:bg-accent"
                    >
                        <Heart
                            class="mr-2 h-4 w-4"
                            aria-hidden="true"
                        />
                        Donate to this cause
                        <ArrowRight
                            class="ml-2 h-4 w-4"
                            aria-hidden="true"
                        />
                    </a>
                    <a
                        href="/campaigns"
                        class="inline-flex h-12 items-center justify-center whitespace-nowrap rounded-sm border border-white/40 bg-white/5 px-6 text-xs font-semibold uppercase tracking-[0.2em] text-white backdrop-blur-sm transition-colors hover:border-white hover:bg-white/10"
                    >
                        All causes
                    </a>
                </div>

                <div class="pt-1">
                    <TrustBadgeRow tone="inverse" />
                </div>
            </div>
        </div>
    </div>
</section>
