<script lang="ts">
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import TrustBadgeRow from '$shared/components/TrustBadgeRow.svelte';
    import BottomCtaBand from '$shared/components/BottomCtaBand.svelte';
    import MandalaDecoration from '$shared/components/MandalaDecoration.svelte';
    import { Card, CardContent } from '$shared/ui/card';
    import {
        Mail,
        Phone,
        MapPin,
        Link as LinkIcon,
        MessageCircle,
    } from 'lucide-svelte';
    import type { ContactPageProps } from './types';

    let {
        contactPoints,
        mapEmbedUrl,
        mapAddress,
        mapOpenUrl,
        appName,
    }: ContactPageProps = $props();

    // WhatsApp link — wa.me takes the number with the leading + stripped
    // and the country code prefixed. +91 98441 32318 → 919844132318.
    const WHATSAPP_NUMBER = '919844132318';
    const WHATSAPP_URL = `https://wa.me/${WHATSAPP_NUMBER}`;
    const WHATSAPP_DISPLAY = '+91 98441 32318';

    function iconFor(type: string) {
        const t = type.toLowerCase();
        if (t === 'email') return Mail;
        if (t === 'phone') return Phone;
        if (t === 'address') return MapPin;
        return LinkIcon;
    }

    function linkFor(type: string, value: string) {
        const t = type.toLowerCase();
        if (t === 'email') return `mailto:${value}`;
        if (t === 'phone') return `tel:${value.replace(/\s+/g, '')}`;
        if (t === 'url') return value;
        return null;
    }

    // "Open in Bing Maps" link (the human-facing directions page on
    // bing.com, not the iframe). The consumer site is X-Frame-Options
    // locked, but works fine when opened in a new tab. Provided by the
    // controller as `mapOpenUrl`; we fall back to building a Bing URL
    // from the address if missing for older server payloads.
    const fallbackOpenUrl = $derived(
        mapAddress
            ? `https://www.bing.com/maps?q=${encodeURIComponent(mapAddress)}`
            : null,
    );
    const mapsOpenUrl = $derived(mapOpenUrl ?? fallbackOpenUrl);

    const grouped = $derived.by(() => {
        const map = new Map<string, typeof contactPoints>();
        for (const p of contactPoints) {
            if (!map.has(p.contact_type)) map.set(p.contact_type, []);
            map.get(p.contact_type)!.push(p);
        }
        return Array.from(map.entries());
    });
</script>

<svelte:head>
    <title>Contact — {appName}</title>
</svelte:head>

