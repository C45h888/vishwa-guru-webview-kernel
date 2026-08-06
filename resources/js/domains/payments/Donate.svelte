<script lang="ts">
    import { router, page } from '@inertiajs/svelte';
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import MandalaDecoration from '$shared/components/MandalaDecoration.svelte';
    import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '$shared/ui/card';
    import { Button } from '$shared/ui/button';
    import { Input } from '$shared/ui/input';
    import { Label } from '$shared/ui/label';
    import { Alert } from '$shared/ui/alert';
    import { ArrowLeft, Heart, Shield } from 'lucide-svelte';
    import type {
        CampaignSummaryProps,
        AppPageProps,
    } from '$shared/lib/inertia';

    // Razorpay environment: shared via HandleInertiaRequests. When 'test',
    // the donate flow surfaces a "Test mode" badge so anyone in the
    // sandbox knows not to use real card details. Production strips it.
    const isTestMode = $derived($page.props.razorpayMode === 'test');

    let {
        campaigns,
        defaultCurrency,
        preselectSlug,
        preselectAmountRupees,
        preselectRecurring,
        preselectAnonymous,
        appName,
    }: AppPageProps<{
        campaigns: CampaignSummaryProps[];
        defaultCurrency: string;
        preselectSlug: string | null;
        preselectAmountRupees: string | null;
        preselectRecurring: string | null;
        preselectAnonymous: boolean;
    }> = $props();

    let selectedCampaign = $state<string>(
        preselectSlug ?? campaigns[0]?.slug ?? '',
    );
    let amountRupees = $state<string>(preselectAmountRupees ?? '1000');
    let isAnonymous = $state<boolean>(preselectAnonymous);
    let isRecurring = $state<string>(preselectRecurring ?? '');
    let donorName = $state<string>('');
    let donorEmail = $state<string>('');
    let donorPhone = $state<string>('');
    let donorMessage = $state<string>('');

    let submitting = $state(false);
    let errorMessage = $state<string | null>(null);

    const selectedCampaignData = $derived(
        campaigns.find((c) => c.slug === String(selectedCampaign)) ?? null,
    );

    function rupeesToMinor(rupees: string): number {
        const parsed = parseFloat(rupees);
        if (Number.isNaN(parsed) || parsed <= 0) return 0;
        return Math.round(parsed * 100);
    }

    async function submit(event: SubmitEvent): Promise<void> {
        event.preventDefault();

        if (!selectedCampaign) {
            errorMessage = 'Please select a campaign.';
            return;
        }

        const amountMinor = rupeesToMinor(amountRupees);
        if (amountMinor < 100) {
            errorMessage = 'Minimum donation is ₹1 (100 paise).';
            return;
        }

        submitting = true;
        errorMessage = null;

        const idempotencyKey =
            typeof crypto !== 'undefined' && 'randomUUID' in crypto
                ? crypto.randomUUID()
                : `idemp_${Date.now()}_${Math.random().toString(36).slice(2)}`;

        const payload = {
            amount_minor: amountMinor,
            currency: defaultCurrency,
            campaign_id: selectedCampaignData?.id ?? selectedCampaign,
            donor: {
                name: isAnonymous ? null : donorName || null,
                email: isAnonymous ? null : donorEmail || null,
                phone: donorPhone || null,
            },
            donation_message: donorMessage || null,
            idempotency_key: idempotencyKey,
        };

        try {
            const csrfToken =
                (
                    document.querySelector(
                        'meta[name="csrf-token"]',
                    ) as HTMLMetaElement | null
                )?.content ?? '';
            const response = await fetch('/api/v1/razorpay/checkout', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
                credentials: 'same-origin',
            });

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                errorMessage =
                    data.message ??
                    data.error ??
                    `Submission failed (HTTP ${response.status}).`;
                submitting = false;
                return;
            }

            const data = await response.json();
            const orderId = data.order_id;
            if (!orderId) {
                errorMessage = 'Gateway did not return an order id.';
                submitting = false;
                return;
            }

            router.visit(
                `/donate/success?gateway_order_id=${encodeURIComponent(orderId)}`,
            );
        } catch (err) {
            errorMessage = err instanceof Error ? err.message : 'Network error.';
            submitting = false;
        }
    }
</script>

<svelte:head>
    <title>Donate — {appName}</title>
    <meta
        name="description"
        content="Support a campaign at {appName} via Razorpay."
    />
</svelte:head>

