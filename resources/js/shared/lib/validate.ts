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

/**
 * Indian PAN validation helpers.
 *
 * Backend contract (App\Payments\Http\Requests\RazorpayCheckoutRequest and
 * App\Payments\Domain\ValueObjects\DonorIdentity): PAN is stored canonically
 * as 10 uppercase characters, 5 letters + 4 digits + 1 letter (AAAAA9999A).
 *
 * The backend request normalizes casing and separators before validating, so
 * the frontend mirrors that: normalize as the donor types, then validate the
 * normalized value. Empty is valid (anonymous donors / amounts below the
 * 80G threshold never send a PAN).
 */

const PAN_LENGTH = 10;
const PAN_PATTERN = /^[A-Z]{5}[0-9]{4}[A-Z]$/;

/** Uppercase, strip spaces/hyphens, and cap at 10 chars. */
export function normalizePan(input: string | null | undefined): string {
    return (input ?? '')
        .toUpperCase()
        .replace(/[^A-Z0-9]/g, '')
        .slice(0, PAN_LENGTH);
}

export type PanValidity =
    | { ok: true; reason: 'empty' | 'valid' }
    | { ok: false; reason: 'empty' | 'incomplete' | 'invalid' };

export function validatePan(input: string | null | undefined): PanValidity {
    const value = normalizePan(input);
    if (value === '') {
        return { ok: true, reason: 'empty' };
    }
    if (value.length !== PAN_LENGTH) {
        return { ok: false, reason: 'incomplete' };
    }
    if (!PAN_PATTERN.test(value)) {
        return { ok: false, reason: 'invalid' };
    }
    return { ok: true, reason: 'valid' };
}

/** Human-friendly inline message. Returns null when empty or valid. */
export function panErrorMessage(validity: PanValidity): string | null {
    if (validity.ok) return null;
    switch (validity.reason) {
        case 'incomplete':
            return 'PAN must be 10 characters (AAAAA9999A).';
        case 'invalid':
            return 'PAN must be 5 letters, 4 digits, then a letter (e.g. ABCDE1234F).';
        default:
            return null;
    }
}