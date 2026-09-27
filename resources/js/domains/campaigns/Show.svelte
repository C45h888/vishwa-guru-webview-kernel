<script lang="ts">
    /**
     * Show.svelte — `/campaigns/{slug}`
     *
     * Pass M2 layout (per docs/ui-passes/M2-campaigns-overhaul.md):
     *
     *   1. Hero          — full-viewport cover (CampaignHero.svelte)
     *   2. About         — eyebrow + headline + prose; "Why it matters"
     *                      folded in as the lead paragraph
     *   3. Campaign      — progress bar + target + status
     *   4. Donate CTA    — single prominent CTA + receipt trust pill
     *   5. Seva pill     — 3 connected steps (SevaPill.svelte)
     *   6. Supports row  — 4-item icon row (SupportsRow.svelte)
     *
     * Quiet footer: related campaigns, FAQ, share.
     *
     * Doctrine preserved from the previous version:
     *   - Backend contracts unchanged. Same props, same data shape.
     *   - All AdminEditOverlay integration points preserved (only the
     *     hero carries an edit pencil now; the admin can still reach
     *     the campaign form via the hero).
     *   - Mobile menu kernel (M1) continues to work — this surface
     *     inherits PublicLayout.
     *   - Aesthetic: same 6 design tokens, same type roles, same
     *     eyebrow rhythm.
     */
    import { page } from '@inertiajs/svelte';
    import Money from '$shared/components/Money.svelte';
    import CampaignProgress from '$shared/components/CampaignProgress.svelte';
    import CampaignCard from '$shared/components/CampaignCard.svelte';
    import CampaignHero from '$shared/components/CampaignHero.svelte';
    import SevaPill from '$shared/components/SevaPill.svelte';
    import SupportsRow from '$shared/components/SupportsRow.svelte';
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import {
        ArrowRight,
        CheckCircle2,
        ChevronDown,
        Copy,
        Heart,
        Mail,
        Share2,
        Sparkles,
    } from 'lucide-svelte';
    import {
        campaignFallbackFor,
        OFFERING_FLOW,
        DONATION_FAQ,
    } from '$shared/lib/campaign-fallbacks';
    import { currencyName } from '$shared/lib/currency';
    import {
        POOLED_FUND_DESCRIPTION,
        POOLED_FUND_SHORT_DESCRIPTION,
        POOLED_FUND_TITLE,
        isPooledFund,
    } from '$shared/lib/pooled-fund';
    import type {
        CampaignDetailProps,
        CampaignProgressProps,
        CampaignSummaryProps,
        AppPageProps,
    } from '$shared/lib/inertia';

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

    const fallback = $derived(campaignFallbackFor(campaign.category));
    const pooledFund = $derived(isPooledFund(campaign));
    const displayTitle = $derived(pooledFund ? POOLED_FUND_TITLE : campaign.title);
    const displayShortDescription = $derived(
        pooledFund ? POOLED_FUND_SHORT_DESCRIPTION : campaign.short_description,
    );

    // ── Labels & meta ───────────────────────────────────────────────

    const CATEGORY_LABELS: Record<string, string> = {
        general: 'Pooled Fund',
        school_annadanam: 'Pooled Fund',
        land_acquisition: 'Campus · In pooled fund',
        construction: 'Campus · Planned Build',
        gaushala_build: 'Campus · Planned Build',
        operations: 'Campus · Planned Cow Care',
        cow_care_future: 'Campus · Planned Cow Care',
        maintenance: 'General Fund',
        diwali: 'Seasonal Appeal',
    };

    const categoryLabel = $derived(
        CATEGORY_LABELS[campaign.category] ??
            campaign.category
                .replace(/_/g, ' ')
                .replace(/\b\w/g, (c) => c.toUpperCase()),
    );

    const plannedCategories = [
        'construction',
        'gaushala_build',
        'operations',
        'cow_care_future',
    ];

    const causeEyebrow = $derived.by(() => {
        switch (campaign.category) {
            case 'general':
            case 'school_annadanam':
            case 'land_acquisition':
                if (pooledFund) return 'One pooled fund · schools and campus';
                return campaign.state === 'active'
                    ? 'Included in the pooled fund'
                    : 'Pooled fund campaign closed';
            case 'construction':
            case 'gaushala_build':
            case 'operations':
            case 'cow_care_future':
                return 'Planned stage · supported through the pooled fund';
            default:
                return 'Campaign · open now';
        }
    });

    const stateLabel = $derived(
        plannedCategories.includes(campaign.category)
            ? 'Supported through pooled fund'
            : campaign.state === 'active'
              ? 'Accepting donations'
              : 'Campaign closed',
    );

    // ── About copy ──────────────────────────────────────────────────

    const aboutSource = $derived(
            pooledFund
                ? POOLED_FUND_DESCRIPTION
                : campaign.description && campaign.description.trim() !== ''
            ? campaign.description
            : fallback.about,
    );

    const aboutParagraphs = $derived(
        aboutSource
            .split(/(?<=[.!?])\s+/)
            .map((p) => p.trim())
            .filter((p) => p.length > 0),
    );

    /**
     * "Why it matters" is now the lead paragraph of the About section
     * (Pass M2). It uses the fallback's pull-quote when one is
     * available; otherwise the first campaign-supplied paragraph is
     * lifted into the lead position so the section still opens with
     * the cause's voice.
     */
    const whyItMattersLead = $derived.by(() => {
        const quote = fallback.whyItMatters?.trim();
        if (quote && quote.length > 0) return quote;
        return aboutParagraphs[0] ?? '';
    });

    const whyItMattersBody = $derived(
        fallback.whyItMattersBody?.trim() ?? '',
    );

    // ── Progress ────────────────────────────────────────────────────

    const targetByCurrency = $derived({
        [campaign.currency_code]: campaign.target_amount_minor,
    } as Record<string, number | null>);

    const hasProgress = $derived(
        progress.length > 0 && progress.some((p) => p.raised_amount_minor > 0),
    );

    const hasTarget = $derived(campaign.target_amount_minor !== null);
    const isOpenGoal = $derived(!hasTarget);

    // ── Date state ──────────────────────────────────────────────────

    const dateLabel = $derived.by(() => {
        const now = Date.now();
        const start = campaign.starts_at
            ? new Date(campaign.starts_at).getTime()
            : null;
        const end = campaign.ends_at
            ? new Date(campaign.ends_at).getTime()
            : null;

        const fmt = (ms: number) =>
            new Date(ms).toLocaleDateString('en-IN', {
                day: 'numeric',
                month: 'short',
                year: 'numeric',
            });

        if (start && start > now) return `Starts ${fmt(start)}`;
        if (end && end < now) return `Ended ${fmt(end)}`;
        if (end) {
            const days = Math.ceil((end - now) / (1000 * 60 * 60 * 24));
            if (days <= 7) {
                return days === 0
                    ? 'Ends today'
                    : `Ends in ${days} day${days === 1 ? '' : 's'}`;
            }
            return `Ends ${fmt(end)}`;
        }
        if (start) return `Started ${fmt(start)}`;
        return 'Open now';
    });

    // ── Share ───────────────────────────────────────────────────────

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
        `https://wa.me/?text=${encodeURIComponent(`${displayTitle} — ${pageUrl}`)}`,
    );
    const mailtoUrl = $derived(
        `mailto:?subject=${encodeURIComponent(`Support: ${displayTitle}`)}&body=${encodeURIComponent(`${pageUrl}`)}`,
    );

    // ── FAQ ─────────────────────────────────────────────────────────

    const faqOpen = $state(DONATION_FAQ.map(() => false));
    function toggleFaq(i: number) {
        faqOpen[i] = !faqOpen[i];
    }

    // ── Supports row items ──────────────────────────────────────────

    /**
     * The "What every offering supports" items for the icon row. We
     * pair each label with a short note for the row's secondary line.
     * Pulled from the campaign fallback so the wording stays consistent
     * with the rest of the page.
     */
    const supportsItems = $derived(
        fallback.whatItSupports.map((label) => ({
            label,
            note: '',
        })),
    );

    // ── Donate href ────────────────────────────────────────────────

    const acceptsDonations = $derived(
        campaign.state === 'active' && !plannedCategories.includes(campaign.category),
    );
    const donateLabel = $derived(
        pooledFund || plannedCategories.includes(campaign.category)
            ? 'Give through the pooled fund'
            : 'Donate to this cause',
    );
    const donateHref = $derived(
        pooledFund || plannedCategories.includes(campaign.category)
            ? '/donate'
            : acceptsDonations
              ? `/donate?campaign=${campaign.slug}`
              : null,
    );
    const donateUnavailableLabel = $derived(
        plannedCategories.includes(campaign.category)
            ? 'This stage is included in the pooled fund'
            : 'This campaign is closed',
    );
