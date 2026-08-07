/**
 * slugify — turn a human title into a URL-safe slug.
 *
 * Doctrine: slug rules from the server's FormRequest validation:
 *   - lowercase letters, digits, hyphens, underscores
 *   - must start with a lowercase letter or digit
 *   - max 120 characters
 *
 * This is the client-side mirror of the regex. The server is the
 * authoritative validator; the client uses this purely to keep the
 * slug field in sync with the title for "new" forms.
 *
 * If the admin edits the slug manually, the auto-fill stops (we track
 * that via the `slugWasManuallyEdited` flag in the form).
 */
export function slugify(input: string): string {
    return input
        .toLowerCase()
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')   // strip combining diacritics
        .replace(/[^a-z0-9]+/g, '-')        // non-alphanumeric runs → single hyphen
        .replace(/^-+|-+$/g, '')            // trim leading/trailing hyphens
        .replace(/-+/g, '-')                  // collapse multiple hyphens
        .slice(0, 120);
}
