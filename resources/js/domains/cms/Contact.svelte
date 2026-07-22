<script lang="ts">
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import TrustBadgeRow from '$shared/components/TrustBadgeRow.svelte';
    import BottomCtaBand from '$shared/components/BottomCtaBand.svelte';
    import { Card, CardContent } from '$shared/ui/card';
    import {
        Mail,
        Phone,
        MapPin,
        Link as LinkIcon,
    } from 'lucide-svelte';
    import type { ContactPageProps } from './types';

    let { contactPoints, appName }: ContactPageProps = $props();

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
    <section class="container py-14 lg:py-20">
        <div class="mx-auto max-w-3xl space-y-5 text-center">
            <h1 class="font-serif text-4xl font-semibold lg:text-5xl">
                Contact
            </h1>
            <p class="text-base text-muted-foreground lg:text-lg">
                Reach the temple office for seva bookings, donation
                enquiries, and general questions.
            </p>
            <div class="pt-1">
                <TrustBadgeRow />
            </div>
        </div>
    </section>

    <section class="container pb-16 lg:pb-20">
        {#if contactPoints.length === 0}
            <div
                class="mx-auto max-w-xl rounded-md border border-dashed border-border bg-muted/30 p-8 text-center"
            >
                <p class="text-sm text-muted-foreground">
                    Contact information will be published soon. For urgent
                    matters, please email
                    <a
                        href="mailto:admin@temple-trust.example"
                        class="font-medium text-primary hover:underline"
                    >
                        admin@temple-trust.example
                    </a>.
                </p>
            </div>
        {:else}
            <div class="grid grid-cols-1 gap-10 lg:grid-cols-3">
                <aside class="space-y-4 lg:col-span-1">
                    <h2 class="font-serif text-xl font-semibold">
                        Office hours
                    </h2>
                    <div class="space-y-1 text-sm text-muted-foreground">
                        <p>Morning: 9:00 AM – 1:00 PM</p>
                        <p>Evening: 4:00 PM – 8:00 PM</p>
                    </div>

                    <div
                        class="aspect-video overflow-hidden rounded-md border border-border bg-muted/40"
                        aria-label="Map placeholder"
                    >
                        <div
                            class="flex h-full items-center justify-center text-xs text-muted-foreground"
                        >
                            Map will appear here
                        </div>
                    </div>
                </aside>

                <div class="space-y-8 lg:col-span-2">
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
        title="Plan a visit"
        body="Come experience the temple in person. We welcome all devotees."
        ctaLabel="View Events"
        ctaHref="/events"
    />
</PublicLayout>