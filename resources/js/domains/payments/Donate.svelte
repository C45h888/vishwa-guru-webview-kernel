<script lang="ts">
    import { router, page } from '@inertiajs/svelte';
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import MandalaDecoration from '$shared/components/MandalaDecoration.svelte';
    import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '$shared/ui/card';
    import { Button } from '$shared/ui/button';
    import { Input } from '$shared/ui/input';
    import { Label } from '$shared/ui/label';
    import { Alert } from '$shared/ui/alert';
    import { ArrowLeft, Heart, Shield } from 'lucide-svelte';
    import type {
        CampaignSummaryProps,
        AppPageProps,
    } from '$shared/lib/inertia';
    import { openRazorpayCheckout } from '$shared/lib/razorpay';
    import type { RazorpaySuccessResponse } from '$shared/lib/razorpay';
    import { toE164, countryByCode } from '$shared/lib/phone';
    import {
        validateEmail,
        emailErrorMessage,
        normalizePan,
        validatePan,
        panErrorMessage,
    } from '$shared/lib/validate';
    import SeoHead from '$shared/components/SeoHead.svelte';
    import PhoneInput from '$shared/ui/phone-input/PhoneInput.svelte';

    // Razorpay environment: shared via HandleInertiaRequests. When 'test',
    // the donate flow surfaces a "Test mode" badge so anyone in the
    // sandbox knows not to use real card details. Production strips it.
    const isTestMode = $derived($page.props.razorpayMode === 'test');

    let {
        campaigns,
        defaultCurrency,
        preselectSlug,
        preselectAmountRupees,
        preselectRecurring,
        preselectAnonymous,
        eightyGThresholdMinor,
        termsPolicyVersion,
        privacyPolicyVersion,
        appName,
        appUrl,
    }: AppPageProps<{
        campaigns: CampaignSummaryProps[];
        defaultCurrency: string;
        preselectSlug: string | null;
        preselectAmountRupees: string | null;
        preselectRecurring: string | null;
        preselectAnonymous: boolean;
        /** 80G certificate threshold in minor units (paise), from backend config. */
        eightyGThresholdMinor: number;
        termsPolicyVersion: string;
        privacyPolicyVersion: string;
    }> = $props();

    let selectedCampaign = $state<string>(
        campaigns.find((campaign) => campaign.slug === preselectSlug)?.slug
            ?? campaigns[0]?.slug
            ?? '',
    );
    let amountRupees = $state<string>(preselectAmountRupees ?? '1000');
    let isAnonymous = $state<boolean>(preselectAnonymous);
    let isRecurring = $state<string>(preselectRecurring ?? '');
    let donorName = $state<string>('');
    let donorEmail = $state<string>('');
    let donorPhone = $state<string>('');
    let donorPhoneCountry = $state<string>('IN');
    let donorPan = $state<string>('');
    let donorAddressLine1 = $state<string>('');
    let donorAddressLine2 = $state<string>('');
    let donorCity = $state<string>('');
    let donorState = $state<string>('');
    let donorPincode = $state<string>('');
    let donorCountry = $state<string>('India');
    let purpose = $state<string>('');
    let donorMessage = $state<string>('');

    // PAN and address fields surface when the amount exceeds the 80G
    // certificate threshold (backend config: default ₹500) AND the donor is
    // identified. The threshold is passed from the backend so the UI cannot
    // drift from Receipt80GValidator. Anonymous donors never need PAN/address.
    const eightyGThresholdRupees = $derived(eightyGThresholdMinor / 100);
    const showEightyGFields = $derived(
        !isAnonymous && parseFloat(amountRupees) > eightyGThresholdRupees,
    );
    const messageRemaining = $derived(500 - donorMessage.length);

    // UX pass (2026-08-08): inline validation state for email + phone. When
    // the donor is identified (non-anonymous), both are required before we
    // allow submission — mirroring the backend rule on donor.name. The
    // `touched` flags keep errors from nagging before the user has typed.
    const isIdentified = $derived(!isAnonymous);
    const phoneValidity = $derived(
        (() => {
            const country = countryByCode(donorPhoneCountry);
            const e164 = toE164(country, donorPhone);
            return e164 === null
                ? { ok: false as const }
                : { ok: true as const, e164 };
        })(),
    );
    const emailValid = $derived(validateEmail(donorEmail).ok);
    // Required + currently invalid (or empty) when identified.
    const emailRequiredError = $derived(
        isIdentified && !emailValid ? (emailErrorMessage(validateEmail(donorEmail)) ?? 'Email is required.') : null,
    );
    const phoneRequiredError = $derived(
        isIdentified && !phoneValidity.ok ? 'A valid phone number is required.' : null,
    );
    let emailTouched = $state(false);
    let phoneTouched = $state(false);
    let panTouched = $state(false);
    const emailError = $derived(emailTouched ? emailRequiredError : null);
    const phoneError = $derived(
        phoneTouched || isIdentified ? phoneRequiredError : null,
    );

    // PAN is optional (only shown above the 80G threshold); when provided it
    // must be a valid 10-char PAN. Case/separator noise is normalized first
    // so a lowercase or spaced PAN is not wrongly rejected.
    const panValidity = $derived(validatePan(donorPan));
    const panError = $derived(panTouched ? panErrorMessage(panValidity) : null);

    let submitting = $state(false);
    let errorMessage = $state<string | null>(null);
    let consentDialogElement: HTMLDialogElement | undefined = $state();
    let showConsentDialog = $state(false);
    let termsAccepted = $state(false);
    let privacyNoticeAcknowledged = $state(false);
    let marketingEmailOptIn = $state(false);
    let pendingAmountMinor = $state<number | null>(null);

    $effect(() => {
        if (!consentDialogElement) return;
        if (showConsentDialog && !consentDialogElement.open) {
            consentDialogElement.showModal();
        } else if (!showConsentDialog && consentDialogElement.open) {
            consentDialogElement.close();
        }
    });

    function handleConsentDialogClose(): void {
        showConsentDialog = false;
        if (!submitting) {
            termsAccepted = false;
            privacyNoticeAcknowledged = false;
            marketingEmailOptIn = false;
            pendingAmountMinor = null;
        }
    }

    function cancelConsent(): void {
        showConsentDialog = false;
        termsAccepted = false;
        privacyNoticeAcknowledged = false;
        marketingEmailOptIn = false;
        pendingAmountMinor = null;
    }

    const selectedCampaignData = $derived(
        campaigns.find((c) => c.slug === String(selectedCampaign)) ?? null,
    );

    /**
     * Read the Inertia-injected CSRF token. Used by the JSON POSTs that
     * sit outside Inertia's own form handling (checkout + verify).
     */
    function currentCsrfToken(): string {
        return (
            (
                document.querySelector(
                    'meta[name="csrf-token"]',
                ) as HTMLMetaElement | null
            )?.content ?? ''
        );
    }

    function rupeesToMinor(rupees: string): number {
        const parsed = parseFloat(rupees);
        if (Number.isNaN(parsed) || parsed <= 0) return 0;
        return Math.round(parsed * 100);
    }

    /**
     * Wave 1 B4 fix (2026-08-06): after the backend returns the Razorpay
     * order id, the Razorpay Standard Checkout modal is opened IMMEDIATELY.
     * The previous flow redirected to /donate/success without opening the
     * modal — donors saw a secondary "Open Razorpay checkout" link, which
     * most treated as a utility link and abandoned. After a successful
     * payment, the handler routes to the success page for status polling.
     *
     * The inline modal logic lives in $shared/lib/razorpay (openRazorpayCheckout)
     * so it can be unit-tested and reused from other donation entry points.
     */
    async function submit(event: SubmitEvent): Promise<void> {
        event.preventDefault();
        if (submitting) return;

        if (!selectedCampaign) {
            errorMessage = 'Please select a campaign.';
            return;
        }

        // Pass B fix (2026-08-07): the checkout contract requires a 35-char
        // campaign ULID (`campaign_…`). The <select> is keyed by slug, so
        // resolve the id from the loaded list and fail closed if the
        // selected slug isn't present (e.g. a deep-link to a campaign not
        // in the displayable list) instead of sending the raw slug and
        // tripping FormRequest size:35 / regex validation.
        if (!selectedCampaignData?.id) {
            errorMessage = 'Please select a valid campaign.';
            return;
        }

        // UX pass (2026-08-08): when the donor is identified (non-anonymous),
        // require a valid email + phone before submit — matching the backend
        // rule that rejects identified donations lacking them. Anonymous
        // donors keep these optional.
        if (isIdentified) {
            emailTouched = true;
            phoneTouched = true;
            if (emailRequiredError) {
                errorMessage = emailRequiredError;
                return;
            }
            if (phoneRequiredError) {
                errorMessage = phoneRequiredError;
                return;
            }
        }

        if (showEightyGFields) {
            panTouched = true;
            if (!panValidity.ok) {
                errorMessage =
                    panErrorMessage(panValidity) ?? 'Please enter a valid PAN.';
                return;
            }
        }

        const amountMinor = rupeesToMinor(amountRupees);
        // RazorpayAdapter enforces ₹1..₹1 crore per adapter's
        // minimumAmount()/maximumAmount() — match on the client too.
        if (amountMinor < 100) {
            errorMessage = 'Minimum donation is ₹1 (100 paise).';
            return;
        }
        const maxMinor = 99_999_999;
        if (amountMinor > maxMinor) {
            errorMessage = 'Maximum donation is ₹9,99,999.99 (one crore paise).';
            return;
        }

        errorMessage = null;
        pendingAmountMinor = amountMinor;
        termsAccepted = false;
        privacyNoticeAcknowledged = false;
        marketingEmailOptIn = false;
        showConsentDialog = true;
    }

    async function confirmConsentAndCheckout(): Promise<void> {
        if (submitting || pendingAmountMinor === null || !termsAccepted || !privacyNoticeAcknowledged) return;

        const amountMinor = pendingAmountMinor;
        const marketingOptIn = !isAnonymous && marketingEmailOptIn;
        submitting = true;
        showConsentDialog = false;
        termsAccepted = false;
        privacyNoticeAcknowledged = false;
        marketingEmailOptIn = false;
        pendingAmountMinor = null;
        errorMessage = null;

        const idempotencyKey =
            typeof crypto !== 'undefined' && 'randomUUID' in crypto
                ? crypto.randomUUID()
                : `idemp_${Date.now()}_${Math.random().toString(36).slice(2)}`;

        // Wave 1 M1 fix (2026-08-06): PAN + address + purpose flow from
        // the form into the payload so the backend can populate 80G certs
        // when the amount crosses the threshold AND the donor is identified.
        const payload = {
            amount_minor: amountMinor,
            currency: defaultCurrency,
            campaign_id: selectedCampaignData?.id ?? '',
            donor: {
                name: isAnonymous ? null : donorName || null,
                email: isAnonymous ? null : (donorEmail.trim() || null),
                // E.164 (e.g. +919876543210) — always satisfies the backend
                // phone regex and gives the gateway/storage one canonical form.
                phone: (phoneValidity.ok && phoneValidity.e164
                    ? phoneValidity.e164
                    : (donorPhone ? toE164(countryByCode(donorPhoneCountry), donorPhone) : null)),
                pan: showEightyGFields ? normalizePan(donorPan) || null : null,
                address: isAnonymous
                    ? null
                    : (donorAddressLine1 || donorCity || donorPincode
                        ? {
                              line1: donorAddressLine1,
                              line2: donorAddressLine2,
                              city: donorCity,
                              state: donorState,
                              pincode: donorPincode,
                              country: donorCountry,
                          }
                        : null),
            },
            donation_message: donorMessage || null,
            purpose: purpose || null,
            idempotency_key: idempotencyKey,
            policy_acceptance: {
                terms_version: termsPolicyVersion,
                terms_accepted: true,
                privacy_notice_version: privacyPolicyVersion,
                privacy_notice_acknowledged: true,
            },
            marketing_email_opt_in: marketingOptIn,
        };

        try {
            const csrfToken = currentCsrfToken();
            const response = await fetch('/api/v1/razorpay/checkout', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Idempotency-Key': idempotencyKey,
                },
                body: JSON.stringify(payload),
                credentials: 'same-origin',
            });

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                errorMessage =
                    data.message ??
                    data.error ??
                    `Submission failed (HTTP ${response.status}).`;
                submitting = false;
                return;
            }

            const data = await response.json();
            const orderId = data.order_id;
            const keyId = data.key_id ?? '';
            const amount = (data.amount_minor ?? amountMinor) as number;
            const currency = (data.currency ?? defaultCurrency) as string;

            if (!orderId) {
                errorMessage = 'Gateway did not return an order id.';
                submitting = false;
                return;
            }
            if (!keyId) {
                errorMessage =
                    'Gateway did not return a Razorpay key id. Check that payments.providers.razorpay.key_id is configured.';
                submitting = false;
                return;
            }

            // Open Razorpay modal immediately (B4 fix). If checkout.js
            // fails to load or throws, fall back to the success page
            // polling UX (preserves the previous no-modal path).
            try {
                await openRazorpayCheckout({
                    orderId,
                    keyId,
                    amountMinor: amount,
                    currency,
                    appName,
                    onSuccess: async (response?: RazorpaySuccessResponse) => {
                        // Confirm the capture server-side through the canonical
                        // signature-verification endpoint before showing success.
                        // The backend flips the Payment to CAPTURED and the
                        // success page then reads authoritative state. A failure
                        // here is non-fatal: the page still reconciles against
                        // the gateway as a fallback.
                        if (
                            response?.razorpay_order_id &&
                            response.razorpay_payment_id &&
                            response.razorpay_signature
                        ) {
                            try {
                                await fetch('/api/v1/razorpay/verify', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        Accept: 'application/json',
                                        'X-CSRF-TOKEN': currentCsrfToken(),
                                        'X-Requested-With': 'XMLHttpRequest',
                                    },
                                    credentials: 'same-origin',
                                    body: JSON.stringify({
                                        razorpay_order_id:
                                            response.razorpay_order_id,
                                        razorpay_payment_id:
                                            response.razorpay_payment_id,
                                        razorpay_signature:
                                            response.razorpay_signature,
                                    }),
                                });
                            } catch {
                                /* non-fatal — success page reconciles */
                            }
                        }

                        router.visit(
                            `/donate/success?gateway_order_id=${encodeURIComponent(response?.razorpay_order_id ?? orderId)}`,
                        );
                    },
                    onDismiss: () => {
                        router.visit('/donate/cancel');
                    },
                });
                submitting = false;
            } catch (modalErr) {
                console.error('Razorpay modal open failed', modalErr);
                alert(
                    modalErr instanceof Error
                        ? modalErr.message
                        : 'Razorpay failed to open. Continuing to status page.',
                );
                router.visit(
                    `/donate/success?gateway_order_id=${encodeURIComponent(orderId)}`,
                );
                submitting = false;
            }
        } catch (err) {
            errorMessage = err instanceof Error ? err.message : 'Network error.';
            submitting = false;
        }
    }
