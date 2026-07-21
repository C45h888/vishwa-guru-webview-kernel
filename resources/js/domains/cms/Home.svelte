<script lang="ts">
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '$shared/ui/card';
    import { Separator } from '$shared/ui/separator';
    import type { HomePageProps } from '../types';

    let {
        page,
        heroBanners,
        resolvedReferences,
        html,
        resolvedAt,
        featuredCampaigns,
        featuredEvents,
        featuredGalleries,
        appName,
    }: HomePageProps = $props();
</script>

<svelte:head>
    <title>{page.title} — {appName}</title>
    {#if page.meta_description}
        <meta name="description" content={page.meta_description} />
    {/if}
</svelte:head>

<PublicLayout>
    <section aria-labelledby="hero-title" class="space-y-4">
        <h1 id="hero-title" class="text-4xl font-semibold">{page.title}</h1>
        <Separator />
        <div class="prose max-w-none">{@html html}</div>
        <p class="text-xs text-muted-foreground">
            Resolved at {resolvedAt} · {heroBanners.length} hero banners · {resolvedReferences.length} references
        </p>
    </section>

    <section aria-labelledby="features-title" class="mt-12 space-y-4">
        <h2 id="features-title" class="text-2xl font-semibold">What you can do</h2>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <Card>
                <CardHeader>
                    <CardTitle>Campaigns</CardTitle>
                    <CardDescription>{featuredCampaigns.length} featured</CardDescription>
                </CardHeader>
                <CardContent>
                    {#if featuredCampaigns[0]}
                        <p class="text-sm">Latest: {featuredCampaigns[0].title}</p>
                    {:else}
                        <p class="text-sm text-muted-foreground">No featured campaigns yet.</p>
                    {/if}
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle>Events</CardTitle>
                    <CardDescription>{featuredEvents.length} upcoming</CardDescription>
                </CardHeader>
                <CardContent>
                    {#if featuredEvents[0]}
                        <p class="text-sm">Next: {featuredEvents[0].title}</p>
                    {:else}
                        <p class="text-sm text-muted-foreground">No upcoming events yet.</p>
                    {/if}
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle>Gallery</CardTitle>
                    <CardDescription>{featuredGalleries.length} published</CardDescription>
                </CardHeader>
                <CardContent>
                    {#if featuredGalleries[0]}
                        <p class="text-sm">Featured: {featuredGalleries[0].title}</p>
                    {:else}
                        <p class="text-sm text-muted-foreground">No galleries yet.</p>
                    {/if}
                </CardContent>
            </Card>
        </div>
    </section>
</PublicLayout>
