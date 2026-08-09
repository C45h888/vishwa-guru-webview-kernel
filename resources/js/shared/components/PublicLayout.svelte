<script lang="ts">
    import type { Snippet } from 'svelte';
    import { page } from '@inertiajs/svelte';
    import SiteHeader from './SiteHeader.svelte';
    import SiteFooter from './SiteFooter.svelte';

    interface Props {
        children: Snippet;
        appName?: string;
        appShortName?: string;
        appUrl?: string;
    }

    let { children, appName = '', appShortName = '', appUrl = '' }: Props = $props();

    // PublicLayout forwards both the full legal name (appName) and the
    // short brand identifier (appShortName) to the header/footer chrome.
    // The header uses the short form for legibility; the footer and body
    // copy keep using the full name so the canonical trust identity
    // remains visible everywhere except the small top bar.
    const resolvedAppName = $derived(appName || $page.props.appName || '');
    const resolvedAppShortName = $derived(
        appShortName || $page.props.appShortName || 'VSRSMS'
    );
    const resolvedAppUrl = $derived(appUrl || $page.props.appUrl || '');
</script>

<div class="flex min-h-screen flex-col bg-white text-foreground">
    <SiteHeader appShortName={resolvedAppShortName} />
    <main class="flex-1">
        {@render children()}
    </main>
    <SiteFooter appName={resolvedAppName} appUrl={resolvedAppUrl} />
</div>