</script>

<SeoHead
    title="Donate"
    {appName}
    {appUrl}
    description="Choose the campaign your gift supports and give securely through Razorpay. An official receipt is issued for every donation."
/>

<PublicLayout>
    <section class="relative overflow-hidden bg-background">
        <div
            class="pointer-events-none absolute -right-20 top-0 opacity-[0.08]"
            aria-hidden="true"
        >
            <MandalaDecoration size={320} tint="gold" />
        </div>

        <div class="container relative py-12 lg:py-20">
            <a
                href="/"
                class="mb-6 inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-primary"
            >
                <ArrowLeft class="h-4 w-4" aria-hidden="true" />
                Back to home
            </a>

            <div class="grid grid-cols-1 items-start gap-10 lg:grid-cols-12 lg:gap-16">
                <header class="space-y-4 lg:col-span-5">
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                    >
                        Give with purpose
                    </p>
                    <h1
                        class="font-serif text-3xl font-semibold leading-tight lg:text-5xl"
                    >
                        Support a campaign
                    </h1>
                    <p
                        class="max-w-md text-base leading-relaxed text-muted-foreground lg:text-lg"
                    >
                        Choose the campaign your gift should support. Every
                        contribution is acknowledged with an official receipt,
                        and secure payment is handled by Razorpay.
                    </p>
                    <div
                        class="flex items-center gap-2 pt-2 text-xs text-muted-foreground"
                    >
                        <Shield
                            class="h-4 w-4 text-primary/70"
                            aria-hidden="true"
                        />
                        <span>
                            Secure payment via Razorpay · An official receipt
                            is issued for every donation{#if isTestMode}
                                · <span class="font-semibold text-primary">Test mode</span>{/if}
                        </span>
                    </div>
                </header>

                <div class="lg:col-span-7">
                    {#if campaigns.length === 0}
                        <div
                            class="rounded-md border border-dashed border-border bg-ivory/60 p-8 text-center"
                        >
                            <p class="text-sm text-muted-foreground">
                                No active campaigns accepting donations right
                                now.
                            </p>
                        </div>
                    {:else}
                        <form onsubmit={submit} class="space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Giving destination</CardTitle>
                                    <CardDescription>
                                        Choose the campaign your gift supports.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent class="space-y-3">
                                    <Label for="campaign">Campaign</Label>
                                    <select
                                        id="campaign"
                                        bind:value={selectedCampaign}
                                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                    >
                                        {#each campaigns as offering (offering.id)}
                                            <option value={offering.slug}>
                                                {offering.title}
                                            </option>
                                        {/each}
                                    </select>
                                    {#if selectedCampaignData}
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            Currency: {selectedCampaignData.currency_code}
                                        </p>
                                        {#if selectedCampaignData.short_description}
                                            <p class="text-sm leading-relaxed text-foreground/80">
                                                {selectedCampaignData.short_description}
                                            </p>
                                        {/if}
                                    {/if}
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Amount</CardTitle>
                                    <CardDescription>
                                        Charged in {defaultCurrency} via Razorpay{#if isTestMode}
                                            (sandbox){/if}.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent class="space-y-3">
                                    <Label for="amount">Amount (₹)</Label>
                                    <Input
                                        id="amount"
                                        type="number"
                                        min="1"
                                        step="1"
                                        bind:value={amountRupees}
                                        placeholder="1000"
                                    />
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Donor information</CardTitle>
                                    <CardDescription>
                                        Name, email, and phone are required for
                                        an identified donation. Choose
                                        anonymous giving to leave those details
                                        out. Receipt and tax-deductibility
                                        details, including 80G where applicable,
                                        are confirmed under the trust’s current
                                        status and applicable law.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent class="space-y-3">
                                    <label
                                        class="flex items-center gap-2 text-sm"
                                    >
                                        <input
                                            type="checkbox"
                                            bind:checked={isAnonymous}
                                            class="h-4 w-4 rounded border-input text-primary"
                                        />
                                        Make this donation anonymous
                                    </label>

                                    <div class="space-y-2">
                                        <Label for="name">Name{#if !isAnonymous} (required){/if}</Label>
                                        <Input
                                            id="name"
                                            type="text"
                                            maxlength={120}
                                            bind:value={donorName}
                                            placeholder="Your full name"
                                            disabled={isAnonymous}
                                            required={!isAnonymous}
                                        />
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="email">Email{#if !isAnonymous} (required){/if}</Label>
                                        <Input
                                            id="email"
                                            type="email"
                                            maxlength={255}
                                            bind:value={donorEmail}
                                            oninput={() => (emailTouched = true)}
                                            placeholder="you@example.com"
                                            disabled={isAnonymous}
                                            required={!isAnonymous}
                                            aria-invalid={emailError ? 'true' : undefined}
                                            aria-describedby={emailError ? 'email-error' : undefined}
                                            class={emailError ? 'border-destructive' : ''}
                                        />
                                        {#if emailError}
                                            <p id={`email-error`} class="text-xs text-destructive">{emailError}</p>
                                        {/if}
                                    </div>

                                    <div class="space-y-2">
                                        <PhoneInput
                                            id="phone"
                                            label="Phone"
                                            bind:value={donorPhone}
                                            bind:country={donorPhoneCountry}
                                            required={isIdentified}
                                            disabled={isAnonymous}
                                            error={phoneError}
                                            validate={() => (phoneTouched = true)}
                                        />
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="message"
                                            >Message (optional)</Label
                                        >
                                        <Input
                                            id="message"
                                            type="text"
                                            maxlength={500}
                                            bind:value={donorMessage}
                                            placeholder="A short dedication or note"
                                        />
                                        <p
                                            class="text-right text-xs text-muted-foreground"
                                        >
                                            {messageRemaining} / 500 remaining
                                        </p>
                                    </div>

                                    {#if showEightyGFields}
                                        <div
                                            class="space-y-2 border-t border-border/40 pt-4"
                                        >
                                            <p
                                                class="text-xs font-semibold uppercase tracking-wider text-primary"
                                            >
                                                80G receipt details
                                            </p>
                                            <div class="space-y-2">
                                                <Label for="pan"
                                                    >PAN</Label
                                                >
                                                <Input
                                                    id="pan"
                                                    type="text"
                                                    inputmode="text"
                                                    autocomplete="off"
                                                    maxlength={10}
                                                    value={donorPan}
                                                    oninput={(e) => {
                                                        donorPan =
                                                            normalizePan(
                                                                (
                                                                    e.currentTarget as HTMLInputElement
                                                                ).value,
                                                            );
                                                    }}
                                                    onblur={() => {
                                                        panTouched = true;
                                                    }}
                                                    aria-invalid={panError
                                                        ? 'true'
                                                        : 'false'}
                                                    placeholder="AAAAA9999A"
                                                />
                                                {#if panError}
                                                    <p
                                                        class="text-xs text-destructive"
                                                    >
                                                        {panError}
                                                    </p>
                                                {:else}
                                                    <p
                                                        class="text-xs text-muted-foreground"
                                                    >
                                                        Format: AAAAA9999A
                                                    </p>
                                                {/if}
                                            </div>
                                            <div class="space-y-2">
                                                <Label for="address-line1"
                                                    >Address line 1</Label
                                                >
                                                <Input
                                                    id="address-line1"
                                                    type="text"
                                                    maxlength={255}
                                                    bind:value={donorAddressLine1}
                                                    placeholder="Street, building"
                                                />
                                            </div>
                                            <div class="space-y-2">
                                                <Label for="address-line2"
                                                    >Address line 2 (optional)</Label
                                                >
                                                <Input
                                                    id="address-line2"
                                                    type="text"
                                                    maxlength={255}
                                                    bind:value={donorAddressLine2}
                                                />
                                            </div>
                                            <div
                                                class="grid grid-cols-1 gap-3 sm:grid-cols-3"
                                            >
                                                <div class="space-y-2">
                                                    <Label for="city"
                                                        >City</Label
                                                    >
                                                    <Input
                                                        id="city"
                                                        type="text"
                                                        maxlength={120}
                                                        bind:value={donorCity}
                                                    />
                                                </div>
                                                <div class="space-y-2">
                                                    <Label for="state"
                                                        >State</Label
                                                    >
                                                    <Input
                                                        id="state"
                                                        type="text"
                                                        maxlength={120}
                                                        bind:value={donorState}
                                                    />
                                                </div>
                                                <div class="space-y-2">
                                                    <Label for="pincode"
                                                        >Pincode</Label
                                                    >
                                                    <Input
                                                        id="pincode"
                                                        type="text"
                                                        maxlength={12}
                                                        bind:value={donorPincode}
                                                    />
                                                </div>
                                            </div>
                                            <div class="space-y-2">
                                                <Label for="country"
                                                    >Country</Label
                                                >
                                                <Input
                                                    id="country"
                                                    type="text"
                                                    maxlength={64}
                                                    bind:value={donorCountry}
                                                />
                                            </div>
                                        </div>

                                        <div
                                            class="space-y-2 border-t border-border/40 pt-4"
                                        >
                                            <Label for="purpose"
                                                >Purpose (optional)</Label
                                            >
                                            <Input
                                                id="purpose"
                                                type="text"
                                                maxlength={120}
                                                bind:value={purpose}
                                                placeholder="E.g. Pooja sponsorship"
                                            />
                                        </div>
                                    {/if}
                                </CardContent>
                            </Card>

                            {#if errorMessage}
                                <Alert variant="destructive"
                                    >{errorMessage}</Alert
                                >
                            {/if}

                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <p class="text-xs text-muted-foreground">
                                    Powered by Razorpay{#if isTestMode}
                                        · <span class="font-semibold">Test mode</span>{/if}
                                </p>
                                <Button type="submit" disabled={submitting}>
                                    <Heart
                                        class="mr-1.5 h-4 w-4"
                                        aria-hidden="true"
                                    />
                                    {submitting
                                        ? 'Opening Razorpay…'
                                        : 'Continue to payment'}
                                </Button>
                            </div>
                        </form>
                    {/if}
                </div>
            </div>
        </div>
    </section>

    <dialog
        bind:this={consentDialogElement}
        onclose={handleConsentDialogClose}
        aria-labelledby="checkout-consent-title"
        aria-describedby="checkout-consent-description"
        class="m-auto max-h-[90vh] w-[calc(100%-2rem)] max-w-xl overflow-y-auto rounded-xl border border-border bg-card p-0 text-card-foreground shadow-2xl backdrop:bg-black/60"
    >
        <div class="space-y-6 p-6 sm:p-8">
            <header class="space-y-2">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary">
                    Before payment
                </p>
                <h2 id="checkout-consent-title" class="font-serif text-2xl font-semibold">
                    Review your choices
                </h2>
                <p id="checkout-consent-description" class="text-sm leading-relaxed text-muted-foreground">
                    We need your agreement to the donation terms and confirmation that you have read the privacy notice before opening the payment gateway. Campaign email is a separate, optional choice.
                </p>
            </header>

            <fieldset class="space-y-4">
                <legend class="sr-only">Required policy acknowledgements</legend>
                <label for="terms-accepted" class="flex items-start gap-3 text-sm leading-relaxed">
                    <input
                        id="terms-accepted"
                        type="checkbox"
                        bind:checked={termsAccepted}
                        aria-required="true"
                        class="mt-1 h-4 w-4 shrink-0 rounded border-input text-primary focus-visible:ring-ring"
                    />
                    <span>
                        I agree to the
                        <a href="/terms" target="_blank" rel="noreferrer" class="font-medium text-primary underline underline-offset-4">
                            Terms &amp; Conditions (v{termsPolicyVersion})
                        </a>.
                    </span>
                </label>

                <label for="privacy-acknowledged" class="flex items-start gap-3 text-sm leading-relaxed">
                    <input
                        id="privacy-acknowledged"
                        type="checkbox"
                        bind:checked={privacyNoticeAcknowledged}
                        aria-required="true"
                        class="mt-1 h-4 w-4 shrink-0 rounded border-input text-primary focus-visible:ring-ring"
                    />
                    <span>
                        I have read the
                        <a href="/privacy" target="_blank" rel="noreferrer" class="font-medium text-primary underline underline-offset-4">
                            Privacy Policy (v{privacyPolicyVersion})
                        </a>.
                    </span>
                </label>

                {#if !isAnonymous}
                    <label for="marketing-email-opt-in" class="flex items-start gap-3 border-t border-border pt-4 text-sm leading-relaxed">
                        <input
                            id="marketing-email-opt-in"
                            type="checkbox"
                            bind:checked={marketingEmailOptIn}
                            class="mt-1 h-4 w-4 shrink-0 rounded border-input text-primary focus-visible:ring-ring"
                        />
                        <span>
                            Optional: email me about future campaigns and the Trust’s activities. I can unsubscribe at any time. This is not required to donate.
                        </span>
                    </label>
                {/if}
            </fieldset>

            <div class="flex flex-col-reverse justify-end gap-3 sm:flex-row">
                <button
                    type="button"
                    onclick={cancelConsent}
                    class="inline-flex h-10 items-center justify-center rounded-md border border-input bg-background px-4 text-sm font-medium transition hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                    Not now
                </button>
                <button
                    type="button"
                    onclick={() => void confirmConsentAndCheckout()}
                    disabled={!termsAccepted || !privacyNoticeAcknowledged || submitting}
                    class="inline-flex h-10 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50"
                >
                    {submitting ? 'Opening payment…' : 'Agree & continue to payment'}
                </button>
            </div>
        </div>
    </dialog>
</PublicLayout>
