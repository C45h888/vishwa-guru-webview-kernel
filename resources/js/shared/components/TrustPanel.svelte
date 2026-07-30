<script lang="ts">
    import {
        Card,
        CardHeader,
        CardTitle,
        CardContent,
    } from '$shared/ui/card';
    import { Badge } from '$shared/ui/badge';
    import { Separator } from '$shared/ui/separator';
    import MandalaDecoration from './MandalaDecoration.svelte';
    import type { HomepageTrustPanelProps } from '$shared/lib/inertia';

    let { content }: { content: HomepageTrustPanelProps } = $props();
</script>

<section class="relative overflow-hidden border-y border-border/30 bg-ivory">
    <div
        class="pointer-events-none absolute -right-24 top-1/2 -translate-y-1/2 opacity-15"
        aria-hidden="true"
    >
        <MandalaDecoration size={420} tint="gold" />
    </div>

    <div class="container relative py-20 lg:py-28">
        <div class="mx-auto max-w-3xl space-y-4 text-center">
            <p
                class="text-xs font-semibold uppercase tracking-[0.2em] text-primary"
            >
                {content.eyebrow}
            </p>
            <h2 class="font-serif text-3xl font-semibold lg:text-4xl">
                {content.title}
            </h2>
        </div>

        <div class="mx-auto mt-12 grid max-w-5xl gap-6 md:grid-cols-2">
            <Card>
                <CardHeader class="space-y-2">
                    <Badge variant="secondary" class="w-fit">Registration</Badge>
                    <CardTitle class="text-lg">Registered charitable trust</CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-sm leading-relaxed text-muted-foreground">
                        {content.registration}
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="space-y-2">
                    <Badge variant="secondary" class="w-fit">Tax status</Badge>
                    <CardTitle class="text-lg">Donation documentation</CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-sm leading-relaxed text-muted-foreground">
                        {content.tax_status}
                    </p>
                </CardContent>
            </Card>
        </div>

        <div class="mx-auto mt-12 grid max-w-5xl gap-10 md:grid-cols-2">
            <div class="space-y-4">
                <h3
                    class="font-serif text-xs font-semibold uppercase tracking-[0.2em] text-foreground/70"
                >
                    Operating principles
                </h3>
                <Separator class="bg-primary/20" />
                <ul class="space-y-3">
                    {#each content.operating_principles as principle, index (index)}
                        <li class="flex items-start gap-3 text-sm leading-relaxed text-foreground/90">
                            <span
                                class="mt-1.5 inline-block h-1.5 w-1.5 shrink-0 rounded-full bg-primary/70"
                                aria-hidden="true"
                            ></span>
                            <span>{principle}</span>
                        </li>
                    {/each}
                </ul>
            </div>

            <div class="space-y-4">
                <h3
                    class="font-serif text-xs font-semibold uppercase tracking-[0.2em] text-foreground/70"
                >
                    Our vows
                </h3>
                <Separator class="bg-primary/20" />
                <ul class="space-y-3">
                    {#each content.vows as vow, index (index)}
                        <li class="flex items-start gap-3 text-sm leading-relaxed text-foreground/90">
                            <span
                                class="mt-1.5 inline-block h-1.5 w-1.5 shrink-0 rounded-full bg-primary/70"
                                aria-hidden="true"
                            ></span>
                            <span>{vow}</span>
                        </li>
                    {/each}
                </ul>
            </div>
        </div>
    </div>
</section>
