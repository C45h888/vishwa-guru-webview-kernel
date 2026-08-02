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

    let {
        payment,
        appName,
    }: AppPageProps<{
        payment: PaymentStatusProps;
    }> = $props();

    let openingCheckout = $state(false);
    let pollHandle: ReturnType<typeof setInterval> | null = null;
    let latestStatus = $state<PaymentStatusProps>(payment);
    let pollAttempts = $state(0);

    const MAX_POLL_ATTEMPTS = 30;
    const POLL_INTERVAL_MS = 2000;

    type PollState =
        | 'captured'
        | 'pending'
        | 'failed'
        | 'timeout'
        | 'not_found';

    const pollState = $derived<PollState>(derivePollState());

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

    type RazorpayOptions = {
        key: string;
        order_id: string;
        amount: number;
        currency: string;
        name: string;
        description: string;
        handler: (response: {
            razorpay_payment_id: string;
            razorpay_order_id: string;
            razorpay_signature: string;
        }) => void;
        modal: { ondismiss: () => void };
    };
    type RazorpayCtor = new (options: RazorpayOptions) => {
        open: () => void;
        on: (event: string, handler: (response: unknown) => void) => void;
    };

    async function openCheckout(): Promise<void> {
        const keyId = latestStatus.public_key_id;
        const orderId = latestStatus.gateway_order_id;
        if (!keyId || !orderId) return;

        openingCheckout = true;

        let scriptLoadedOk = true;
        await new Promise<void>((resolve, reject) => {
            const existing = document.getElementById(
                'razorpay-checkout-js',
            );
            if (existing) {
                resolve();
                return;
            }
            const script = document.createElement('script');
            script.id = 'razorpay-checkout-js';
            script.src = 'https://checkout.razorpay.com/v1/checkout.js';
            script.async = true;
            script.onload = () => resolve();
            script.onerror = () =>
                reject(new Error('Razorpay checkout.js failed to load.'));
            document.head.appendChild(script);
        }).catch((err: Error) => {
            scriptLoadedOk = false;
            alert(err.message);
        });

        if (!scriptLoadedOk) {
            openingCheckout = false;
            return;
        }

        const RazorpayConstructor = (
            window as unknown as { Razorpay?: RazorpayCtor }
        ).Razorpay;
        if (!RazorpayConstructor) {
            openingCheckout = false;
            alert('Razorpay is not available on window.');
            return;
        }

        const checkout = new RazorpayConstructor({
            key: keyId,
            order_id: orderId,
            amount: latestStatus.amount_minor ?? 0,
            currency: latestStatus.currency_code ?? 'INR',
            name: appName,
            description: 'Donation',
            handler: () => {
                pollAttempts = 0;
                pollHandle = setInterval(pollOnce, POLL_INTERVAL_MS);
            },
            modal: {
                ondismiss: () => {
                    openingCheckout = false;
                    router.visit('/donate/cancel');
                },
            },
        });

        checkout.on('payment.failed', (response) => {
            console.error('Razorpay payment.failed', response);
            openingCheckout = false;
        });

        checkout.open();
    }
</script>

<svelte:head>
    <title>Donation status — {appName}</title>
</svelte:head>

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
                        <div class="space-y-1">
                            <p class="text-sm font-medium">
                                Your receipt is on its way
                            </p>
                            <p
                                class="text-xs leading-relaxed text-muted-foreground"
                            >
                                An official receipt will be emailed to you
                                shortly. Keep it for your records — the
                                temple's gratitude is already in the offering.
                            </p>
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

                <p class="mt-6 text-xs text-muted-foreground">
                    Razorpay public key id (test mode): <span class="font-mono"
                        >{latestStatus.public_key_id || '—'}</span
                    >
                </p>
            </div>
        </div>
    </section>
</PublicLayout>
