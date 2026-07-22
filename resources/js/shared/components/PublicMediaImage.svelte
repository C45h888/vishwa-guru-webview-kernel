<script lang="ts">
    import type { PublicMediaProps } from '$shared/lib/inertia';

    let {
        media,
        alt = media.alt_text ?? '',
        loading = 'lazy',
        class: className = '',
    }: {
        media: PublicMediaProps;
        alt?: string;
        loading?: 'lazy' | 'eager';
        class?: string;
    } = $props();
</script>

<img
    src={media.url}
    {alt}
    width={media.width ?? undefined}
    height={media.height ?? undefined}
    {loading}
    decoding="async"
    class={className}
/>