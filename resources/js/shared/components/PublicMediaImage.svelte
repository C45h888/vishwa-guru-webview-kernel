<script lang="ts">
    /**
     * PublicMediaImage — typed wrapper around <img> for files served via
     * the `/media/{id}` route. Centralises the loading/decoding/fetchpriority
     * defaults so every public surface agrees.
     *
     * Loading doctrine:
     *   - `loading="eager"` is opt-in — the LCP element of each page
     *     (campaign hero, event hero, etc.) sets it.
     *   - `fetchpriority="high"` is also opt-in and pairs with `eager`
     *     to tell the browser this image should be preloaded.
     *   - `decoding="async"` is always-on so large images don't block
     *     paint, even when they're the LCP element.
     */
    import type { PublicMediaProps } from '$shared/lib/inertia';

    let {
        media,
        alt = media.alt_text ?? '',
        loading = 'lazy',
        fetchpriority,
        class: className = '',
    }: {
        media: PublicMediaProps;
        alt?: string;
        loading?: 'lazy' | 'eager';
        fetchpriority?: 'high' | 'low' | 'auto';
        class?: string;
    } = $props();
</script>

<img
    src={media.url}
    {alt}
    width={media.width ?? undefined}
    height={media.height ?? undefined}
    {loading}
    {fetchpriority}
    decoding="async"
    class={className}
/>
