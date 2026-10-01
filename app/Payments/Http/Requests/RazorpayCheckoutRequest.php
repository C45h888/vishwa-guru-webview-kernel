<?php

declare(strict_types=1);

namespace App\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the public Razorpay Standard Checkout initialization request.
 *
 * Doctrine:
 *   - Authorization is intentionally permissive; financial integrity is
 *     enforced downstream in PaymentOrchestrator via the PaymentStateMachine.
 *     The webhook signature does the actual auth on the callback path.
 *   - Validation enforces the Razorpay API minimums (INR + amount ≥ ₹1).
 *   - Donor fields are optional for anonymous donations (legal in India for
 *     amounts below the 80G-reporting threshold); strict 80G PAN format
 *     applies only when a PAN is supplied.
 *   - A *non-anonymous* donation (one carrying a donor.name) MUST provide a
 *     contactable email and phone. Identification is signalled by
 *     `donor.name` being present — mirroring DonorIdentity::anonymous() in
 *     RazorpayCheckoutController::buildDonor(). Anonymous donations (no
 *     name) keep email/phone optional.
 *
 * After validation, the controller builds a DonationIntent and hands it to
 * the PaymentService. No business logic lives here.
 */
final class RazorpayCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize the donor PAN before validation.
     *
     * PAN is canonically uppercase (AAAAA9999A) and case-sensitive at the
     * storage/domain layer. Donors routinely type it lowercase or paste it
     * with spaces, which previously tripped the strict regex and surfaced
     * as "The donor.pan field format is invalid." Normalizing at the
     * request boundary means any casing/spacing is accepted while the
     * persisted PAN stays canonical. Empty → null so `nullable` applies.
     */
    protected function prepareForValidation(): void
    {
        $donor = $this->input('donor');
        if (! is_array($donor) || ! array_key_exists('pan', $donor)) {
            return;
        }

        $donor['pan'] = $this->normalizePan($donor['pan']);
        $this->merge(['donor' => $donor]);
    }

    private function normalizePan(mixed $pan): ?string
    {
        if ($pan === null) {
            return null;
        }
        if (! is_string($pan)) {
            // Leave non-string input untouched so the `string` rule reports it.
            return null;
        }

        $normalized = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $pan));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * A donor is "identified" when a name is supplied. Anonymous donors send
     * name as null/absent; the controller maps that to DonorIdentity::anonymous().
     */
    private function hasIdentifiedDonor(): bool
    {
        return $this->filled('donor.name');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'amount_minor' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'in:INR'],
            'campaign_id' => [
                'required',
                'string',
                'size:35',
                'regex:/^campaign_[0-9A-HJKMNP-TV-Z]{26}$/',
            ],

            'donor' => ['sometimes', 'array'],
            'donor.name' => ['sometimes', 'nullable', 'string', 'min:1', 'max:120'],
            // NOTE: don't add `sometimes` to email/phone — `sometimes` makes
            // an *absent* key skip validation entirely, so an identified
            // donation omitting email/phone would pass. With only `nullable`
            // + `Rule::requiredIf(identified)`, an identified donation that
            // omission or blanks these fields fails, while anonymous donations
            // (no name) keep them optional.
            'donor.email' => [
                'nullable',
                Rule::requiredIf($this->hasIdentifiedDonor()),
                'email:rfc',
                'max:255',
            ],
            'donor.phone' => [
                'nullable',
                Rule::requiredIf($this->hasIdentifiedDonor()),
                'string',
                'regex:/^\+?[0-9\s\-()]{7,20}$/',
            ],
            'donor.pan' => ['sometimes', 'nullable', 'string', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'],
            'donor.address' => ['sometimes', 'nullable', 'array'],
            'donor.address.line1' => ['sometimes', 'string', 'max:255'],
            'donor.address.line2' => ['sometimes', 'string', 'max:255'],
            'donor.address.city' => ['sometimes', 'string', 'max:120'],
            'donor.address.state' => ['sometimes', 'string', 'max:120'],
            'donor.address.pincode' => ['sometimes', 'string', 'max:12'],
            'donor.address.country' => ['sometimes', 'string', 'max:64'],

            'purpose' => ['sometimes', 'nullable', 'string', 'max:120'],
            'donation_message' => ['sometimes', 'nullable', 'string', 'max:500'],
            // Wave 1 m12 fix (2026-08-06): 'internal_notes' was a publicly
            // POSTable field on the donation contract — donors could
            // taint the donations.internal_notes column with attacker
            // content (admin-trust-boundary violation / XSS surface in
            // any consumer that renders without escaping). It was
            // accepted by the FormRequest but never sent by the
            // legitimate Donate.svelte form. Removed from the public
            // rules; staff-authored internal notes belong on a separate
            // /admin/* endpoint once admin CMS lands.
            'idempotency_key' => ['sometimes', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'currency.in' => 'Razorpay only supports INR at this time.',
            'donor.email.required_if' => 'Email is required for identified donations.',
            'donor.phone.required_if' => 'Phone is required for identified donations.',
            'donor.pan.regex' => 'PAN must match the format AAAAA9999A.',
            'donor.phone.regex' => 'Phone must be 7-20 characters, digits with optional + ( ) -.',
        ];
    }
}
