# Seo Kernel

> One-screen navigation map for the `Seo` kernel.
> Read order: Boundaries → Contracts → Providers → FSMs → Tests.

## Boundaries

**Owns:** the public webview's discoverability surface, and nothing
else. Three things live here:

1. **Per-page metadata** — `SeoMetaBuilder` produces the title,
   description, canonical, Open Graph quartet, Twitter card tags and
   Schema.org graph for one page. Rendered by
   `resources/views/app.blade.php` into the initial HTML.
2. **XML sitemap** — `SitemapBuilder` + `SitemapUrl` enumerate the
   public URL set and render it. Redis-cached for 6h.
3. **robots.txt body** — a static string assembled by
   `Public\Seo\RobotsController`, served as plain text.

**Does NOT own:** page content of any kind. It never queries the
database, holds no entities, and has no write path. It also does NOT
own the Inertia root view — `resources/views/app.blade.php` remains the
runtime's only Blade template and merely *prints* what this kernel
produces.

**Outbound edges (read-only):**
- Into Campaigns via `App\Campaigns\Contracts\CampaignsQueryContract`
  (`listDisplayable` slugs only — a campaign that is not publicly
  displayable can never enter the sitemap).
- Into Events via `App\Events\Contracts\EventsQueryContract`
  (`listUpcoming` + `listPast` slugs).
- Into Gallery via `App\Gallery\Contracts\GalleryQueryContract`
  (`listDisplayable` slugs only).
- Into Shared for `config('app.url')` / `config('app.name')` and the
  cache store.

**Inbound edges:** every public controller depends on
`SeoMetaContract` to emit the `seo` prop. This is the only write
direction — no kernel may construct meta arrays by hand.

## Contracts

| Contract | FQCN | Direction |
|---|---|---|
| SeoMetaContract | `App\Seo\Contracts\SeoMetaContract` | Consumed by every `App\Http\Controllers\Public\*` controller |

`SeoMetaContract::forPage()` returns
`{title, tags[], jsonLd, jsonLdString}`. `tags` is a flat, ordered list
of `{tag, attrs}` pairs so the root Blade shell can render it with one
generic loop and hold zero SEO logic.

`jsonLdString` is pre-encoded by the builder on purpose: the Blade
shell and `SeoHead.svelte` both print that exact string, so the
server-rendered and client-rendered `<script>` blocks cannot drift.

## Providers

- `app/Seo/Providers/SeoServiceProvider.php` — registered in
  `bootstrap/providers.php` **last** (`Events → Seo`).
- Boot-order invariant: Seo is a **leaf**. It declares no boot-time
  dependency on any other kernel, and every other kernel's bindings are
  already registered by the time it loads. Cross-referenced by
  `tests/Unit/Bootstrap/ProviderOrderTest.php`.
- `SeoMetaContract` is bound with `bind`, **not** `singleton`: the
  builder depends on the current `Illuminate\Http\Request` to derive
  the canonical path, and a shared instance would leak one request's
  path into another under long-lived workers.
- `SitemapBuilder` is a `singleton`: it holds no request state.

## Canonical host pinning

`App\Providers\AppServiceProvider::boot()` calls
`URL::forceRootUrl(rtrim(config('app.url'), '/'))` when `app.url`
carries a scheme.

This is load-bearing, not cosmetic. `route()` and `url()` derive
absolute URLs from the host a request *arrived on*. Before this pass
the live site advertised `https://www.vsrsms.in/...` in its sitemap
while its canonical tags advertised `https://vsrsms.in` — a direct
contradiction that search engines resolve arbitrarily. Pinning the
root URL makes generated URLs, canonical tags and `og:url` all derive
from one configured value.

The guard skips a scheme-less `APP_URL` (a bare host or IP, which is
how a dev box can be configured) rather than force-rooting onto an
unusable URL.

`SitemapBuilder::CACHE_KEY` deliberately does **not** carry the host
alone — `cachedXml()` appends it, so changing `APP_URL` invalidates the
cached XML instead of serving the previous host for a full TTL.

## FSMs

None — the Seo kernel holds no mutable state and no lifecycle. Page
`state` values (`draft` / `published` / `completed`) belong to the
Campaigns and Events kernels and are validated at the FormRequest
boundary (Phase 4 doctrine). The kernel only READS entities that those
kernels have already decided are public.

## Tests

**Unit** — `tests/Unit/Seo/`
- `SeoMetaBuilderTest.php` — tag shape: the Open Graph quartet,
  canonical/`og:url` agreement, absolute-URL promotion for
  `og:image`, `noindex` only when requested, homepage title without a
  suffix, pre-encoded JSON-LD, and the no-canonical-when-no-host
  guard.
- `SitemapBuilderTest.php` — URL shaping, `lastmod` rules, XML
  escaping. *(moved here from `tests/Unit/Cms/`.)*

**Feature** — `tests/Feature/Seo/`
- `HeadTagsTest.php` — the acceptance test for this pass. Asserts that
  a plain `GET /campaigns`, with no JavaScript executed, returns
  `og:title` / `og:description` / `og:url` / `og:site_name` /
  `description` / `canonical` / `twitter:card` in the initial HTML, and
  that `/donate/cancel` is `noindex`.
- `SitemapTest.php` — routing, content type, slug inclusion.
- `RobotsTest.php` — static body, no mocks.

**HTTP controllers** — `app/Http/Controllers/Public/Seo/`
(`RobotsController`, `SitemapController`) with routes `/robots.txt`
(`seo.robots`) and `/sitemap.xml` (`seo.sitemap`) in `routes/web.php`.

## Doctrines

- **Link previews are the reason this kernel exists.** WhatsApp,
  Facebook (`facebookexternalhit`) and iMessage (Apple's unfurl
  service) never execute JavaScript. They `GET` a URL and read
  `<head>`. Tags authored in `<svelte:head>` are invisible to all
  three. This kernel is what makes them server-rendered.
- **One implementation, three scrapers.** The Open Graph quartet is a
  single set of tags that all three read. There is no per-platform
  SEO path in this codebase and there must never be one.
- **The backend is the single source of truth.** `SeoHead.svelte` still
  renders the same values on the client for exactly one reason: Inertia
  client-side navigations return JSON (they carry the `X-Inertia`
  header) and never re-render the Blade shell. It takes no props and
  authors nothing.
- **Controllers pass domain values, never tag arrays.** A controller
  calls `forPage(title: ..., description: ...)`; it must not construct
  `og:` keys.
