<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import Money from '$shared/components/Money.svelte';
    import CampaignProgress from '$shared/components/CampaignProgress.svelte';
    import CampaignCard from '$shared/components/CampaignCard.svelte';
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import PublicMediaImage from '$shared/components/PublicMediaImage.svelte';
    import MandalaDecoration from '$shared/components/MandalaDecoration.svelte';
    import TrustBadgeRow from '$shared/components/TrustBadgeRow.svelte';
    import AdminEditOverlay from '$shared/components/AdminEditOverlay.svelte';
    import {
        campaignFallbackFor,
        OFFERING_FLOW,
        DONATION_FAQ,
    } from '$shared/lib/campaign-fallbacks';
    import { currencySymbol, currencyName } from '$shared/lib/currency';
    import {
        ArrowRight,
        CheckCircle2,
        ChevronDown,
        Clock,
        Copy,
        Heart,
        Mail,
        Share2,
        Sparkles,
        HandHeart,
        Wrench,
        Utensils,
        Users,
    } from 'lucide-svelte';
    import type {
        CampaignDetailProps,
        CampaignProgressProps,
        CampaignSummaryProps,
        AppPageProps,
    } from '$shared/lib/inertia';

    /**
     * Default static asset hero image — used when the campaign row has no
     * `cover_image_file_id` (Phase 2 image migration will replace this).
     * Phase 2 swaps this fallback for the per-campaign public_media row.
     */
    const DEFAULT_HERO_IMAGE = '/show-images/hero.png';
    const ABOUT_FEATURE_IMAGE = '/show-images/about.jpg';
    const SUPPORTS_HEADER_IMAGE = '/show-images/supports.jpg';

    let {
        campaign,
        progress,
        relatedCampaigns = [],
        appName,
    }: AppPageProps<{
        campaign: CampaignDetailProps;
        progress: CampaignProgressProps[];
        relatedCampaigns?: (CampaignSummaryProps & {
            raised_amount_minor?: number | null;
            donor_count?: number | null;
        })[];
    }> = $props();

    const targetByCurrency = $derived({
        [campaign.currency_code]: campaign.target_amount_minor,
    } as Record<string, number | null>);

    const heroImage = $derived(campaign.cover_image ?? null);
    const hasImage = $derived(heroImage !== null);

    const fallback = $derived(campaignFallbackFor(campaign.category));

    const categoryLabel = $derived(
        campaign.category
            .replace(/_/g, ' ')
            .replace(/\b\w/g, (c) => c.toUpperCase()),
    );

    // Multi-paragraph rendering of the About copy. The config string
    // contains 4 sentences; split on full-stops so each becomes its own
    // paragraph instead of one block.
    const aboutParagraphs = $derived(
        fallback.about
            .split(/(?<=[.!?])\s+/)
            .map((p) => p.trim())
            .filter((p) => p.length > 0),
    );

    const hasProgress = $derived(
        progress.length > 0 && progress.some((p) => p.raised_amount_minor > 0),
    );

    const hasTarget = $derived(campaign.target_amount_minor !== null);
    const isOpenGoal = $derived(!hasTarget);

    const dateState = $derived.by(() => {
        const now = Date.now();
        const start = campaign.starts_at
            ? new Date(campaign.starts_at).getTime()
            : null;
        const end = campaign.ends_at
            ? new Date(campaign.ends_at).getTime()
            : null;

        const startLabel = start
            ? new Date(start).toLocaleDateString('en-IN', {
                  day: 'numeric',
                  month: 'short',
                  year: 'numeric',
              })
            : null;
        const endLabel = end
            ? new Date(end).toLocaleDateString('en-IN', {
                  day: 'numeric',
                  month: 'short',
                  year: 'numeric',
              })
            : null;

        if (start && start > now) {
            return { kind: 'upcoming' as const, label: `Starts ${startLabel}` };
        }
        if (end && end < now) {
            return { kind: 'ended' as const, label: `Ended ${endLabel}` };
        }
        if (end) {
            const days = Math.ceil((end - now) / (1000 * 60 * 60 * 24));
            if (days <= 7) {
                return {
                    kind: 'urgent' as const,
                    label:
                        days === 0
                            ? 'Ends today'
                            : `Ends in ${days} day${days === 1 ? '' : 's'}`,
                };
            }
            return { kind: 'ongoing' as const, label: `Ends ${endLabel}` };
        }
        if (start) {
            return { kind: 'ongoing' as const, label: `Started ${startLabel}` };
        }
        return { kind: 'open' as const, label: 'Open now' };
    });

    const stateLabel = $derived(
        campaign.state.charAt(0).toUpperCase() + campaign.state.slice(1),
    );

    const pageUrl = $derived(
        typeof window !== 'undefined' ? window.location.href : '',
    );

    let copied = $state(false);
    async function copyLink() {
        try {
            await navigator.clipboard.writeText(pageUrl);
            copied = true;
            setTimeout(() => (copied = false), 2000);
        } catch {
            /* clipboard blocked */
        }
    }

    const whatsappUrl = $derived(
        `https://wa.me/?text=${encodeURIComponent(`${campaign.title} — ${pageUrl}`)}`,
    );
    const mailtoUrl = $derived(
        `mailto:?subject=${encodeURIComponent(`Support: ${campaign.title}`)}&body=${encodeURIComponent(`${pageUrl}`)}`,
    );

    const faqOpen = $state(DONATION_FAQ.map(() => false));
    function toggleFaq(i: number) {
        faqOpen[i] = !faqOpen[i];
    }

    /**
     * Icon for each "What your offering supports" item — picked by index
     * for visual rhythm. Same 4 icons cycle in order across all three
     * campaigns so the cards feel like a single editorial system.
     */
    const SUPPORT_ICONS = [HandHeart, Wrench, Utensils, Users];

    const donateHref = $derived(`/donate?campaign=${campaign.slug}`);
