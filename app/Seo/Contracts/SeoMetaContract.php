<?php

declare(strict_types=1);

namespace App\Seo\Contracts;

/**
 * SeoMetaContract — builds the per-page metadata payload that the
 * Inertia root shell prints into <head> on the server.
 *
 * WHY THIS EXISTS (AGENTS.md §"Pass 5: SEO Optimisation")
 *
 * The public webview is an Inertia SPA. Before this contract, every
 * SEO tag was authored inside `<svelte:head>` (resources/js/shared/
 * components/SeoHead.svelte), which means the tags only exist after
 * the browser executes JavaScript. That has two consequences:
 *
 *   1. Link-preview scrapers — WhatsApp, Facebook (facebookexternalhit)
 *      and iMessage (Apple's unfurl service) — never execute JS. They
 *      GET the URL and read <head> directly. They saw no description,
 *      no OG tags and no JSON-LD at all.
 *   2. Google indexes the JS-rendered document in a second pass, which
 *      is slower and ranks lower than a server-rendered equivalent.
 *
 * The fix is to build the metadata on the BACKEND and print it from
 * the root Blade shell. Inertia hands the fully-resolved page object to
 * that shell as `$page` (vendor/inertiajs/inertia-laravel/src/
 * Response.php:219), so the builder's output is available server-side
 * for free — no second query, no extra round trip.
 *
 * DOCTRINE NOTES
 *
 * - The backend is the single source of truth. `SeoHead.svelte` still
 *   renders the same values on the client, but only because Inertia
 *   client-side navigations return JSON (X-Inertia header) and never
 *   re-render the Blade shell. Both writers emit IDENTICAL values;
 *   the server-rendered tags appear first in document order, which is
 *   what every scraper reads.
 * - Controllers MUST NOT assemble tag arrays by hand. They pass
 *   domain values to `forPage()` and let the builder normalise.
 * - The canonical host is derived from `config('app.url')` alone, so
 *   canonical tags, the sitemap and og:url can never disagree.
 */
interface SeoMetaContract
{
    /**
     * Build the metadata payload for one page.
     *
     * @param  string       $title        Bare page title. The trust name is appended unless $appendTrustName is false.
     * @param  string|null  $description  Meta description. Omitted from the payload when null/blank.
     * @param  string|null  $imageUrl     Absolute (or root-relative) share image — hero/cover. Normalised to absolute.
     * @param  string|null  $imageAlt     Alt text for the share image.
     * @param  string       $type         Open Graph type: 'website' or 'article'.
     * @param  bool         $noindex      Transactional pages (receipt/success/cancel) set this.
     * @param  array<string, mixed>|null $jsonLd Schema.org graph. Embedded verbatim as application/ld+json.
     * @param  bool         $appendTrustName Append ` — {appName}` to the title. False for the homepage, where the trust name alone is the title.
     * @param  string|null  $path         Override the canonical path. Defaults to the current request path.
     *
     * @return array{title: string, tags: list<array{tag: string, attrs: array<string, string>}>, jsonLd: array<string, mixed>|null}
     */
    public function forPage(
        string $title,
        ?string $description = null,
        ?string $imageUrl = null,
        ?string $imageAlt = null,
        string $type = 'website',
        bool $noindex = false,
        ?array $jsonLd = null,
        bool $appendTrustName = true,
        ?string $path = null,
    ): array;

    /**
     * The trust-level `HinduTemple` graph.
     *
     * Moved off `resources/js/domains/cms/Home.svelte` (which authored
     * it inline as a client-side object) so it is present in the
     * initial HTML. Returned as the single top-level graph; callers
     * pass it to `forPage()` on the pages where the trust entity is
     * the subject.
     *
     * @return array<string, mixed>
     */
    public function trustGraph(): array;
}
