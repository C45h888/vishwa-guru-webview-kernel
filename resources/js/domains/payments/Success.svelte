<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import { Card, CardContent, CardHeader, CardTitle } from '$shared/ui/card';
    import { Button } from '$shared/ui/button';
    import { Badge } from '$shared/ui/badge';
    import { Alert } from '$shared/ui/alert';
    import Money from '$shared/components/Money.svelte';
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

    const isCaptured = $derived(payment.status === 'captured' || payment.status === 'settled' || payment.status === 'settling');
    const isOpenable = $derived(
        payment.status === 'initialized'
        || payment.status === 'pending'
        || payment.status === 'authorized'
        || payment.status === null
    );

    function teardownPoll(): void {
        if (pollHandle !== null) {
            clearInterval(pollHandle);
            pollHandle = null;
        }
    }

    async function openCheckout(): Promise<void> {
        if (!payment.public_key_id || !payment.gateway_order_id) {
            return;
        }

        openingCheckout = true;

        type RazorpayOptions = {
            key: string;
            order_id: string;
            amount: number;
            currency: string;
            name: string;
            description: string;
            handler: (response: { razorpay_payment_id: string; razorpay_order_id: string; razorpay_signature: string }) => void;
            modal: { ondismiss: () => void };
        };
        type RazorpayCtor = new (options: RazorpayOptions) => { open: () => void; on: (event: string, handler: (response: unknown) => void) => void };

        let scriptLoadedOk = true;
        await new Promise<void>((resolve, reject) => {
            const existing = document.getElementById('razorpay-checkout-js');
            if (existing) {
                resolve();
                return;
            }
            const script = document.createElement('script');
            script.id = 'razorpay-checkout-js';
            script.src = 'https://checkout.razorpay.com/v1/checkout.js';
            script.async = true;
            script.onload = () => resolve();
            script.onerror = () => reject(new Error('Razorpay checkout.js failed to load.'));
            document.head.appendChild(script);
        }).catch((err: Error) => {
            scriptLoadedOk = false;
            alert(err.message);
        });

        if (!scriptLoadedOk) {
            openingCheckout = false;
            return;
        }

        const RazorpayConstructor = (window as unknown as { Razorpay?: RazorpayCtor }).Razorpay;
        if (!RazorpayConstructor) {
            openingCheckout = false;
            alert('Razorpay is not available on window.');
            return;
        }

        const checkout = new RazorpayConstructor({
            key: payment.public_key_id,
            order_id: payment.gateway_order_id,
            amount: payment.amount_minor ?? 0,
            currency: payment.currency_code ?? 'INR',
            name: appName,
            description: 'Donation',
            handler: () => {
                // Server-side webhook will mark the payment captured;
                // poll /donate/success?gateway_order_id=… for status.
                startPolling();
            },
            modal: {
                ondismiss: () => {
                    openingCheckout = false;
                },
            },
        });

        checkout.on('payment.failed', (response) => {
            console.error('Razorpay payment.failed', response);
            openingCheckout = false;
        });

        checkout.open();
    }

    function startPolling(): void {
        teardownPoll();
        let attempts = 0;
        pollHandle = setInterval(async () => {
            attempts++;
            try {
                const response = await fetch(`/donate/success?gateway_order_id=${encodeURIComponent(payment.gateway_order_id)}`, {
                    headers: { Accept: 'application/json', 'X-Inertia': 'true', 'X-Inertia-Partial-Component': 'payments/Success' },
                });
                if (!response.ok) return;
                const data = await response.json();
                const newStatus: string | undefined = data?.props?.payment?.status;
                if (newStatus && newStatus !== payment.status) {
                    teardownPoll();
                    router.visit(`/donate/success?gateway_order_id=${encodeURIComponent(payment.gateway_order_id)}`, {
                        only: ['payment'],
                        preserveScroll: true,
                    });
                }
            } catch {
                /* swallow transient errors; poll again */
            }
            if (attempts >= 30) {
                teardownPoll();
                openingCheckout = false;
            }
        }, 2000);
    }
</script>

<svelte:head>
    <title>Donate — {appName}</title>
</svelte:head>

<PublicLayout>
    <div class="mx-auto max-w-2xl space-y-6">
        <header class="space-y-2">
            <h1 class="text-3xl font-semibold">
                {isCaptured ? 'Thank you' : 'Donation status'}
            </h1>
            <p class="text-sm text-muted-foreground">
                Order
                <span class="font-mono text-xs">{payment.gateway_order_id || '(no order id)'}</span>
            </p>
        </header>

        <Card>
            <CardHeader>
                <CardTitle>Status</CardTitle>
            </CardHeader>
            <CardContent class="space-y-3 text-sm">
                <div class="flex items-baseline justify-between">
                    <span class="text-muted-foreground">Status</span>
                    <Badge variant={isCaptured ? 'default' : 'secondary'}>
                        {payment.status ?? 'unknown'}
                    </Badge>
                </div>
                {#if payment.amount_minor !== null}
                    <div class="flex items-baseline justify-between">
                        <span class="text-muted-foreground">Amount</span>
                        <Money amountMinor={payment.amount_minor} currencyCode={payment.currency_code ?? 'INR'} />
                    </div>
                {/if}
                {#if payment.captured_at}
                    <div class="flex items-baseline justify-between">
                        <span class="text-muted-foreground">Captured at</span>
                        <span>{payment.captured_at}</span>
                    </div>
                {/if}
                {#if payment.last_failure_reason}
                    <Alert variant="destructive">
                        {payment.last_failure_reason}
                    </Alert>
                {/if}
            </CardContent>
        </Card>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="/" class="text-sm hover:underline">← Back to home</a>

            {#if isOpenable}
                <Button type="button" disabled={openingCheckout} onclick={openCheckout}>
                    {openingCheckout ? 'Opening…' : 'Open Razorpay checkout'}
                </Button>
            {:else if isCaptured}
                <Button
                    type="button"
                    href={`/receipts/`}
                >
                    View receipt
                </Button>
            {/if}
        </div>

        <p class="text-xs text-muted-foreground">
            Razorpay public key id (test mode): <span class="font-mono">{payment.public_key_id || '—'}</span>
        </p>
    </div>
</PublicLayout>
