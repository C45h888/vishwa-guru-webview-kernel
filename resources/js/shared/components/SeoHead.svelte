<!--
    Shared SEO head — CLIENT half of the SEO surface.

    The BACKEND is the single source of truth. Every value rendered here
    comes from the `seo` Inertia prop built by
    `App\Seo\Services\SeoMetaBuilder` and already printed into <head> by
    `resources/views/app.blade.php` on the initial request.

    WHY THIS COMPONENT STILL EXISTS (AGENTS.md §"Pass 5: SEO Optimisation")

    Inertia client-side navigations return JSON (they carry the X-Inertia
    header), so the Blade root shell is never re-rendered when a visitor
    taps from the homepage to /campaigns. Without this component the
    browser tab title and the head would stay frozen on whatever the
    first full page load set.

    That is the ONLY job. This component does not author tags, does not
    format titles, does not build canonicals, and takes no props. The
    server-rendered tags and the tags below are built from the identical
    payload — including the pre-encoded JSON-LD string — so the two
    writers cannot drift.

    Trade-off, stated plainly: on the very first load the server has
    already written these tags, and this component writes the same ones
    again. That is intentional and harmless:
      - Every scraper (WhatsApp, Facebook, iMessage) and Google's
        first indexing pass read the server-rendered copy, which is
        first in document order.
      - Google ignores identical duplicate meta tags.
    The alternative — having Svelte adopt and mutate the server's nodes —
    adds a hydration-order dependency for no gain that the crawlers care
    about.

    Pages that render outside a controller context (error pages 403/404/
    500/503) deliberately do NOT use this component; they own their own
    <svelte:head>.
-->
<script lang="ts">
    import { page } from '@inertiajs/svelte';

    interface SeoTag {
        tag: string;
        attrs: Record<string, string>;
    }

    interface SeoPayload {
        title: string;
        tags: SeoTag[];
        jsonLd: Record<string, unknown> | null;
        jsonLdString: string | null;
    }

    const seo = $derived(
        ($page.props as { seo?: SeoPayload }).seo ??
            ({ title: '', tags: [], jsonLd: null, jsonLdString: null } as SeoPayload),
    );
</script>

<svelte:head>
    {#if seo.title}
        <title>{seo.title}</title>
    {/if}
    {#each seo.tags as tag, index (`${tag.tag}-${index}-${JSON.stringify(tag.attrs)}`)}
        {#if tag.tag === 'link'}
            <link
                rel={tag.attrs.rel}
                href={tag.attrs.href}
            />
        {:else}
            <meta
                name={tag.attrs.name}
                property={tag.attrs.property}
                content={tag.attrs.content}
            />
        {/if}
    {/each}
    {#if seo.jsonLdString}
        <script type="application/ld+json">{@html seo.jsonLdString}</script>
    {/if}
</svelte:head>
