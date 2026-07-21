<script lang="ts">
    import type { Snippet } from 'svelte';
    import SiteHeader from './SiteHeader.svelte';
    import SiteFooter from './SiteFooter.svelte';

    interface Props {
        children: Snippet;
    }

    let { children }: Props = $props();
</script>

<div class="flex min-h-screen flex-col bg-background text-foreground">
    <SiteHeader />
    <main class="container flex-1 py-8">
        {@render children()}
    </main>
    <SiteFooter />
</div>
