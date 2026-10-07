<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import MandalaDecoration from '$shared/components/MandalaDecoration.svelte';
    import { Card, CardContent, CardHeader, CardTitle } from '$shared/ui/card';
    import { Button } from '$shared/ui/button';
    import { Badge } from '$shared/ui/badge';
    import { Alert } from '$shared/ui/alert';
    import Money from '$shared/components/Money.svelte';
    import { CheckCircle2, XCircle, Clock, AlertCircle, Mail } from 'lucide-svelte';
    import type {
        PaymentStatusProps,
        AppPageProps,
    } from '$shared/lib/inertia';
    import { openRazorpayCheckout } from '$shared/lib/razorpay';
    import type { RazorpaySuccessResponse } from '$shared/lib/razorpay';
    import SeoHead from '$shared/components/SeoHead.svelte';
    import { trackPurchaseOnce } from '$shared/lib/analytics';

    let {
        payment,
        appName,
        appUrl,
    }: AppPageProps<{
        payment: PaymentStatusProps;
    }> = $props();

    let openingCheckout = $state(false);
    let pollHandle: ReturnType<typeof setInterval> | null = null;
    let latestStatus = $state<PaymentStatusProps>(payment);
    let pollAttempts = $state(0);

    const POLL_INTERVAL_MS = 2000;
    // Wave 1 M7 fix (2026-08-06): extend the polling window from 60s to
    // 5 minutes. Razorpay webhook delivery for non-instant methods
    // (UPI, NetBanking, EMI, RECURRING) regularly takes 2-5 min. The
    // previous 60s window triggered a misleading "Status check timed
    // out" alert while the payment was still being processed.
    const MAX_POLL_ATTEMPTS = 150; // 150 × 2s = 300s = 5 minutes
    const POLL_TIMEOUT_TOTAL_MS = 60_000; // 60s — still surface the "delayed" alert at 1 minute so donor knows we're still listening

    type PollState =
        | 'captured'
        | 'pending'
        | 'failed'
        | 'timeout'
        | 'not_found';

    const pollState = $derived<PollState>(derivePollState());

    // Conversion tracking: only on authoritative captured/settled state,
    // once per order (sessionStorage dedupe inside trackPurchaseOnce).
    $effect(() => {
        const s = latestStatus.status;
        if (s === 'captured' || s === 'settled') {
            trackPurchaseOnce({
                orderId: latestStatus.gateway_order_id,
                amountMinor: latestStatus.amount_minor,
                currency: latestStatus.currency_code,
            });
        }
    });

    function derivePollState(): PollState {
        const s = latestStatus.status;
        if (
            s === 'captured' ||
            s === 'settled' ||
            s === 'settling' ||
            s === 'refunded' ||
            s === 'partially_refunded'
        ) {
            return 'captured';
        }
        if (
            s === 'failed' ||
            s === 'disputed' ||
            s === 'cancelled' ||
            s === 'expired'
        ) {
            return 'failed';
        }
        if (
            pollAttempts >= MAX_POLL_ATTEMPTS &&
            (s === 'initialized' ||
                s === 'pending' ||
                s === 'authorized' ||
                s === null)
        ) {
            return 'timeout';
        }
        if (s === null && !latestStatus.gateway_order_id) {
            return 'not_found';
        }
        return 'pending';
    }

    const isCaptured = $derived(pollState === 'captured');
    const isFailed = $derived(pollState === 'failed');
    const isPending = $derived(pollState === 'pending');
    const isTimedOut = $derived(pollState === 'timeout');
    const isNotFound = $derived(pollState === 'not_found');
    const isOpenable = $derived(isPending && Boolean(latestStatus.public_key_id));

    function teardownPoll(): void {
        if (pollHandle !== null) {
            clearInterval(pollHandle);
            pollHandle = null;
        }
    }

    function currentCsrfToken(): string {
        return (
            (
                document.querySelector(
                    'meta[name="csrf-token"]',
                ) as HTMLMetaElement | null
            )?.content ?? ''
        );
    }

    /**
     * Confirm a Razorpay capture server-side, then re-poll local status.
     * Non-fatal: if the verify call fails, the next full page load
     * reconciles against the gateway anyway.
     */
    async function confirmCapture(
        response?: RazorpaySuccessResponse,
    ): Promise<void> {
        if (
            response?.razorpay_order_id &&
            response.razorpay_payment_id &&
            response.razorpay_signature
        ) {
            try {
                await fetch('/api/v1/razorpay/verify', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': currentCsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        razorpay_order_id: response.razorpay_order_id,
                        razorpay_payment_id: response.razorpay_payment_id,
                        razorpay_signature: response.razorpay_signature,
                    }),
                });
            } catch {
                /* non-fatal — the backend reconciles on the next load */
            }
        }

        pollAttempts = 0;
        pollHandle = setInterval(pollOnce, POLL_INTERVAL_MS);
        void pollOnce();
    }

    async function pollOnce(): Promise<void> {
        pollAttempts += 1;

        if (pollAttempts > MAX_POLL_ATTEMPTS) {
            teardownPoll();
            return;
        }

        const orderId = latestStatus.gateway_order_id;
        if (!orderId) {
            teardownPoll();
            return;
        }

        try {
            const response = await fetch(
                `/donate/success?gateway_order_id=${encodeURIComponent(orderId)}`,
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Inertia': 'true',
                        'X-Inertia-Partial-Component': 'payments/Success',
                    },
                },
            );
            if (!response.ok) return;
            const data = await response.json();
            const next: PaymentStatusProps | undefined =
                data?.props?.payment;
            if (!next) return;

            const prev = latestStatus.status;
            latestStatus = next;

            if (
                next.status === 'captured' ||
                next.status === 'settled' ||
                next.status === 'failed'
            ) {
                teardownPoll();
                if (prev !== next.status) {
                    /* no-op */
                }
            }
        } catch {
            /* swallow */
        }
    }

    $effect(() => {
        if (pollState === 'pending' && pollHandle === null) {
            pollHandle = setInterval(pollOnce, POLL_INTERVAL_MS);
        }
        return () => teardownPoll();
    });

    /**
     * Wave 1 B3 follow-up: the inline Razorpay checkout wiring that used to
     * live here was hoisted into $shared/lib/razorpay.openRazorpayCheckout
     * so Donate.svelte and Success.svelte share one implementation. The
     * success-page flow is the recovery path — donors land here if the
     * modal was closed before the payment completed — so it still needs to
     * be able to re-open checkout on demand.
     */
    async function openCheckout(): Promise<void> {
        const keyId = latestStatus.public_key_id;
        const orderId = latestStatus.gateway_order_id;
        if (!keyId || !orderId) return;

        openingCheckout = true;
        try {
            await openRazorpayCheckout({
                orderId,
                keyId,
                amountMinor: latestStatus.amount_minor ?? 0,
                currency: latestStatus.currency_code ?? 'INR',
                appName,
                onSuccess: (response) => {
                    void confirmCapture(response);
                },
                onDismiss: () => {
                    openingCheckout = false;
                    router.visit('/donate/cancel');
                },
            });
        } catch (err) {
            openingCheckout = false;
            alert(
                err instanceof Error
                    ? err.message
                    : 'Razorpay failed to open.',
            );
        }
    }
