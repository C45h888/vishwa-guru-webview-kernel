/**
 * Parses a Postgres TIMESTAMPTZ string into a JS Date.
 *
 * Postgres returns timestamps in ISO-like format with microsecond precision
 * (6 digits) and a `+00` timezone offset. The native `new Date(...)`
 * constructor chokes on the microsecond suffix in older runtimes and
 * silently returns `Invalid Date` in V8 for the space-separated form
 * (`2026-08-04 16:40:20.482762+00`).
 *
 * Doctrine: the API serialises timestamps via JSON, which preserves the
 * Postgres format. Wrap the parse in a helper so the rest of the Svelte
 * code can use the standard `Date` API without re-implementing the
 * format conversion at every callsite.
 *
 * Supported input formats:
 *   - `2026-08-04 16:40:20.482762+00`           (Postgres default)
 *   - `2026-08-04T16:40:20.482762+00:00`         (ISO 8601 with T sep)
 *   - `2026-08-04 16:40:20`                      (no tz, treated as UTC)
 *   - `2026-08-04T16:40:20Z`                     (UTC shorthand)
 */
export function parseDbDate(value: string | null | undefined): Date | null {
    if (!value) return null;
    // Normalise: space → T, `+00` → `+00:00`, drop extra microsecond digits.
    const normalised = value
        .replace(' ', 'T')
        .replace(/(\+\d{2})$/, '$1:00')
        .replace(/\.(\d{3})\d+/, '.$1');

    const ts = Date.parse(normalised);
    if (Number.isNaN(ts)) return null;
    return new Date(ts);
}

/**
 * Format a Postgres timestamp for display.
 *
 * Returns the original string if parsing fails (instead of rendering
 * "Invalid Date") so the user sees the raw value rather than a broken
 * date.
 */
export function formatDbDate(
    value: string | null | undefined,
    fallback = '—',
    options: Intl.DateTimeFormatOptions = { dateStyle: 'medium', timeStyle: 'short' },
): string {
    const d = parseDbDate(value);
    if (d === null) return value || fallback;
    try {
        return new Intl.DateTimeFormat('en-IN', options).format(d);
    } catch {
        return value || fallback;
    }
}
