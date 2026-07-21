<script lang="ts">
    import { formatMoney } from '$shared/lib/utils';

    let {
        amountMinor,
        currencyCode,
        class: className = '',
        ...rest
    }: {
        amountMinor: number | null | undefined;
        currencyCode: string | null | undefined;
        class?: string;
    } = $props();
</script>

<span class={className} {...rest}>{formatMoney(amountMinor, currencyCode)}</span>
