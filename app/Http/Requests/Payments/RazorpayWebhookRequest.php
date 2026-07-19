<?php

declare(strict_types=1);

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Accepts the raw Razorpay webhook POST.
 *
 * Doctrine:
 *   - This FormRequest is permissive on schema. Razorpay's webhook body
 *     shape varies by event type, and the canonical authorization gate
 *     is the HMAC signature, validated downstream by
 *     RazorpayVerificationAdapter. Laravel-side schema validation would
 *     break replay protection and reduce us to guessing Razorpay's
 *     event taxonomy.
 *   - Middleware (WebhookDedupeMiddleware, applied via the route) carries
 *     the dedupe + idempotency responsibilities. The controller does not
 *     re-implement them.
 *
 * The controller still reads the raw body (verbatim) via $request->getContent()
 * so HMAC verification uses the exact bytes Razorpay signed.
 */
final class RazorpayWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is granted to the public — the routing is HMAC-gated
        // inside PaymentOrchestrator::handleWebhook. Returning true here
        // means we never short-circuit dispatch.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Disable validation redirects. A malformed webhook must always
     * produce a JSON error, not an HTML redirect.
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator): never
    {
        throw new \Illuminate\Validation\ValidationException($validator);
    }
}
