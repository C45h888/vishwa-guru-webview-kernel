<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '$shared/ui/card';
    import { Button } from '$shared/ui/button';
    import { Input } from '$shared/ui/input';
    import { Label } from '$shared/ui/label';
    import { Alert } from '$shared/ui/alert';
    import type {
        CampaignSummaryProps,
        AppPageProps,
    } from '$shared/lib/inertia';

    let {
        campaigns,
        defaultCurrency,
        preselectSlug,
        appName,
    }: AppPageProps<{
        campaigns: CampaignSummaryProps[];
        defaultCurrency: string;
        preselectSlug: string | null;
    }> = $props();

    let selectedCampaign = $state<string>(preselectSlug ?? campaigns[0]?.slug ?? '');
    let amountRupees = $state<string>('1000');
    let donorName = $state<string>('');
    let donorEmail = $state<string>('');
    let donorPhone = $state<string>('');
    let donorMessage = $state<string>('');
    let isAnonymous = $state(false);

    let submitting = $state(false);
    let errorMessage = $state<string | null>(null);

    const selectedCampaignData = $derived(
        campaigns.find((c) => c.slug === String(selectedCampaign)) ?? null
    );

    // Convert rupee input → minor units (Razorpay expects integer paise).
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

        const idempotencyKey = (typeof crypto !== 'undefined' && 'randomUUID' in crypto)
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
            const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
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
                    data.message
                    ?? data.error
                    ?? `Submission failed (HTTP ${response.status}).`;
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

            // Navigate to Success page; it loads Razorpay checkout.js
            // with the returned key_id and opens the modal client-side.
            router.visit(`/donate/success?gateway_order_id=${encodeURIComponent(orderId)}`);
        } catch (err) {
            errorMessage = err instanceof Error ? err.message : 'Network error.';
            submitting = false;
        }
    }
</script>

<svelte:head>
    <title>Donate — {appName}</title>
    <meta name="description" content="Support a campaign at {appName} via Razorpay." />
</svelte:head>

<PublicLayout>
    <div class="mx-auto max-w-2xl space-y-6">
        <header class="space-y-2">
            <h1 class="text-3xl font-semibold">Donate</h1>
            <p class="text-sm text-muted-foreground">
                Your contribution goes directly to the chosen campaign via Razorpay test gateway.
            </p>
        </header>

        {#if campaigns.length === 0}
            <p class="text-sm text-muted-foreground">
                No active campaigns accepting donations right now.
            </p>
        {:else}
            <form onsubmit={submit} class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Choose a campaign</CardTitle>
                        <CardDescription>Pick where your donation should go.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <Label for="campaign">Campaign</Label>
                        <select
                            id="campaign"
                            bind:value={selectedCampaign}
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            {#each campaigns as campaign (campaign.id)}
                                <option value={campaign.slug}>{campaign.title}</option>
                            {/each}
                        </select>
                        {#if selectedCampaignData}
                            <p class="text-xs text-muted-foreground">
                                Currency: {selectedCampaignData.currency_code}
                            </p>
                        {/if}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Amount</CardTitle>
                        <CardDescription>
                            Charged in {defaultCurrency} via Razorpay test mode.
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
                            Optional. Below ₹2,000 the donation may remain anonymous.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <label class="flex items-center gap-2 text-sm">
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
                            <Label for="message">Message (optional)</Label>
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
                    <Alert variant="destructive">
                        {errorMessage}
                    </Alert>
                {/if}

                <div class="flex items-center justify-between">
                    <p class="text-xs text-muted-foreground">
                        Powered by Razorpay · Test mode
                    </p>
                    <Button type="submit" disabled={submitting}>
                        {submitting ? 'Submitting…' : 'Continue to payment'}
                    </Button>
                </div>
            </form>
        {/if}
    </div>
</PublicLayout>
