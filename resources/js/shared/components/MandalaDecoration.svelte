<script lang="ts">
    import logoUrl from '$shared/assets/logo.jpeg?url';

    interface Props {
        size?: number;
        opacity?: number;
        tint?: 'gold' | 'none';
        class?: string;
    }

    let {
        size = 256,
        opacity = 1,
        tint = 'none',
        class: className = '',
    }: Props = $props();

    const goldFilter =
        'sepia(1) saturate(4) hue-rotate(-12deg) brightness(0.95)';
</script>

{#if logoUrl}
    <img
        src={logoUrl}
        alt=""
        aria-hidden="true"
        width={size}
        height={size}
        class={className}
        style:opacity={opacity}
        style:filter={tint === 'gold' ? goldFilter : 'none'}
        loading="lazy"
        decoding="async"
    />
{/if}