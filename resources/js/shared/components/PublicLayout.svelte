<script lang="ts">
    import type { Snippet } from 'svelte';
    import { page } from '@inertiajs/svelte';
    import SiteHeader from './SiteHeader.svelte';
    import SiteFooter from './SiteFooter.svelte';

    interface Props {
        children: Snippet;
        appName?: string;
        appUrl?: string;
    }

    let { children, appName = '', appUrl = '' }: Props = $props();

    const resolvedAppName = $derived(appName || $page.props.appName || '');
    const resolvedAppUrl = $derived(appUrl || $page.props.appUrl || '');
</script>

<div class="flex min-h-screen flex-col bg-white text-foreground">
    <SiteHeader appName={resolvedAppName} />
    <main class="flex-1">
        {@render children()}
    </main>
    <SiteFooter appName={resolvedAppName} appUrl={resolvedAppUrl} />
</div>