</script>

<svelte:head>
    <title>{displayTitle} — {appName}</title>
    {#if displayShortDescription}
        <meta name="description" content={displayShortDescription} />
    {/if}
</svelte:head>

<PublicLayout>
    <article>
        <!-- ═══ 1. HERO ═══ -->
        <CampaignHero
            {campaign}
            donateHref={donateHref ?? undefined}
            {donateLabel}
            {donateUnavailableLabel}
            {displayTitle}
            {displayShortDescription}
            {categoryLabel}
            {causeEyebrow}
            {stateLabel}
            {dateLabel}
            isFeatured={campaign.is_featured}
        />

        <!-- ═══ 2. ABOUT (with "Why it matters" folded in as the lead) ═══ -->
        <section class="bg-background py-20 lg:py-28">
            <div class="container">
                <div class="mx-auto max-w-3xl space-y-8 text-center">
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.3em] text-primary"
                    >
                        About this campaign
                    </p>
                    <h2
                        class="font-serif text-3xl font-semibold leading-tight lg:text-4xl"
                    >
                        What your offering sustains
                    </h2>

                    {#if whyItMattersLead}
                        <p
                            class="font-serif text-xl leading-relaxed text-foreground/90 lg:text-2xl"
                        >
                            "{whyItMattersLead}"
                        </p>
                    {/if}

                    {#if whyItMattersBody}
                        <p
                            class="text-base leading-relaxed text-muted-foreground lg:text-lg"
                        >
                            {whyItMattersBody}
                        </p>
                    {/if}

                    <div
                        class="space-y-4 pt-2 text-left text-base leading-relaxed text-muted-foreground"
                    >
                        {#each aboutParagraphs as para, i (i)}
                            <p>{para}</p>
                        {/each}
                    </div>

                    {#if donateHref}
                        <div class="pt-2">
                            <a
                                href={donateHref}
                                class="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline"
                            >
                                {donateLabel}
                                <ArrowRight class="h-4 w-4" aria-hidden="true" />
                            </a>
                        </div>
                    {:else}
                        <p class="pt-2 text-sm font-medium text-muted-foreground">
                            {donateUnavailableLabel}.
                        </p>
                    {/if}
                </div>
            </div>
        </section>

        <!-- ═══ 3. CAMPAIGN — progress + target + status ═══ -->
        <section class="bg-ivory py-16 lg:py-20">
            <div class="container">
                <div class="mx-auto max-w-3xl space-y-6">
                    <div class="flex items-end justify-between gap-4">
                        <div class="space-y-2">
                            <p
                                class="text-xs font-semibold uppercase tracking-[0.3em] text-primary"
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
                            class="rounded-md border border-dashed border-primary/30 bg-background p-8 text-center"
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
                            class="rounded-md border border-dashed border-border bg-background p-6 text-center"
                        >
                            <p class="text-sm text-muted-foreground">
                                No donations yet for this campaign. Be the
                                first.
                            </p>
                        </div>
                    {/if}
                </div>
            </div>
        </section>

        <!-- ═══ 4. DONATE CTA — single, prominent, with the receipt trust pill ═══ -->
        <section class="container py-20 lg:py-24">
            <div class="mx-auto max-w-3xl space-y-5">
                <div
                    class="flex items-start gap-3 rounded-md border border-primary/20 bg-rice p-4"
                >
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                    >
                        <CheckCircle2 class="h-4 w-4" aria-hidden="true" />
                    </div>
                    <div class="space-y-0.5">
                        <p class="text-sm font-medium">
                            An official receipt is issued for every donation
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Tax-deductibility, including 80G certificates where
                            applicable, is confirmed at the time of each
                            donation in line with applicable law.
                        </p>
                    </div>
                </div>

                <div
                    class="relative overflow-hidden rounded-md border border-primary/30 bg-ivory p-8 lg:p-12"
                >
                    <div
                        class="pointer-events-none absolute -right-16 -top-16 opacity-[0.10]"
                        aria-hidden="true"
                    >
                        <svg
                            viewBox="0 0 200 200"
                            class="h-56 w-56 text-primary"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="0.5"
                        >
                            <circle cx="100" cy="100" r="95" />
                            <circle cx="100" cy="100" r="75" />
                            <circle cx="100" cy="100" r="55" />
                        </svg>
                    </div>
                    <div
                        class="relative flex flex-col items-start gap-6 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="space-y-2">
                            <p
                                class="text-xs font-semibold uppercase tracking-[0.3em] text-primary"
                            >
                                Offer your seva
                            </p>
                            <h3
                                class="font-serif text-2xl font-semibold leading-tight lg:text-3xl"
                            >
                                {pooledFund || plannedCategories.includes(campaign.category)
                                    ? 'Contribute through the pooled fund'
                                    : `Contribute to ${campaign.title}`}
                            </h3>
                            <p class="text-sm text-muted-foreground">
                                Every contribution, of any size, sustains
                                {categoryLabel} —
                                {causeEyebrow.toLowerCase()}.
                            </p>
                        </div>
                        {#if donateHref}
                            <a
                                href={donateHref}
                                class="inline-flex h-12 items-center justify-center whitespace-nowrap rounded-sm bg-primary px-7 text-xs font-semibold uppercase tracking-[0.2em] text-primary-foreground transition-colors hover:bg-accent"
                            >
                                <Heart class="mr-2 h-4 w-4" aria-hidden="true" />
                                Donate
                                <ArrowRight
                                    class="ml-2 h-4 w-4"
                                    aria-hidden="true"
                                />
                            </a>
                        {:else}
                            <span class="text-sm font-medium text-muted-foreground">
                                {donateUnavailableLabel}.
                            </span>
                        {/if}
                    </div>
                </div>
            </div>
        </section>

        <!-- ═══ 5. SEVA PILL ═══ -->
        <SevaPill steps={OFFERING_FLOW} />

        <!-- ═══ 6. SUPPORTS ROW ═══ -->
        <SupportsRow items={supportsItems} />

        <!-- ═══ FOOTER: FAQ + SHARE + RELATED ═══ -->
        <section class="container py-16 lg:py-20">
            <div class="mx-auto max-w-3xl space-y-12">
                <!-- FAQ -->
                <div class="space-y-5">
                    <div>
                        <p
                            class="text-xs font-semibold uppercase tracking-[0.3em] text-primary"
                        >
                            About giving
                        </p>
                        <h2 class="mt-2 font-serif text-2xl font-semibold lg:text-3xl">
                            Frequently asked
                        </h2>
                    </div>
                    <ul
                        class="divide-y divide-border/60 rounded-md border border-border/40 bg-background"
                    >
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

                <!-- Share -->
                <div
                    class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-border/40 bg-ivory px-4 py-3"
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
            </div>
        </section>

        <!-- Related campaigns -->
        {#if relatedCampaigns.length > 0}
            <section class="bg-ivory py-20 lg:py-28">
                <div class="container">
                    <div class="mx-auto max-w-5xl space-y-8">
                        <div class="space-y-2">
                            <p
                                class="text-xs font-semibold uppercase tracking-[0.3em] text-primary"
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
