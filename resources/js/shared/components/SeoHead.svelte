<!--
    Shared SEO head — the ONLY place head tags are authored.

    Every public page renders <SeoHead> instead of hand-writing
    <svelte:head> blocks, so title format (`{title} — {appName}`),
    canonical construction, OG/Twitter tags, and noindex behavior
    stay uniform. Backend supplies the data (title/description/image
    arrive as Inertia props per the backend-first doctrine); this
    component only presents them.

    Canonical = appUrl (config('app.url') = https://vsrsms.in) +
    the current Inertia path with query/hash stripped. Tags that need
    an absolute host (canonical, og:url) are skipped when appUrl is
    absent rather than guessed.
-->
<script lang="ts">
    import { page } from '@inertiajs/svelte';

    interface Props {
        /** Bare page title — the component appends ` — {appName}`. */
        title: string;
        appName: string;
        description?: string | null;
        /** Absolute image URL (cover/banner). Skipped when absent. */
        image?: string | null;
        imageAlt?: string | null;
        type?: 'website' | 'article';
        /** Transactional pages (receipt/success/cancel) set this. */
        noindex?: boolean;
        /** Canonical host. Skipped when absent. */
        appUrl?: string | null;
        /** JSON-LD object, serialised into a ld+json script tag. */
        jsonLd?: Record<string, unknown> | null;
    }

    let {
        title,
        appName,
        description = null,
        image = null,
        imageAlt = null,
        type = 'website',
        noindex = false,
        appUrl = null,
        jsonLd = null,
    }: Props = $props();

    const fullTitle = $derived(`${title} — ${appName}`);
    const path = $derived(($page.url.split('?')[0] ?? '').split('#')[0] || '/');
    const canonical = $derived(
        appUrl ? `${appUrl.replace(/\/$/, '')}${path.startsWith('/') ? path : `/${path}`}` : null,
    );
    const jsonLdString = $derived(jsonLd ? JSON.stringify(jsonLd) : null);
</script>

<svelte:head>
    <title>{fullTitle}</title>
    {#if description}
        <meta name="description" content={description} />
    {/if}
    {#if noindex}
        <meta name="robots" content="noindex, nofollow" />
    {/if}
    {#if canonical}
        <link rel="canonical" href={canonical} />
    {/if}
    <meta property="og:site_name" content={appName} />
    <meta property="og:type" content={type} />
    <meta property="og:title" content={fullTitle} />
    {#if description}
        <meta property="og:description" content={description} />
    {/if}
    {#if canonical}
        <meta property="og:url" content={canonical} />
    {/if}
    {#if image}
        <meta property="og:image" content={image} />
        {#if imageAlt}
            <meta property="og:image:alt" content={imageAlt} />
        {/if}
    {/if}
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content={fullTitle} />
    {#if description}
        <meta name="twitter:description" content={description} />
    {/if}
    {#if image}
        <meta name="twitter:image" content={image} />
    {/if}
    {#if jsonLdString}
        <script type="application/ld+json">{@html jsonLdString}</script>
    {/if}
</svelte:head>
