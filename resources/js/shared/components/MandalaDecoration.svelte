<script lang="ts">
    import mandalaUrl from '$shared/assets/mandala.svg?url';

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

{#if mandalaUrl}
    <img
        src={mandalaUrl}
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