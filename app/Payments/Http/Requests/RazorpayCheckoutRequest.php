<?php

declare(strict_types=1);

namespace App\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the public Razorpay Standard Checkout initialization request.
 *
 * Doctrine:
 *   - Authorization is intentionally permissive; financial integrity is
 *     enforced downstream in PaymentOrchestrator via the PaymentStateMachine.
 *     The webhook signature does the actual auth on the callback path.
 *   - Validation enforces the Razorpay API minimums (INR + amount ≥ ₹1).
 *   - Donor fields are optional (anonymous donations are legal in India for
 *     amounts below the 80G-reporting threshold); strict 80G PAN format
 *     applies only when a PAN is supplied.
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
            'donor.email' => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
            'donor.phone' => ['sometimes', 'nullable', 'string', 'regex:/^\+?[0-9\s\-()]{7,20}$/'],
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
            'donor.pan.regex' => 'PAN must match the format AAAAA9999A.',
            'donor.phone.regex' => 'Phone must be 7-20 characters, digits with optional + ( ) -.',
        ];
    }
}
