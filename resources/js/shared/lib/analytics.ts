/**
 * analytics.ts — thin, dependency-free tracking helper (Phase 1).
 *
 * Pushes GA4-style events onto `window.dataLayer` (consumed by GTM, or by
 * gtag when GA4_ID is loaded directly) and mirrors them to the Meta Pixel
 * when `fbq` is present. Every call is a no-op when no tag is installed,
 * and never throws — analytics must never break the donate flow.
 */

type DataLayerEvent = Record<string, unknown>;

declare global {
    interface Window {
        dataLayer?: DataLayerEvent[];
        fbq?: (...args: unknown[]) => void;
    }
}

const PURCHASE_KEY_PREFIX = 'vg:purchase-tracked:';

function push(event: DataLayerEvent): void {
    try {
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push(event);
    } catch {
        /* never break the page */
    }
}

function pixel(event: string, params: Record<string, unknown>, eventId?: string): void {
    try {
        if (typeof window.fbq === 'function') {
            window.fbq('track', event, params, eventId ? { eventID: eventId } : undefined);
        }
    } catch {
        /* never break the page */
    }
}

/** Minor units (paise) → major units (rupees). */
export function minorToMajor(amountMinor: number | null | undefined): number {
    return typeof amountMinor === 'number' && Number.isFinite(amountMinor)
        ? Math.round(amountMinor) / 100
        : 0;
}

export interface CheckoutTrackParams {
    orderId: string;
    amountMinor: number;
    currency?: string;
    campaignId?: string | null;
}

/** Fire when the Razorpay checkout window is about to open. */
export function trackBeginCheckout(p: CheckoutTrackParams): void {
    const value = minorToMajor(p.amountMinor);
    const currency = p.currency || 'INR';
    push({ ecommerce: null });
    push({
        event: 'begin_checkout',
        ecommerce: {
            currency,
            value,
            items: [{ item_id: p.campaignId || 'donation', item_name: 'Donation', price: value, quantity: 1 }],
        },
        gateway_order_id: p.orderId,
    });
    pixel('InitiateCheckout', { value, currency }, `checkout_${p.orderId}`);
}

export interface PurchaseTrackParams {
    orderId: string;
    amountMinor: number | null;
    currency?: string | null;
}

/**
 * Fire once per order when the payment is authoritatively captured or
 * settled. Deduplicated across reloads/polls via sessionStorage.
 * Returns true when the event was sent.
 */
export function trackPurchaseOnce(p: PurchaseTrackParams): boolean {
    if (!p.orderId) return false;
    const key = PURCHASE_KEY_PREFIX + p.orderId;
    try {
        if (window.sessionStorage.getItem(key)) return false;
        window.sessionStorage.setItem(key, String(Date.now()));
    } catch {
        /* storage unavailable: fall through, best-effort */
    }

    const value = minorToMajor(p.amountMinor);
    const currency = p.currency || 'INR';
    push({ ecommerce: null });
    push({
        event: 'purchase',
        ecommerce: {
            transaction_id: p.orderId,
            currency,
            value,
            items: [{ item_id: 'donation', item_name: 'Donation', price: value, quantity: 1 }],
        },
    });
    // Meta: Donate is the standard nonprofit event; eventID lets a future
    // server-side CAPI send deduplicate against this browser event.
    pixel('Donate', { value, currency }, `donate_${p.orderId}`);
    return true;
}
