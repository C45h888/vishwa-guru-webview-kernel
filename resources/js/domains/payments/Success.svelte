<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import { Card, CardContent, CardHeader, CardTitle } from '$shared/ui/card';
    import { Button } from '$shared/ui/button';
    import { Badge } from '$shared/ui/badge';
    import { Alert } from '$shared/ui/alert';
    import Money from '$shared/components/Money.svelte';
    import { CheckCircle2, XCircle, Clock, AlertCircle } from 'lucide-svelte';
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

    // ─── State abstraction ─────────────────────────────────────────────
    // Explicit poll states mirror the backend PaymentStateMachine transitions
    // so the UI never has to inspect raw status strings to decide rendering.
    type PollState =
        | 'captured'
        | 'pending'
        | 'failed'
        | 'timeout'
        | 'not_found';

    const pollState = $derived<PollState>(derivePollState());

    function derivePollState(): PollState {
        const s = latestStatus.status;
        if (s === 'captured' || s === 'settled' || s === 'settling') {
            return 'captured';
        }
        if (s === 'failed') {
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

    // ─── Derived display flags ──────────────────────────────────────────
    const isCaptured = $derived(pollState === 'captured');
    const isFailed = $derived(pollState === 'failed');
    const isPending = $derived(pollState === 'pending');
    const isTimedOut = $derived(pollState === 'timeout');
    const isNotFound = $derived(pollState === 'not_found');
    const isOpenable = $derived(isPending && Boolean(latestStatus.public_key_id));

    // ─── Polling lifecycle ─────────────────────────────────────────────
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

            // Stop polling on terminal transitions.
            if (
                next.status === 'captured' ||
                next.status === 'settled' ||
                next.status === 'failed'
            ) {
                teardownPoll();
                if (prev !== next.status) {
                    // No-op; state-derived UI handles the change.
                }
            }
        } catch {
            /* swallow transient errors; poll again */
        }
    }

    // ─── Effect: start polling on mount if needed ─────────────────────
    $effect(() => {
        if (pollState === 'pending' && pollHandle === null) {
            pollHandle = setInterval(pollOnce, POLL_INTERVAL_MS);
        }
        return () => teardownPoll();
    });

    // ─── Razorpay checkout flow ────────────────────────────────────────
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
                // Server-side webhook will mark the payment captured;
                // poll /donate/success?gateway_order_id=… for status.
                pollAttempts = 0;
                pollHandle = setInterval(pollOnce, POLL_INTERVAL_MS);
            },
            modal: {
                ondismiss: () => {
                    // Razorpay modal closed without completing payment.
                    // Route the donor to the cancel page for a clean state.
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
    <div class="mx-auto max-w-2xl space-y-6">
        <header class="space-y-2">
            <div class="flex items-center gap-2">
                {#if isCaptured}
                    <CheckCircle2
                        class="h-6 w-6 text-primary"
                        aria-hidden="true"
                    />
                {:else if isFailed}
                    <XCircle
                        class="h-6 w-6 text-destructive"
                        aria-hidden="true"
                    />
                {:else if isTimedOut}
                    <AlertCircle
                        class="h-6 w-6 text-muted-foreground"
                        aria-hidden="true"
                    />
                {:else if isNotFound}
                    <AlertCircle
                        class="h-6 w-6 text-muted-foreground"
                        aria-hidden="true"
                    />
                {:else}
                    <Clock
                        class="h-6 w-6 animate-pulse text-muted-foreground"
                        aria-hidden="true"
                    />
                {/if}

                <h1 class="font-serif text-3xl font-semibold lg:text-4xl">
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

        <!-- ─── Status card ──────────────────────────────────────────── -->
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
                            currencyCode={latestStatus.currency_code ?? 'INR'}
                        />
                    </div>
                {/if}
                {#if latestStatus.captured_at}
                    <div class="flex items-baseline justify-between">
                        <span class="text-muted-foreground">Captured at</span>
                        <span>{latestStatus.captured_at}</span>
                    </div>
                {/if}
                {#if isTimedOut}
                    <Alert>
                        We couldn't confirm your donation status in time. Your
                        payment may still be processing — refresh to check, or
                        contact us if the issue persists.
                    </Alert>
                {/if}
                {#if isFailed && latestStatus.last_failure_reason}
                    <Alert variant="destructive">
                        {latestStatus.last_failure_reason}
                    </Alert>
                {/if}
            </CardContent>
        </Card>

        <!-- ─── Actions ─────────────────────────────────────────────── -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a
                href="/"
                class="text-sm hover:underline"
            >
                ← Back to home
            </a>

            {#if isCaptured && latestStatus.gateway_order_id}
                <Button href={`/receipts/${latestStatus.gateway_order_id}`}>
                    View receipt
                </Button>
            {:else if isOpenable}
                <Button
                    type="button"
                    disabled={openingCheckout}
                    onclick={openCheckout}
                >
                    {openingCheckout ? 'Opening…' : 'Open Razorpay checkout'}
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

        <p class="text-xs text-muted-foreground">
            Razorpay public key id (test mode): <span class="font-mono"
                >{latestStatus.public_key_id || '—'}</span
            >
        </p>
    </div>
</PublicLayout>