/**
 * Small currency helpers for the public site.
 *
 * The kernel only deals in ISO 4217 currency codes over the wire.
 * `currencySymbol()` is the only place we map those to display glyphs.
 * Add more entries as the kernel starts accepting more currencies.
 */

const SYMBOLS: Record<string, string> = {
    INR: '₹',
    USD: '$',
    EUR: '€',
    GBP: '£',
    JPY: '¥',
    AUD: 'A$',
    CAD: 'C$',
    SGD: 'S$',
};

/**
 * Display glyph for an ISO 4217 currency code. Falls back to the code
 * itself (e.g. "AUD 50.00") for currencies we haven't added a glyph
 * for — never returns an empty string.
 */
export function currencySymbol(code: string): string {
    return SYMBOLS[code] ?? code;
}

/**
 * Locale-aware currency name for the prose "Donations in INR" / "USD gifts".
 * Falls back to the code if the locale doesn't match a known currency.
 */
const NAMES: Record<string, string> = {
    INR: 'Indian rupees',
    USD: 'US dollars',
    EUR: 'euros',
    GBP: 'British pounds',
};

export function currencyName(code: string): string {
    return NAMES[code] ?? code;
}
