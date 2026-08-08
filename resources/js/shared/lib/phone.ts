/**
 * Phone number helpers for the public site's donation form.
 *
 * Pure logic only — no Svelte, no DOM. Mirrors how currency.ts / dates.ts
 * are standalone modules so they can be unit-tested and reused.
 *
 * Doctrine:
 *   - The kernel deals in E.164 over the wire. The backend FormRequest
 *     (`App\Payments\Http\Requests\RazorpayCheckoutRequest`) accepts
 *     `+?[0-9\s-()]{7,20}`, so an E.164 string always passes.
 *   - India-first: the site takes INR donations, so the default country is
 *     India (+91) with a small curated diaspora list.
 *   - We never hard-code real-number verification (that is the phone
 *     carrier's job). Here we only (a) format for display, (b) validate a
 *     *plausible* national number per country, and (c) normalise to E.164.
 */

export interface PhoneCountry {
    /** ISO 3166-1 alpha-2 code, e.g. "IN". */
    code: string;
    name: string;
    /** E.164 country calling code including the leading +, e.g. "+91". */
    dial: string;
    /** Emoji flag for display. */
    flag: string;
    /** Bounds on the national-number digit count for acceptance. */
    minDigits: number;
    maxDigits: number;
    /** Display grouping (space-separated), e.g. India [5, 5]. Empty/absent = none. */
    grouping?: number[];
}

/**
 * India-first curated list. Deliberately small — every country is over-build
 * for an INR donation checkout. Extend here when the constituency needs it.
 */
export const PHONE_COUNTRIES: PhoneCountry[] = [
    { code: 'IN', name: 'India', dial: '+91', flag: '🇮🇳', minDigits: 10, maxDigits: 10, grouping: [5, 5] },
    { code: 'US', name: 'United States', dial: '+1', flag: '🇺🇸', minDigits: 10, maxDigits: 11 },
    { code: 'GB', name: 'United Kingdom', dial: '+44', flag: '🇬🇧', minDigits: 10, maxDigits: 11, grouping: [5, 6] },
    { code: 'AE', name: 'United Arab Emirates', dial: '+971', flag: '🇦🇪', minDigits: 8, maxDigits: 9 },
    { code: 'CA', name: 'Canada', dial: '+1', flag: '🇨🇦', minDigits: 10, maxDigits: 11 },
    { code: 'AU', name: 'Australia', dial: '+61', flag: '🇦🇺', minDigits: 9, maxDigits: 10 },
    { code: 'SG', name: 'Singapore', dial: '+65', flag: '🇸🇬', minDigits: 8, maxDigits: 8, grouping: [4, 4] },
    { code: 'NP', name: 'Nepal', dial: '+977', flag: '🇳🇵', minDigits: 8, maxDigits: 10 },
    { code: 'LK', name: 'Sri Lanka', dial: '+94', flag: '🇱🇰', minDigits: 9, maxDigits: 10 },
    { code: 'BD', name: 'Bangladesh', dial: '+880', flag: '🇧🇩', minDigits: 10, maxDigits: 11 },
];

/** Resolve a country by ISO code. Defaults to India when unknown. */
export function countryByCode(code: string): PhoneCountry {
    return (
        PHONE_COUNTRIES.find((c) => c.code === code) ??
        PHONE_COUNTRIES.find((c) => c.code === 'IN')! // India always present by construction
    );
}

/** Raw digits only, no separators. */
export function onlyDigits(value: string): string {
    return value.replace(/\D/g, '');
}

/** National-number validation result. */
export type PhoneValidity =
    | { ok: true; e164: string }
    | { ok: false; reason: 'empty' | 'too_short' | 'too_long' };

/**
 * Validate a national number for a country. Returns E.164 on success
 * (`+91` + national digits, no trunk prefix). Empty -> `empty`.
 */
export function validatePhone(country: PhoneCountry, national: string): PhoneValidity {
    const clean = onlyDigits(national);
    if (clean === '') {
        return { ok: false, reason: 'empty' };
    }
    if (clean.length < country.minDigits) {
        return { ok: false, reason: 'too_short' };
    }
    if (clean.length > country.maxDigits) {
        return { ok: false, reason: 'too_long' };
    }
    return { ok: true, e164: `${country.dial}${clean}` };
}

/** E.164 string, or null when empty/invalid (used by the form payload). */
export function toE164(country: PhoneCountry, national: string): string | null {
    const result = validatePhone(country, national);
    return result.ok ? result.e164 : null;
}

/** Format national digits for display using per-country grouping. */
export function formatNational(country: PhoneCountry, digits: string): string {
    const clean = onlyDigits(digits);
    if (!country.grouping || country.grouping.length === 0) {
        return clean;
    }
    const groups: string[] = [];
    let i = 0;
    for (const size of country.grouping) {
        if (i >= clean.length) break;
        groups.push(clean.slice(i, i + size));
        i += size;
    }
    const residue = clean.slice(i);
    if (residue) {
        groups.push(residue);
    }
    return groups.join(' ');
}

/** Full display string, e.g. "+91 98765 43210". */
export function formatFull(country: PhoneCountry, national: string): string {
    const clean = onlyDigits(national);
    if (clean === '') return country.dial;
    return `${country.dial} ${formatNational(country, clean)}`;
}

/** Strip a leading trunk prefix `0` from a national number, where present. */
export function stripTrunkZero(national: string): string {
    return national.startsWith('0') ? national.slice(1) : national;
}