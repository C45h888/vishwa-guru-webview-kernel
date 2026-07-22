<script lang="ts">
    import type { Snippet } from 'svelte';
    import SiteHeader from './SiteHeader.svelte';
    import SiteFooter from './SiteFooter.svelte';

    interface Props {
        children: Snippet;
        appName?: string;
        appUrl?: string;
    }

    let { children, appName = '', appUrl = '' }: Props = $props();
</script>

<div class="flex min-h-screen flex-col bg-background text-foreground">
    <SiteHeader />
    <main class="flex-1">
        {@render children()}
    </main>
    <SiteFooter {appName} {appUrl} />
</div>