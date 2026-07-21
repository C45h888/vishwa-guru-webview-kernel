<script lang="ts">
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '$shared/ui/card';
    import { Separator } from '$shared/ui/separator';
    import type { HomeEmptyProps } from '../types';

    let { appName, featuredCampaigns, featuredEvents, featuredGalleries }: HomeEmptyProps = $props();
</script>

<svelte:head>
    <title>Home — {appName}</title>
</svelte:head>

<PublicLayout>
    <section class="space-y-4">
        <h1 class="text-4xl font-semibold">Welcome to {appName}</h1>
        <Separator />
        <p class="text-sm text-muted-foreground">
            No published homepage yet. The site is being prepared.
        </p>
    </section>
    <section class="mt-12 grid grid-cols-1 gap-4 md:grid-cols-3">
        <Card>
            <CardHeader>
                <CardTitle>Campaigns</CardTitle>
                <CardDescription>{featuredCampaigns.length} featured</CardDescription>
            </CardHeader>
            <CardContent>
                <p class="text-sm text-muted-foreground">No campaigns yet.</p>
            </CardContent>
        </Card>
        <Card>
            <CardHeader>
                <CardTitle>Events</CardTitle>
                <CardDescription>{featuredEvents.length} upcoming</CardDescription>
            </CardHeader>
            <CardContent>
                <p class="text-sm text-muted-foreground">No events yet.</p>
            </CardContent>
        </Card>
        <Card>
            <CardHeader>
                <CardTitle>Gallery</CardTitle>
                <CardDescription>{featuredGalleries.length} published</CardDescription>
            </CardHeader>
            <CardContent>
                <p class="text-sm text-muted-foreground">No galleries yet.</p>
            </CardContent>
        </Card>
    </section>
</PublicLayout>
