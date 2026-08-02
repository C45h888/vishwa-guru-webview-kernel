<script lang="ts">
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import MandalaDecoration from '$shared/components/MandalaDecoration.svelte';
    import { Card, CardContent } from '$shared/ui/card';
    import { Button } from '$shared/ui/button';
    import { XCircle } from 'lucide-svelte';
    import type { AppPageProps } from '$shared/lib/inertia';

    let { appName }: AppPageProps = $props();
</script>

<svelte:head>
    <title>Donation cancelled — {appName}</title>
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
            <div class="mx-auto max-w-xl space-y-6 text-center">
                <div
                    class="mx-auto flex h-14 w-14 items-center justify-center rounded-full border border-border/60 bg-ivory text-muted-foreground"
                >
                    <XCircle class="h-7 w-7" aria-hidden="true" />
                </div>

                <div class="space-y-2">
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                    >
                        No charge made
                    </p>
                    <h1
                        class="font-serif text-3xl font-semibold lg:text-4xl"
                    >
                        Donation cancelled
                    </h1>
                    <p class="text-base text-muted-foreground">
                        No payment was processed. Your card was not charged.
                    </p>
                </div>

                <Card>
                    <CardContent class="space-y-4 p-6 text-sm">
                        <p class="text-muted-foreground">
                            You can try again with the same or a different
                            cause, or browse our campaigns to find one that
                            resonates.
                        </p>
                        <div
                            class="flex flex-wrap items-center justify-center gap-3 pt-2"
                        >
                            <Button href="/donate">Try again</Button>
                            <Button href="/campaigns" variant="outline">
                                Browse campaigns
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </section>
</PublicLayout>
