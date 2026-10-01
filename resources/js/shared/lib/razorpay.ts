/**
 * Open the Razorpay Checkout modal for an existing gateway order.
 *
 * Hoisted out of Success.svelte (where it previously lived, gated behind
 * a manual "Open Razorpay checkout" button) so Donate.svelte can invoke
 * it inline after the order POST. The donor should never have to land
 * on a "pending" success page and then click a second button — most
 * abandon the flow entirely. The modal opens here on submit success,
 * and only the `modal.ondismiss` / error paths fall back to
 * /donate/success for the polling recovery.
 *
 * Caller supplies the order payload returned by
 * POST /api/v1/razorpay/checkout — see
 * app/Http/Controllers/Payments/RazorpayCheckoutController.php:42-83.
 */

declare global {
    interface Window {
        Razorpay?: new (options: RazorpayOptions) => RazorpayInstance;
    }
}

export interface RazorpayOptions {
    key: string;
    order_id: string;
    amount: number;
    currency: string;
    name: string;
    description?: string;
    handler?: (response: unknown) => void;
    modal?: {
        ondismiss?: () => void;
    };
}

export interface RazorpayInstance {
    on: (event: string, handler: (response: unknown) => void) => void;
    open: () => void;
}

export interface RazorpaySuccessResponse {
    razorpay_payment_id: string;
    razorpay_order_id: string;
    razorpay_signature: string;
}

export interface RazorpayCheckoutInput {
    orderId: string;
    keyId: string;
    /** Minor units (paise for INR) — matches the AGENTS.md money doctrine. */
    amountMinor: number;
    currency: string;
    appName: string;
    description?: string;
    /**
     * Called with Razorpay's signed checkout response. Callers should
     * confirm the capture server-side (POST /api/v1/razorpay/verify)
     * before treating the payment as successful.
     */
    onSuccess?: (response?: RazorpaySuccessResponse) => void;
    onDismiss?: () => void;
}

const SCRIPT_ID = 'razorpay-checkout-js';
const SCRIPT_SRC = 'https://checkout.razorpay.com/v1/checkout.js';

function loadRazorpaySdk(): Promise<void> {
    return new Promise<void>((resolve, reject) => {
        const existing = document.getElementById(SCRIPT_ID);
        if (existing) {
            resolve();
            return;
        }
        const script = document.createElement('script');
        script.id = SCRIPT_ID;
        script.src = SCRIPT_SRC;
        script.async = true;
        script.onload = () => resolve();
        script.onerror = () =>
            reject(new Error('Razorpay checkout.js failed to load.'));
        document.head.appendChild(script);
    });
}

export async function openRazorpayCheckout(
    input: RazorpayCheckoutInput,
): Promise<void> {
    if (!input.orderId || !input.keyId) {
        throw new Error('openRazorpayCheckout: orderId and keyId are required');
    }

    await loadRazorpaySdk();

    const Ctor = window.Razorpay;
    if (!Ctor) {
        throw new Error('Razorpay global is not available after script load.');
    }

    const checkout = new Ctor({
        key: input.keyId,
        order_id: input.orderId,
        amount: input.amountMinor,
        currency: input.currency,
        name: input.appName,
        description: input.description ?? 'Donation',
        handler: (response: unknown) => {
            input.onSuccess?.(response as RazorpaySuccessResponse);
        },
        modal: {
            ondismiss: () => {
                input.onDismiss?.();
            },
        },
    });

    checkout.on('payment.failed', (response) => {
        console.error('Razorpay payment.failed', response);
    });

    checkout.open();
}