<PublicLayout>
    <section class="relative overflow-hidden bg-background">
        <div
            class="pointer-events-none absolute -right-20 top-0 opacity-[0.10]"
            aria-hidden="true"
        >
            <MandalaDecoration size={320} tint="gold" />
        </div>
        <div class="container relative py-16 lg:py-24">
            <div class="mx-auto max-w-3xl space-y-5 text-center">
                <p
                    class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                >
                    Get in touch
                </p>
                <h1 class="font-serif text-4xl font-semibold lg:text-5xl">
                    Contact the trust
                </h1>
                <p class="text-base text-muted-foreground lg:text-lg">
                    Reach the trust office for donations, events, CSR
                    enquiries, project updates, and future volunteering
                    or support.
                </p>
                <div class="pt-2">
                    <TrustBadgeRow />
                </div>
            </div>
        </div>
    </section>

    <section class="container py-8 lg:py-10">
        <a
            href={WHATSAPP_URL}
            target="_blank"
            rel="noopener noreferrer"
            class="group flex items-center justify-between gap-4 rounded-md bg-[#25D366] px-5 py-4 text-white shadow-sm transition-all hover:bg-[#1ebe5d] hover:shadow-md sm:px-7 sm:py-5"
        >
            <div class="flex items-center gap-4">
                <span
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-white/15"
                >
                    <MessageCircle class="h-5 w-5" aria-hidden="true" />
                </span>
                <div class="space-y-0.5">
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.2em] text-white/85"
                    >
                        Chat with us
                    </p>
                    <p class="font-serif text-lg font-semibold sm:text-xl">
                        WhatsApp the temple office
                    </p>
                </div>
            </div>
            <div class="hidden flex-col items-end sm:flex">
                <span class="text-sm font-medium">{WHATSAPP_DISPLAY}</span>
                <span
                    class="text-xs text-white/80 transition-transform group-hover:translate-x-0.5"
                    aria-hidden="true"
                >
                    Open chat →
                </span>
            </div>
        </a>
    </section>

    <section class="container pb-16 lg:pb-24">
        {#if contactPoints.length === 0}
            <div
                class="mx-auto max-w-xl rounded-md border border-dashed border-border bg-ivory/60 p-8 text-center"
            >
                <p class="text-sm text-muted-foreground">
                    Contact details will be published by the trust
                    office shortly. In the meantime, you can reach the
                    office on +91 98441 32318, and a response will be
                    arranged.
                </p>
            </div>
        {:else}
            <div class="grid grid-cols-1 gap-10 lg:grid-cols-12 lg:gap-16">
                <aside class="space-y-5 lg:col-span-4">
                    <div class="space-y-3">
                        <p
                            class="text-xs font-semibold uppercase tracking-[0.2em] text-primary"
                        >
                            Visit
                        </p>
                        <h2 class="font-serif text-2xl font-semibold">
                            Office hours
                        </h2>
                        <div class="space-y-1 text-sm text-muted-foreground">
                            <p>Office hours published by the temple office</p>
                        </div>
                    </div>

                    <!--
                        Map slot. Embeds Google Maps when the
                        contact_information table has an `address` row;
                        otherwise degrades to the original placeholder.
                        The iframe is sandboxed, lazy-loaded, and
                        has a labelled title for screen readers. A
                        "Open in Google Maps" link sits below it so
                        visitors on browsers that block third-party
                        iframes can still navigate to the address.
                    -->
                    <div
                        class="aspect-[3/2] overflow-hidden rounded-md border border-border/40 bg-ivory"
                        aria-label={mapAddress
                            ? `Map of ${mapAddress}`
                            : 'Map placeholder'}
                    >
                        {#if mapEmbedUrl}
                            <iframe
                                src={mapEmbedUrl}
                                title={`Map of ${mapAddress ?? 'temple office'}`}
                                class="h-full w-full border-0"
                                loading="lazy"
                                referrerpolicy="no-referrer-when-downgrade"
                                allowfullscreen
                            ></iframe>
                        {:else}
                            <div
                                class="flex h-full flex-col items-center justify-center gap-1 text-xs text-muted-foreground"
                            >
                                <MapPin class="h-5 w-5 text-primary/60" aria-hidden="true" />
                                <span>Map will appear here</span>
                            </div>
                        {/if}
                    </div>

                    {#if mapsOpenUrl && mapAddress}
                        <a
                            href={mapsOpenUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1.5 text-xs font-medium text-primary hover:underline"
                        >
                            <MapPin class="h-3.5 w-3.5" aria-hidden="true" />
                            Open in Bing Maps
                        </a>
                    {/if}
                </aside>

                <div class="space-y-10 lg:col-span-8">
                    {#each grouped as [type, points] (type)}
                        <div class="space-y-3">
                            <h2
                                class="font-serif text-xl font-semibold capitalize"
                            >
                                {type}
                            </h2>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                {#each points as p (p.id)}
                                    {@const Icon = iconFor(p.contact_type)}
                                    {@const href = linkFor(
                                        p.contact_type,
                                        p.value,
                                    )}
                                    <Card>
                                        <CardContent
                                            class="flex items-start gap-3 p-4"
                                        >
                                            <div
                                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                                            >
                                                <Icon class="h-4 w-4" />
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div
                                                    class="flex items-center gap-2"
                                                >
                                                    <span
                                                        class="text-sm font-medium"
                                                    >
                                                        {p.label}
                                                    </span>
                                                    {#if p.is_primary}
                                                        <span
                                                            class="rounded-full bg-secondary px-2 py-0.5 text-[10px] uppercase tracking-wide text-secondary-foreground"
                                                        >
                                                            Primary
                                                        </span>
                                                    {/if}
                                                </div>
                                                {#if href}
                                                    <a
                                                        href={href}
                                                        class="mt-1 block break-words text-sm text-primary hover:underline"
                                                    >
                                                        {p.value}
                                                    </a>
                                                {:else}
                                                    <p
                                                        class="mt-1 break-words text-sm text-foreground"
                                                    >
                                                        {p.value}
                                                    </p>
                                                {/if}
                                            </div>
                                        </CardContent>
                                    </Card>
                                {/each}
                            </div>
                        </div>
                    {/each}
                </div>
            </div>
        {/if}
    </section>

    <BottomCtaBand
        title="Visit the trust"
        body="The trust office is near Nanjangud, outside Mysore. Visitors are welcome to coordinate a visit through the office in advance."
        ctaLabel="See upcoming events"
        ctaHref="/events"
    />
</PublicLayout>
