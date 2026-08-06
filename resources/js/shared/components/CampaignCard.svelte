<script lang="ts">
    import Money from './Money.svelte';
    import { currencySymbol } from '$shared/lib/currency';
    import AdminEditOverlay from './AdminEditOverlay.svelte';
    import { ArrowRight, Heart, Users } from 'lucide-svelte';
    import {
        Card,
        CardHeader,
        CardTitle,
        CardDescription,
        CardContent,
    } from '$shared/ui/card';
    import type { CampaignSummaryProps } from '$shared/lib/inertia';

    /**
     * Campaign card used on the index page and the home "featured"
     * section. Renders the campaign title, category, target, raised
     * amount, days-remaining, and a single CTA affordance. Designed
     * to gracefully handle missing data: null target, null end date,
     * null raised amount, null cover image all render coherent states
     * rather than collapsing or showing 0%.
     */

    interface Props {
        campaign: CampaignSummaryProps & {
            raised_amount_minor?: number | null;
            donor_count?: number | null;
        };
        href?: string | null;
        compact?: boolean;
        class?: string;
        /**
         * Phase 4: Admin Kernel — when set, the card surfaces a
         * floating pencil overlay in the top-right corner that links
         * to the admin edit page for this campaign. The pencil is
         * only rendered for authenticated admin users (the overlay
         * component is a no-op for everyone else).
         */
        adminEditHref?: string | null;
    }

    let {
        campaign,
        href = null,
        compact = false,
        class: className = '',
        adminEditHref = null,
    }: Props = $props();

    const titleId = $derived(`campaign-card-${campaign.id}-title`);

    const target = $derived(campaign.target_amount_minor ?? null);
    const raised = $derived(campaign.raised_amount_minor ?? null);
    const donors = $derived(campaign.donor_count ?? null);

    const percent = $derived.by(() => {
        if (target === null || raised === null) return null;
        if (target <= 0) return null;
        return Math.min(100, Math.round((raised / target) * 100));
    });

    const stateLabel = $derived(
        campaign.state.charAt(0).toUpperCase() + campaign.state.slice(1),
    );

    const categoryLabel = $derived(
        campaign.category
            .replace(/_/g, ' ')
            .replace(/\b\w/g, (c) => c.toUpperCase()),
    );

    const daysRemaining = $derived.by(() => {
        if (!campaign.ends_at) return null;
        const end = new Date(campaign.ends_at).getTime();
        if (Number.isNaN(end)) return null;
        const diff = Math.ceil((end - Date.now()) / (1000 * 60 * 60 * 24));
        return diff;
    });

    const endsDisplay = $derived.by(() => {
        if (daysRemaining === null) return null;
        if (daysRemaining < 0) {
            return {
                label: 'Ended',
                tone: 'muted' as const,
            };
        }
        if (daysRemaining === 0) {
            return { label: 'Ends today', tone: 'urgent' as const };
        }
        if (daysRemaining <= 7) {
            return {
                label: `${daysRemaining} day${daysRemaining === 1 ? '' : 's'} left`,
                tone: 'urgent' as const,
            };
        }
        if (daysRemaining <= 30) {
            return {
                label: `${daysRemaining} days left`,
                tone: 'muted' as const,
            };
        }
        return {
            label: new Date(campaign.ends_at!).toLocaleDateString('en-IN', {
                day: 'numeric',
                month: 'short',
                year: 'numeric',
            }),
            tone: 'muted' as const,
        };
    });
</script>

