<script lang="ts">
    import { cn } from '$shared/lib/utils';
    import { ChevronDown } from 'lucide-svelte';
    import {
        PHONE_COUNTRIES,
        countryByCode,
        formatNational,
        onlyDigits,
        stripTrunkZero,
        validatePhone,
        type PhoneCountry,
        type PhoneValidity,
    } from '$shared/lib/phone';

    /**
     * PhoneInput — India-first phone field for the donation form.
     *
     * Gives the modern "country code + auto-format + inline validation" UX
     * while staying a thin, self-contained wrapper:
     *   - Binds `value`   = the national digits only (no dial prefix).
     *   - Binds `country` = ISO 3166-1 alpha-2 code (default "IN").
     *   - Calls `validate(v)` whenever the number's validity changes so the
     *     parent can gate submission and show inline help.
     *
     * The payload string is assembled by the parent via `toE164()` from
     * $shared/lib/phone — this component only renders and formats.
     *
     * No new dependency: the country selector is a native <select> (matches
     * the campaign <select> already in Donate.svelte).
     */

    type Props = {
        value?: string;
        country?: string;
        label?: string;
        required?: boolean;
        disabled?: boolean;
        id?: string;
        /** Called with the current validity on mount and on every change. */
        validate?: (v: PhoneValidity) => void;
        /** External error (e.g. a server 422) to surface below the field. */
        error?: string | null;
    };

    let {
        value = $bindable(''),
        country = $bindable('IN'),
        label = 'Phone',
        required = false,
        disabled = false,
        id = 'phone',
        validate,
        error = null,
    }: Props = $props();

    const selected = $derived<PhoneCountry>(countryByCode(country));
    const national = $derived(onlyDigits(value));

    // The valid national number (trunk-0 stripped where the country dials
    // with a leading zero inside the country) — used for grouping display
    // and validity. India: 09876543210 -> 9876543210 national.
    const nationalCanonical = $derived(stripTrunkZero(national));

    // Report validity to the parent whenever country/digits change.
    $effect(() => {
        validate?.(validatePhone(selected, nationalCanonical));
    });

    function onInput(event: Event): void {
        const input = event.currentTarget as HTMLInputElement;
        const digits = onlyDigits(input.value);
        value = digits;
        input.value = formatNational(selected, stripTrunkZero(digits));
    }

    function onCountryChange(event: Event): void {
        const select = event.currentTarget as HTMLSelectElement;
        country = select.value;
    }

    const displayValue = $derived(formatNational(selected, nationalCanonical));
</script>

<div class="space-y-2">
    {#if label}
        <label
            for={id}
            class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
            >{label}</label
        >
    {/if}

    <div
        class="flex h-10 w-full items-center overflow-hidden rounded-md border border-input bg-background text-sm focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2 {error
            ? 'border-destructive'
            : ''}"
    >
        <div class="relative flex shrink-0 items-center gap-1 border-r border-input bg-muted/40 px-3">
            <span aria-hidden="true" class="text-base leading-none">{selected.flag}</span>
            <span class="text-sm text-muted-foreground">{selected.dial}</span>
            <select
                aria-label="Country code"
                value={country}
                onchange={onCountryChange}
                disabled={disabled}
                class="absolute inset-0 h-full w-full cursor-pointer appearance-none opacity-0"
            >
                {#each PHONE_COUNTRIES as c (c.code)}
                    <option value={c.code}>
                        {c.name} ({c.dial})
                    </option>
                {/each}
            </select>
            <ChevronDown class="pointer-events-none h-3.5 w-3.5 text-muted-foreground" aria-hidden="true" />
        </div>

        <input
            {id}
            type="tel"
            autocomplete="tel-national"
            maxlength="20"
            value={displayValue}
            oninput={onInput}
            disabled={disabled}
            required={required}
            aria-invalid={error ? 'true' : undefined}
            placeholder="98765 43210"
            class={cn(
                'h-full min-w-0 flex-1 bg-transparent px-3 py-2 outline-none placeholder:text-muted-foreground',
                'disabled:cursor-not-allowed disabled:opacity-50'
            )}
        />
    </div>

    {#if error}
        <p id={`${id}-error`} class="text-xs text-destructive">{error}</p>
    {/if}
</div>