</script>

<svelte:head>
    <title>{campaign.title} — {appName}</title>
    {#if campaign.short_description}
        <meta name="description" content={campaign.short_description} />
    {/if}
</svelte:head>

<PublicLayout>
    <article>
        <!-- ═══ 1. HERO ═══ -->
        <section class="relative overflow-hidden bg-background">
            <div
                class="pointer-events-none absolute -right-20 top-0 opacity-[0.08]"
                aria-hidden="true"
            >
                <MandalaDecoration size={320} tint="gold" />
            </div>

            <div class="container relative py-12 lg:py-20">
                <div class="grid grid-cols-1 items-center gap-10 lg:grid-cols-12 lg:gap-16">
                    <div class="space-y-5 lg:col-span-7">
                        <div
                            class="flex flex-wrap items-center gap-2 text-[10px] font-semibold uppercase tracking-[0.2em]"
                        >
                            <span
                                class="rounded-sm border border-primary/30 bg-primary/5 px-2 py-0.5 text-primary"
                            >
                                {categoryLabel}
                            </span>
                            {#if campaign.is_featured}
                                <span
                                    class="rounded-sm bg-primary px-2 py-0.5 text-primary-foreground"
                                >
                                    Featured
                                </span>
                            {/if}
                            <span
                                class="rounded-sm border border-border bg-background px-2 py-0.5 text-foreground/70"
                            >
                                {stateLabel}
                            </span>
                        </div>

                        <h1
                            class="font-serif text-3xl font-semibold leading-tight lg:text-5xl"
                        >
                            {campaign.title}
                        </h1>
                        {#if campaign.short_description}
                            <p
                                class="max-w-xl text-base leading-relaxed text-muted-foreground lg:text-lg"
                            >
                                {campaign.short_description}
                            </p>
                        {/if}

                        <div class="flex flex-wrap items-center gap-2 pt-1 text-xs">
                            <span
                                class="inline-flex items-center gap-1.5 rounded-sm border border-border bg-ivory px-2.5 py-1 text-foreground/80"
                            >
                                <Clock class="h-3.5 w-3.5" aria-hidden="true" />
                                {dateState.label}
                            </span>
                        </div>

                        <div class="pt-1">
                            <TrustBadgeRow />
                        </div>
                    </div>

                    <div class="relative lg:col-span-5">
                        {#if $page.props.authUser?.role === 'admin'}
                            <AdminEditOverlay
                                href={`/admin/campaigns/${campaign.id}/edit`}
                                srLabel={`Edit campaign: ${campaign.title}`}
                            />
                        {/if}
                        {#if hasImage}
                            <div
                                class="overflow-hidden rounded-md border border-border/40"
                            >
                                <PublicMediaImage
                                    media={heroImage!}
                                    alt={campaign.title}
                                    class="aspect-[4/5] w-full object-cover"
                                />
                            </div>
                        {:else}
                            <div
                                class="overflow-hidden rounded-md border border-border/40"
                            >
                                <img
                                    src={DEFAULT_HERO_IMAGE}
                                    alt={campaign.title}
                                    class="aspect-[4/5] w-full object-cover"
                                    loading="eager"
                                    decoding="async"
                                />
                            </div>
                        {/if}
                    </div>
                </div>
            </div>
        </section>

        <!-- ═══ 2. HOW YOUR OFFERING BECOMES SEVA — 3-step guide (moved up — right under the title) ═══ -->
        <section class="bg-background py-14 lg:py-20">
            <div class="container">
                <div class="mx-auto max-w-5xl space-y-10">
                    <div class="mx-auto max-w-2xl space-y-4 text-center">
                        <p
                            class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                        >
                            Three steps
                        </p>
                        <h2
                            class="font-serif text-2xl font-semibold leading-tight lg:text-3xl"
                        >
                            How your offering becomes seva
                        </h2>
                        <p
                            class="text-base leading-relaxed text-muted-foreground"
                        >
                            Giving here follows a simple rhythm — choose,
                            dedicate, receive. Each step is handled with care,
                            so the offering stays an offering.
                        </p>
                    </div>

                    <ol
                        class="grid grid-cols-1 gap-5 lg:grid-cols-3"
                        aria-label="The three steps of offering"
                    >
                        {#each OFFERING_FLOW as step, i (i)}
                            <li
                                class="relative flex h-full flex-col gap-4 rounded-md border border-border/40 bg-ivory p-6 lg:p-7"
                            >
                                <div
                                    class="flex items-center justify-between gap-2"
                                >
                                    <span
                                        class="font-serif text-3xl font-semibold text-primary/40 lg:text-4xl"
                                        aria-hidden="true"
                                    >
                                        {step.number}
                                    </span>
                                    <span
                                        class="inline-flex h-1.5 w-12 rounded-full bg-primary/30"
                                        aria-hidden="true"
                                    ></span>
                                </div>
                                <h3
                                    class="font-serif text-lg font-semibold leading-tight lg:text-xl"
                                >
                                    {step.title}
                                </h3>
                                <p
                                    class="text-sm leading-relaxed text-muted-foreground"
                                >
                                    {step.body}
                                </p>
                            </li>
                        {/each}
                    </ol>

                    <div class="text-center">
                        <a
                            href={donateHref}
                            class="inline-flex h-11 items-center justify-center whitespace-nowrap rounded-sm bg-primary px-6 text-xs font-semibold uppercase tracking-[0.2em] text-primary-foreground transition-colors hover:bg-accent"
                        >
                            <Heart
                                class="mr-2 h-4 w-4"
                                aria-hidden="true"
                            />
                            Start your offering
                            <ArrowRight
                                class="ml-2 h-4 w-4"
                                aria-hidden="true"
                            />
                        </a>
                        <p
                            class="mt-3 text-xs text-muted-foreground"
                        >
                            You'll be taken to the donation page for
                            <span class="text-foreground/80"
                                >{campaign.title}</span
                            >.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ═══ 3. ABOUT THIS CAMPAIGN — expanded multi-paragraph, with feature photo ═══ -->
        <section class="bg-background py-14 lg:py-20">
            <div class="container">
                <div class="grid grid-cols-1 items-start gap-10 lg:grid-cols-12 lg:gap-14">
                    <div class="space-y-5 lg:col-span-7">
                        <p
                            class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                        >
                            About this campaign
                        </p>
                        <h2
                            class="font-serif text-2xl font-semibold leading-tight lg:text-3xl"
                        >
                            What your offering sustains
                        </h2>
                        <div
                            class="space-y-4 text-base leading-relaxed text-muted-foreground"
                        >
                            {#each aboutParagraphs as para, i (i)}
                                <p>{para}</p>
                            {/each}
                        </div>
                    </div>

                    <div class="relative lg:col-span-5">
                        <div
                            class="overflow-hidden rounded-md border border-border/40 shadow-sm"
                        >
                            <img
                                src={ABOUT_FEATURE_IMAGE}
                                alt="A glimpse from the temple"
                                class="aspect-[4/5] w-full object-cover"
                                loading="lazy"
                                decoding="async"
                            />
                        </div>
                        <div
                            class="pointer-events-none absolute -bottom-4 -right-4 hidden h-24 w-24 rounded-full border border-primary/15 bg-ivory/80 backdrop-blur sm:block"
                            aria-hidden="true"
                        ></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ═══ 3. WHY IT MATTERS — pull-quote + following paragraph ═══ -->
        <section class="bg-ivory py-16 lg:py-24">
            <div class="container">
                <div class="mx-auto max-w-3xl space-y-6 text-center">
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                    >
                        Why it matters
                    </p>
                    <blockquote
                        class="font-serif text-xl leading-relaxed text-foreground/90 lg:text-2xl"
                    >
                        "{fallback.whyItMatters}"
                    </blockquote>
                    <p
                        class="text-base leading-relaxed text-muted-foreground lg:text-lg"
                    >
                        {fallback.whyItMattersBody}
                    </p>
                </div>
            </div>
        </section>

        <!-- ═══ 4. PROGRESS + INLINE DONATE ═══ -->
        <section class="bg-background py-16 lg:py-24">
            <div class="container">
                <div class="mx-auto max-w-3xl space-y-6">
                    <div class="flex items-end justify-between gap-4">
                        <div class="space-y-2">
                            <p
                                class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                            >
                                Progress
                            </p>
                            <h2
                                class="font-serif text-2xl font-semibold lg:text-3xl"
                            >
                                Where the campaign stands
                            </h2>
                        </div>
                        {#if hasTarget}
                            <div class="text-right">
                                <p
                                    class="text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground"
                                >
                                    Target
                                </p>
                                <p class="font-serif text-xl font-semibold">
                                    <Money
                                        amountMinor={campaign.target_amount_minor!}
                                        currencyCode={campaign.currency_code}
                                    />
                                </p>
                            </div>
                        {:else}
                            <div class="text-right">
                                <p
                                    class="text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground"
                                >
                                    Goal
                                </p>
                                <p
                                    class="font-serif text-base italic text-muted-foreground"
                                >
                                    Open goal
                                </p>
                            </div>
                        {/if}
                    </div>

                    {#if hasProgress}
                        <CampaignProgress
                            {progress}
                            {targetByCurrency}
                            class="space-y-4"
                        />
                    {:else if isOpenGoal}
                        <div
                            class="rounded-md border border-dashed border-primary/30 bg-ivory p-8 text-center"
                        >
                            <Sparkles
                                class="mx-auto mb-3 h-6 w-6 text-primary/60"
                                aria-hidden="true"
                            />
                            <p class="text-sm font-medium text-foreground/80">
                                Every contribution, of any size, sustains this cause.
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {currencyName(campaign.currency_code)} gifts
                                of any amount are received with gratitude.
                            </p>
                        </div>
                    {:else}
                        <div
                            class="rounded-md border border-dashed border-border bg-ivory p-6 text-center"
                        >
                            <p class="text-sm text-muted-foreground">
                                No donations yet for this campaign. Be the
                                first.
                            </p>
                        </div>
                    {/if}

                    <div class="flex justify-end pt-2">
                        <a
                            href={donateHref}
                            class="inline-flex h-10 items-center justify-center whitespace-nowrap rounded-sm bg-primary px-5 text-xs font-semibold uppercase tracking-[0.2em] text-primary-foreground transition-colors hover:bg-accent"
                        >
                            <Heart
                                class="mr-2 h-4 w-4"
                                aria-hidden="true"
                            />
                            Donate now
                            <ArrowRight
                                class="ml-2 h-4 w-4"
                                aria-hidden="true"
                            />
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- ═══ 5. WHAT YOUR OFFERING SUPPORTS — banner image + 4 icon cards ═══ -->
        <section class="bg-ivory py-16 lg:py-24">
            <div class="container">
                <div class="mx-auto max-w-5xl space-y-10">
                    <div
                        class="relative overflow-hidden rounded-md border border-border/40"
                    >
                        <img
                            src={SUPPORTS_HEADER_IMAGE}
                            alt="Where every rupee goes"
                            class="aspect-[21/9] w-full object-cover"
                            loading="lazy"
                            decoding="async"
                        />
                        <div
                            class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/65 via-black/20 to-transparent"
                            aria-hidden="true"
                        ></div>
                        <div
                            class="absolute inset-0 flex items-end p-6 lg:p-10"
                        >
                            <div class="space-y-2">
                                <p
                                    class="text-xs font-semibold uppercase tracking-[0.25em] text-white/85"
                                >
                                    What your offering supports
                                </p>
                                <h3
                                    class="font-serif text-2xl font-semibold leading-tight text-white lg:text-3xl"
                                >
                                    Where every rupee goes
                                </h3>
                            </div>
                        </div>
                    </div>

                    <ul class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {#each fallback.whatItSupports as item, i (i)}
                            {@const Icon = SUPPORT_ICONS[i % SUPPORT_ICONS.length]}
                            <li
                                class="group flex flex-col gap-3 rounded-md border border-border/40 bg-background p-5 transition-all hover:border-primary/40 hover:shadow-sm"
                            >
                                <div
                                    class="flex h-10 w-10 items-center justify-center rounded-sm bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground"
                                >
                                    <Icon
                                        class="h-5 w-5"
                                        aria-hidden="true"
                                    />
                                </div>
                                <p
                                    class="text-sm font-medium text-foreground/90"
                                >
                                    {item}
                                </p>
                            </li>
                        {/each}
                    </ul>

                    <p
                        class="mx-auto max-w-2xl text-center text-sm leading-relaxed text-muted-foreground"
                    >
                        Every offering supports the categories above. The trust
                        publishes how each campaign's funds were spent when
                        the campaign closes — so the giving stays accountable
                        to every devotee who contributes.
                    </p>
                </div>
            </div>
        </section>

        <!-- ═══ 7. CONTRIBUTE CTA — moved up so the guide leads into it ═══ -->
        <section class="container py-16 lg:py-20">
            <div class="mx-auto max-w-3xl space-y-4">
                <div
                    class="flex items-start gap-3 rounded-md border border-primary/20 bg-ivory p-4"
                >
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                    >
                        <CheckCircle2
                            class="h-4 w-4"
                            aria-hidden="true"
                        />
                    </div>
                    <div class="space-y-0.5">
                        <p class="text-sm font-medium">
                            An official receipt is issued for every donation
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Tax-deductibility, including 80G certificates where
                            applicable, is confirmed at the time of each donation
                            in line with applicable law.
                        </p>
                    </div>
                </div>

                <div
                    class="relative overflow-hidden rounded-md border border-primary/20 bg-ivory p-8 lg:p-12"
                >
                    <div
                        class="pointer-events-none absolute -right-16 -top-16 opacity-[0.08]"
                        aria-hidden="true"
                    >
                        <MandalaDecoration size={260} tint="gold" />
                    </div>
                    <div
                        class="relative flex flex-col items-start gap-6 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="space-y-2">
                            <p
                                class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                            >
                                Contribute
                            </p>
                            <h3
                                class="font-serif text-2xl font-semibold lg:text-3xl"
                            >
                                Offer your seva
                            </h3>
                            <p class="text-sm text-muted-foreground">
                                Every contribution, of any size, sustains this
                                cause.
                            </p>
                        </div>
                        <a
                            href={donateHref}
                            class="inline-flex h-11 items-center justify-center whitespace-nowrap rounded-sm bg-primary px-6 text-xs font-semibold uppercase tracking-[0.2em] text-primary-foreground transition-colors hover:bg-accent"
                        >
                            <Heart
                                class="mr-2 h-4 w-4"
                                aria-hidden="true"
                            />
                            Donate
                            <ArrowRight
                                class="ml-2 h-4 w-4"
                                aria-hidden="true"
                            />
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- ═══ 8. FAQ ═══ -->
        <section class="container pb-16 lg:pb-20">
            <div class="mx-auto max-w-3xl space-y-5">
                <p
                    class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                >
                    About giving
                </p>
                <h2 class="font-serif text-2xl font-semibold lg:text-3xl">
                    Frequently asked
                </h2>
                <ul class="divide-y divide-border/60 rounded-md border border-border/40 bg-background">
                    {#each DONATION_FAQ as faq, i (i)}
                        <li>
                            <button
                                type="button"
                                class="flex w-full items-center justify-between gap-4 px-4 py-4 text-left text-sm font-medium transition-colors hover:bg-ivory/50"
                                aria-expanded={faqOpen[i]}
                                aria-controls="faq-panel-{i}"
                                onclick={() => toggleFaq(i)}
                            >
                                <span>{faq.question}</span>
                                <ChevronDown
                                    class={`h-4 w-4 shrink-0 text-muted-foreground transition-transform ${faqOpen[i] ? 'rotate-180' : ''}`}
                                    aria-hidden="true"
                                />
                            </button>
                            {#if faqOpen[i]}
                                <div
                                    id="faq-panel-{i}"
                                    class="px-4 pb-4 text-sm leading-relaxed text-muted-foreground"
                                >
                                    {faq.answer}
                                </div>
                            {/if}
                        </li>
                    {/each}
                </ul>
            </div>
        </section>

        <!-- ═══ 9. SHARE ═══ -->
        <section class="container pb-16 lg:pb-20">
            <div
                class="mx-auto flex max-w-3xl flex-wrap items-center justify-between gap-3 rounded-md border border-border/40 bg-ivory px-4 py-3"
            >
                <span
                    class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground"
                >
                    <Share2 class="h-3.5 w-3.5" aria-hidden="true" />
                    Share this campaign
                </span>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        onclick={copyLink}
                        class="inline-flex items-center gap-1.5 rounded-sm border border-border bg-background px-3 py-1.5 text-xs font-medium transition-colors hover:border-primary/40"
                    >
                        <Copy class="h-3.5 w-3.5" aria-hidden="true" />
                        {copied ? 'Copied' : 'Copy link'}
                    </button>
                    <a
                        href={whatsappUrl}
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-1.5 rounded-sm border border-border bg-background px-3 py-1.5 text-xs font-medium transition-colors hover:border-primary/40"
                    >
                        WhatsApp
                    </a>
                    <a
                        href={mailtoUrl}
                        class="inline-flex items-center gap-1.5 rounded-sm border border-border bg-background px-3 py-1.5 text-xs font-medium transition-colors hover:border-primary/40"
                    >
                        <Mail class="h-3.5 w-3.5" aria-hidden="true" />
                        Email
                    </a>
                </div>
            </div>
        </section>

        <!-- ═══ 10. RELATED CAMPAIGNS ═══ -->
        {#if relatedCampaigns.length > 0}
            <section class="bg-ivory py-20 lg:py-28">
                <div class="container">
                    <div class="mx-auto max-w-5xl space-y-8">
                        <div class="space-y-2">
                            <p
                                class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                            >
                                Other causes
                            </p>
                            <h2
                                class="font-serif text-2xl font-semibold lg:text-3xl"
                            >
                                Related campaigns
                            </h2>
                        </div>
                        <div
                            class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
                        >
                            {#each relatedCampaigns as rc (rc.id)}
                                <CampaignCard campaign={rc} compact />
                            {/each}
                        </div>
                    </div>
                </div>
            </section>
        {/if}
    </article>
</PublicLayout>