<Card class={`group relative h-full overflow-hidden transition-all hover:border-primary/40 hover:shadow-md ${className}`}>
    {#if adminEditHref}
        <AdminEditOverlay href={adminEditHref} srLabel={`Edit campaign: ${campaign.title}`} />
    {/if}
    <a
        {href}
        aria-labelledby={titleId}
        class="flex h-full flex-col focus:outline-none focus:ring-2 focus:ring-primary/40"
    >
        {#if campaign.cover_image}
            <div class="aspect-[16/9] overflow-hidden bg-ivory">
                <img
                    src={campaign.cover_image.url}
                    alt={campaign.cover_image.alt_text ?? campaign.title}
                    class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.03]"
                    loading="lazy"
                />
            </div>
        {:else if !compact}
            <div
                class="flex aspect-[16/9] items-center justify-center bg-gradient-to-br from-primary/10 via-ivory to-primary/5"
            >
                <div
                    class="rounded-full border border-primary/20 bg-background/60 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.2em] text-primary/70"
                >
                    {categoryLabel}
                </div>
            </div>
        {/if}

        <CardHeader class="space-y-3">
            <div class="flex items-center justify-between gap-2">
                <span
                    class="inline-flex items-center rounded-sm border border-primary/30 bg-primary/5 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-[0.18em] text-primary"
                >
                    {categoryLabel}
                </span>
                {#if campaign.is_featured}
                    <span
                        class="inline-flex items-center rounded-sm bg-primary px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-[0.18em] text-primary-foreground"
                    >
                        Featured
                    </span>
                {/if}
            </div>

            <CardTitle id={titleId} class="line-clamp-2 text-lg lg:text-xl">
                {campaign.title}
            </CardTitle>

            {#if campaign.short_description}
                <CardDescription class="line-clamp-2">
                    {campaign.short_description}
                </CardDescription>
            {/if}
        </CardHeader>

        <CardContent class="mt-auto space-y-3 text-sm">
            <div class="flex items-baseline justify-between gap-2">
                <span class="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">
                    State
                </span>
                <span class="text-xs font-medium text-foreground/80">
                    {stateLabel}
                </span>
            </div>

            <div class="flex items-baseline justify-between gap-2">
                <span class="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">
                    Goal
                </span>
                {#if target !== null}
                    <span class="font-serif text-sm font-semibold">
                        <Money
                            amountMinor={target}
                            currencyCode={campaign.currency_code}
                        />
                    </span>
                {:else}
                    <span class="text-xs italic text-muted-foreground">
                        Open goal
                    </span>
                {/if}
            </div>

            {#if raised !== null}
                <div class="flex items-baseline justify-between gap-2">
                    <span class="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">
                        Raised
                    </span>
                    <span class="text-sm font-medium text-primary">
                        {currencySymbol(campaign.currency_code)}
                        {(raised / 100).toLocaleString('en-IN', {
                            maximumFractionDigits: 0,
                        })}
                    </span>
                </div>
            {/if}

            {#if percent !== null}
                <div
                    class="h-1 w-full overflow-hidden rounded-full bg-secondary"
                    role="progressbar"
                    aria-valuenow={percent}
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-label={`${percent}% of goal`}
                >
                    <div
                        class="h-full bg-primary transition-all"
                        style:width={`${percent}%`}
                    ></div>
                </div>
            {/if}

            {#if endsDisplay}
                <div
                    class="flex items-baseline justify-between gap-2 text-xs"
                    class:text-primary={endsDisplay.tone === 'urgent'}
                    class:text-muted-foreground={endsDisplay.tone === 'muted'}
                >
                    <span class="inline-flex items-center gap-1">
                        {#if endsDisplay.tone === 'urgent'}<span class="inline-block h-1.5 w-1.5 rounded-full bg-primary"></span>{/if}
                        {endsDisplay.label}
                    </span>
                    {#if donors !== null && donors > 0}
                        <span class="inline-flex items-center gap-1">
                            <Users class="h-3 w-3" aria-hidden="true" />
                            {donors}
                        </span>
                    {/if}
                </div>
            {:else if donors !== null && donors > 0}
                <div class="flex items-center gap-1 text-xs text-muted-foreground">
                    <Users class="h-3 w-3" aria-hidden="true" />
                    {donors} {donors === 1 ? 'devotee' : 'devotees'}
                </div>
            {/if}

            <div
                class="flex items-center justify-between gap-1 pt-1 text-sm font-medium text-primary transition-transform group-hover:translate-x-0.5"
            >
                {#if href}
                    <span class="inline-flex items-center gap-1">
                        <Heart class="h-3.5 w-3.5" aria-hidden="true" />
                        Support this cause
                    </span>
                    <ArrowRight class="h-3.5 w-3.5" aria-hidden="true" />
                {/if}
            </div>
        </CardContent>
    </a>
</Card>
