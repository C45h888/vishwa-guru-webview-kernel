/**
 * Email validation helper for form fields across the public site.
 *
 * Pure logic, no DOM. Used to give inline (as-you-type) feedback that is
 * *predictive* of the backend contract, never stricter in a way that
 * rejects a value the backend would accept.
 *
 * Backend contract (App\Payments\Http\Requests\RazorpayCheckoutRequest):
 *   `donor.email` -> nullable, `email:rfc`, max 255.
 *
 * So: empty is valid (optional donor field), a present value must be a
 * plausible RFC-ish address, and length must stay <= 255.
 */

const MAX_EMAIL_LENGTH = 255;

/**
 * A balanced RFC-5322-ish pattern without being byte-perfect: local part
 * plus a dot-separated domain with at least one dot and a TLD. Deliberately
 * pragmatic — it exists to catch typos, not to gate on superseded RFC
 * minutiae. Never rejects something the backend `email:rfc` would accept.
 */
// eslint-disable-next-line no-useless-escape
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

export type EmailValidity = { ok: true; reason: 'valid' } | { ok: false; reason: 'empty' | 'too_long' | 'invalid' };

export function validateEmail(email: string | null | undefined): EmailValidity {
    const value = email?.trim() ?? '';
    if (value === '') {
        return { ok: false, reason: 'empty' };
    }
    if (value.length > MAX_EMAIL_LENGTH) {
        return { ok: false, reason: 'too_long' };
    }
    if (!EMAIL_PATTERN.test(value)) {
        return { ok: false, reason: 'invalid' };
    }
    return { ok: true, reason: 'valid' };
}

/** Human-friendly inline message for a given validity. Returns null when valid. */
export function emailErrorMessage(validity: EmailValidity): string | null {
    if (validity.ok) return null;
    switch (validity.reason) {
        case 'too_long':
            return `Email must be at most ${MAX_EMAIL_LENGTH} characters.`;
        case 'invalid':
            return 'Please enter a valid email address.';
        default:
            return null;
    }
}