</script>

<SeoHead />

<PublicLayout>
    <section class="relative overflow-hidden bg-background">
        <div
            class="pointer-events-none absolute -right-20 top-0 opacity-[0.10]"
            aria-hidden="true"
        >
            <MandalaDecoration size={320} tint="gold" />
        </div>

        <div class="container relative py-12 lg:py-20">
            <div class="mx-auto max-w-2xl">
                <header class="mb-8 space-y-3">
                    <div class="flex items-center gap-2">
                        {#if isCaptured}
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-primary"
                            >
                                <CheckCircle2 class="h-5 w-5" aria-hidden="true" />
                            </div>
                        {:else if isFailed}
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-full bg-destructive/10 text-destructive"
                            >
                                <XCircle class="h-5 w-5" aria-hidden="true" />
                            </div>
                        {:else if isTimedOut || isNotFound}
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-full bg-muted text-muted-foreground"
                            >
                                <AlertCircle class="h-5 w-5" aria-hidden="true" />
                            </div>
                        {:else}
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-full bg-muted text-muted-foreground"
                            >
                                <Clock
                                    class="h-5 w-5 animate-pulse"
                                    aria-hidden="true"
                                />
                            </div>
                        {/if}
                        <h1
                            class="font-serif text-3xl font-semibold lg:text-4xl"
                        >
                            {#if isCaptured}
                                Thank you
                            {:else if isFailed}
                                Payment failed
                            {:else if isTimedOut}
                                Status check timed out
                            {:else if isNotFound}
                                Order not found
                            {:else}
                                Donation status
                            {/if}
                        </h1>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        Order
                        <span class="font-mono text-xs">
                            {latestStatus.gateway_order_id || '(no order id)'}
                        </span>
                    </p>
                </header>

                <Card>
                    <CardHeader>
                        <CardTitle>Status</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <div class="flex items-baseline justify-between">
                            <span class="text-muted-foreground">Status</span>
                            <Badge
                                variant={isCaptured
                                    ? 'default'
                                    : isFailed
                                      ? 'destructive'
                                      : 'secondary'}
                            >
                                {latestStatus.status ?? 'unknown'}
                            </Badge>
                        </div>
                        {#if latestStatus.amount_minor !== null}
                            <div class="flex items-baseline justify-between">
                                <span class="text-muted-foreground">Amount</span>
                                <Money
                                    amountMinor={latestStatus.amount_minor}
                                    currencyCode={latestStatus.currency_code ??
                                        'INR'}
                                />
                            </div>
                        {/if}
                        {#if latestStatus.captured_at}
                            <div class="flex items-baseline justify-between">
                                <span class="text-muted-foreground"
                                    >Captured at</span
                                >
                                <span>{latestStatus.captured_at}</span>
                            </div>
                        {/if}
                        {#if isTimedOut}
                            <Alert>
                                We couldn't confirm your donation status in
                                time. Your payment may still be processing —
                                refresh to check, or contact us if the issue
                                persists.
                            </Alert>
                        {/if}
                        {#if isFailed && latestStatus.last_failure_reason}
                            <Alert variant="destructive">
                                {latestStatus.last_failure_reason}
                            </Alert>
                        {/if}
                    </CardContent>
                </Card>

                {#if isCaptured}
                    <div
                        class="mt-6 flex items-start gap-3 rounded-md border border-primary/20 bg-ivory p-4"
                    >
                        <Mail
                            class="mt-0.5 h-5 w-5 shrink-0 text-primary"
                            aria-hidden="true"
                        />
                        <div class="space-y-2">
                            {#if latestStatus.receipt}
                                <p class="text-sm font-medium">
                                    Your receipt is ready
                                </p>
                                <p
                                    class="text-xs leading-relaxed text-muted-foreground"
                                >
                                    Receipt
                                    <span class="font-mono"
                                        >{latestStatus.receipt.number}</span
                                    > has been issued. Download it and keep it
                                    for your records.
                                </p>
                                <Button
                                    href={latestStatus.receipt.download_path}
                                    variant="outline"
                                    size="sm"
                                >
                                    Download receipt (PDF)
                                </Button>
                            {:else}
                                <p class="text-sm font-medium">
                                    Payment received
                                </p>
                                <p
                                    class="text-xs leading-relaxed text-muted-foreground"
                                >
                                    Thank you — your donation is confirmed. Your
                                    official receipt is being generated and
                                    will appear here shortly; keep this page
                                    open or refresh in a moment.
                                </p>
                            {/if}
                        </div>
                    </div>
                {/if}

                <div
                    class="mt-8 flex flex-wrap items-center justify-between gap-3"
                >
                    <a
                        href="/"
                        class="text-sm text-muted-foreground hover:text-primary"
                    >
                        ← Back to home
                    </a>

                    {#if isOpenable}
                        <Button
                            type="button"
                            disabled={openingCheckout}
                            onclick={openCheckout}
                        >
                            {openingCheckout
                                ? 'Opening…'
                                : 'Open Razorpay checkout'}
                        </Button>
                    {:else if isFailed}
                        <Button href="/donate" variant="outline">
                            Try again
                        </Button>
                    {:else if isTimedOut || isNotFound}
                        <Button
                            type="button"
                            variant="outline"
                            onclick={() => {
                                pollAttempts = 0;
                                pollHandle = setInterval(
                                    pollOnce,
                                    POLL_INTERVAL_MS,
                                );
                            }}
                        >
                            Refresh status
                        </Button>
                    {/if}
                </div>
            </div>
        </section>
    </PublicLayout>

