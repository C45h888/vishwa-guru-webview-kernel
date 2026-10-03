<script lang="ts">
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import BottomCtaBand from '$shared/components/BottomCtaBand.svelte';
    import { Separator } from '$shared/ui/separator';
    import {
        FileCheck,
        Landmark,
        ScrollText,
        Receipt,
        ShieldCheck,
        Mail,
        Sparkles,
    } from 'lucide-svelte';
    import { FALLBACK_LEGAL_PAGE_CONTENT } from './legal-fallbacks';
    import type { LegalPageProps } from './types';
    import SeoHead from '$shared/components/SeoHead.svelte';
    import type {
        LegalCertificateKey,
        LegalCertificateProps,
    } from '$shared/lib/inertia';

    let { page, legalContent, appName, appUrl }: LegalPageProps = $props();

    const content = $derived(
        legalContent === null ? FALLBACK_LEGAL_PAGE_CONTENT : legalContent,
    );

    // Icon map: certificate icon_key → lucide-svelte component.
    // Unknown keys fall back to Sparkles so the page never breaks on a
    // future new key.
    const certificateIcons: Record<
        LegalCertificateKey,
        typeof FileCheck
    > = {
        eighty_g: FileCheck,
        twelve_a: Landmark,
        poa: ScrollText,
        tan: Receipt,
    };

    // Subtle accent palette per card so the 4-up grid has visual
    // rhythm without being loud. Tints match the site's
    // primary/ivory/dark axis.
    const cardTints: Record<LegalCertificateKey, string> = {
        eighty_g: 'bg-primary/10 text-primary',
        twelve_a: 'bg-amber-100 text-amber-900',
        poa: 'bg-stone-200 text-stone-900',
        tan: 'bg-primary/10 text-primary',
    };

    function referenceLabel(cert: LegalCertificateProps): string {
        return cert.reference_number === null
            ? 'To be confirmed by the trust office'
            : cert.reference_number;
    }

    function referenceIsPlaceholder(cert: LegalCertificateProps): boolean {
        return cert.reference_number === null;
    }
</script>

<SeoHead />

<PublicLayout>
    <!-- ═══ PAGE TITLE STRIP (no hero — content-only page) ═══ -->
    <section class="border-b border-border/40 bg-background">
        <div class="container py-14 lg:py-20">
            <div class="mx-auto max-w-3xl space-y-5 text-center">
                <p
                    class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                >
                    Legal & Tax-Exempt Standing
                </p>
                <h1 class="font-serif text-3xl font-semibold lg:text-5xl">
                    {page.title}
                </h1>
                {#if page.meta_description}
                    <p
                        class="text-base leading-relaxed text-muted-foreground lg:text-lg"
                    >
                        {page.meta_description}
                    </p>
                {/if}
            </div>
        </div>
    </section>

    <!-- ═══ INTRO ═══ -->
    <section class="bg-muted/30">
        <div class="container py-14 lg:py-20">
            <div class="mx-auto max-w-3xl space-y-4 text-center">
                <p
                    class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                >
                    {content.intro.eyebrow}
                </p>
                <h2 class="font-serif text-2xl font-semibold lg:text-3xl">
                    {content.intro.title}
                </h2>
                <p class="text-base leading-relaxed text-muted-foreground lg:text-lg">
                    {content.intro.body}
                </p>
            </div>
        </div>
    </section>

    <!-- ═══ CERTIFICATE CARDS ═══ -->
    <section class="container py-16 lg:py-24">
        <div
            class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 lg:gap-8"
        >
            {#each content.certificates as cert (cert.certificate_key)}
                {@const Icon = certificateIcons[cert.icon_key] ?? Sparkles}
                {@const tint = cardTints[cert.icon_key] ?? 'bg-primary/10 text-primary'}
                <article
                    class="group flex h-full flex-col rounded-lg border border-border/40 bg-card p-7 transition hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md"
                >
                    <!-- Icon medallion -->
                    <div
                        class="mb-5 flex h-14 w-14 shrink-0 items-center justify-center rounded-full {tint}"
                        aria-hidden="true"
                    >
                        <Icon class="h-6 w-6" />
                    </div>

                    <!-- Certificate eyebrow title -->
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.18em] text-primary"
                    >
                        {cert.title}
                    </p>

                    <!-- Reference number (real value vs. placeholder) -->
                    <p
                        class="mt-3 font-mono text-xs leading-relaxed {referenceIsPlaceholder(
                            cert,
                        )
                            ? 'italic text-muted-foreground/80'
                            : 'break-all text-muted-foreground'}"
                    >
                        {referenceLabel(cert)}
                    </p>

                    <!-- Description -->
                    <p
                        class="mt-5 flex-1 text-sm leading-relaxed text-foreground/80"
                    >
                        {cert.description}
                    </p>

                    <!-- View document link -->
                    <a
                        href={`/legal/documents/${cert.certificate_key}`}
                        target="_blank"
                        rel="noopener"
                        class="mt-5 inline-flex items-center gap-2 self-start rounded-md border border-border/60 bg-background px-4 py-2 text-xs font-semibold uppercase tracking-[0.15em] text-primary transition hover:border-primary/40 hover:bg-primary/5"
                    >
                        View document
                        <svg
                            class="h-3.5 w-3.5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <path d="M14 3h7v7" />
                            <path d="M10 14L21 3" />
                            <path d="M21 14v7H3V3h7" />
                        </svg>
                    </a>
                </article>
            {/each}
        </div>
    </section>

    <Separator />

    <!-- ═══ CERTIFIED COPIES STRIP ═══ -->
    <section class="bg-muted/30">
        <div class="container py-14 lg:py-20">
            <div
                class="mx-auto flex max-w-3xl flex-col items-center gap-5 text-center sm:flex-row sm:gap-6 sm:text-left"
            >
                <div
                    class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full border border-primary/30 bg-primary/5 text-primary"
                    aria-hidden="true"
                >
                    <ShieldCheck class="h-6 w-6" />
                </div>
                <div class="space-y-2">
                    <h3 class="font-serif text-xl font-semibold lg:text-2xl">
                        Need a certified copy?
                    </h3>
                    <p
                        class="text-sm leading-relaxed text-muted-foreground lg:text-base"
                    >
                        Donors and auditors who need a certified copy of any
                        certificate, or a signed statement of compliance,
                        should write to the trust office. The trust office
                        replies within five working days.
                    </p>
                    <a
                        href="/contact"
                        class="inline-flex items-center gap-2 pt-1 text-sm font-semibold text-primary underline-offset-4 hover:underline"
                    >
                        <Mail class="h-4 w-4" aria-hidden="true" />
                        Contact the trust office
                    </a>
                </div>
            </div>
        </div>
    </section>

    <BottomCtaBand />
</PublicLayout>