<PublicLayout>
    <section class="relative overflow-hidden bg-background">
        <div
            class="pointer-events-none absolute -right-20 top-0 opacity-[0.08]"
            aria-hidden="true"
        >
            <MandalaDecoration size={320} tint="gold" />
        </div>

        <div class="container relative py-12 lg:py-20">
            <a
                href="/"
                class="mb-6 inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-primary"
            >
                <ArrowLeft class="h-4 w-4" aria-hidden="true" />
                Back to home
            </a>

            <div class="grid grid-cols-1 items-start gap-10 lg:grid-cols-12 lg:gap-16">
                <header class="space-y-4 lg:col-span-5">
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                    >
                        Offer your seva
                    </p>
                    <h1
                        class="font-serif text-3xl font-semibold leading-tight lg:text-5xl"
                    >
                        Donate
                    </h1>
                    <p
                        class="max-w-md text-base leading-relaxed text-muted-foreground lg:text-lg"
                    >
                        Your contribution supports the current capital
                        project — acquiring the land on which the Gaushala
                        and the Shiva temple will stand.
                    </p>
                    <div
                        class="flex items-center gap-2 pt-2 text-xs text-muted-foreground"
                    >
                        <Shield
                            class="h-4 w-4 text-primary/70"
                            aria-hidden="true"
                        />
                        <span>
                            Secure payment via Razorpay{#if isTestMode}
                                · <span class="font-semibold text-primary">Test mode</span>{/if}
                        </span>
                    </div>
                </header>

                <div class="lg:col-span-7">
                    {#if campaigns.length === 0}
                        <div
                            class="rounded-md border border-dashed border-border bg-ivory/60 p-8 text-center"
                        >
                            <p class="text-sm text-muted-foreground">
                                No active campaigns accepting donations right
                                now.
                            </p>
                        </div>
                    {:else}
                        <form onsubmit={submit} class="space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Choose a campaign</CardTitle>
                                    <CardDescription>
                                        Pick where your donation should go.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent class="space-y-3">
                                    <Label for="campaign">Campaign</Label>
                                    <select
                                        id="campaign"
                                        bind:value={selectedCampaign}
                                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    >
                                        {#each campaigns as campaign (campaign.id)}
                                            <option value={campaign.slug}
                                                >{campaign.title}</option
                                            >
                                        {/each}
                                    </select>
                                    {#if selectedCampaignData}
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            Currency: {selectedCampaignData.currency_code}
                                        </p>
                                    {/if}
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Amount</CardTitle>
                                    <CardDescription>
                                        Charged in {defaultCurrency} via Razorpay{#if isTestMode}
                                            (sandbox){/if}.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent class="space-y-3">
                                    <Label for="amount">Amount (₹)</Label>
                                    <Input
                                        id="amount"
                                        type="number"
                                        min="1"
                                        step="1"
                                        bind:value={amountRupees}
                                        placeholder="1000"
                                    />
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Donor information</CardTitle>
                                    <CardDescription>
                                        Optional. Below ₹2,000 the donation
                                        may remain anonymous.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent class="space-y-3">
                                    <label
                                        class="flex items-center gap-2 text-sm"
                                    >
                                        <input
                                            type="checkbox"
                                            bind:checked={isAnonymous}
                                            class="h-4 w-4 rounded border-input text-primary"
                                        />
                                        Make this donation anonymous
                                    </label>

                                    <div class="space-y-2">
                                        <Label for="name">Name</Label>
                                        <Input
                                            id="name"
                                            type="text"
                                            maxlength={120}
                                            bind:value={donorName}
                                            placeholder="Your full name"
                                            disabled={isAnonymous}
                                        />
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="email">Email</Label>
                                        <Input
                                            id="email"
                                            type="email"
                                            maxlength={255}
                                            bind:value={donorEmail}
                                            placeholder="you@example.com"
                                            disabled={isAnonymous}
                                        />
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="phone">Phone</Label>
                                        <Input
                                            id="phone"
                                            type="tel"
                                            maxlength={20}
                                            bind:value={donorPhone}
                                            placeholder="+91 98765 43210"
                                        />
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="message"
                                            >Message (optional)</Label
                                        >
                                        <Input
                                            id="message"
                                            type="text"
                                            maxlength={500}
                                            bind:value={donorMessage}
                                            placeholder="A short dedication or note"
                                        />
                                    </div>
                                </CardContent>
                            </Card>

                            {#if errorMessage}
                                <Alert variant="destructive"
                                    >{errorMessage}</Alert
                                >
                            {/if}

                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <p class="text-xs text-muted-foreground">
                                    Powered by Razorpay{#if isTestMode}
                                        · <span class="font-semibold">Test mode</span>{/if}
                                </p>
                                <Button type="submit" disabled={submitting}>
                                    <Heart
                                        class="mr-1.5 h-4 w-4"
                                        aria-hidden="true"
                                    />
                                    {submitting
                                        ? 'Submitting…'
                                        : 'Continue to payment'}
                                </Button>
                            </div>
                        </form>
                    {/if}
                </div>
            </div>
        </div>
    </section>
</PublicLayout>
