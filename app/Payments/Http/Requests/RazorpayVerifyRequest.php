<?php

declare(strict_types=1);

namespace App\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the Razorpay Standard Checkout synchronous callback.
 *
 * Razorpay's checkout.js fires the modal's `handler` callback with a
 * response object containing:
 *   - razorpay_order_id   (the order id we returned from /api/v1/razorpay/checkout)
 *   - razorpay_payment_id (the canonical Razorpay payment id)
 *   - razorpay_signature  (HMAC-SHA256 over "{order_id}|{payment_id}" using key_secret)
 *
 * This request runs AFTER the modal closes. It is the synchronous verification
 * endpoint that lets the donor see "Payment received" instantly, instead of
 * waiting for the async webhook (which can take 2-5 minutes for UPI).
 *
 * The webhook remains the source-of-truth reconciliation path; this endpoint
 * only flips the local Payment state to CAPTURED earlier for UX.
 */
final class RazorpayVerifyRequest extends FormRequest
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
            'razorpay_order_id' => ['required', 'string', 'min:1', 'max:64'],
            'razorpay_payment_id' => ['required', 'string', 'min:1', 'max:64'],
            'razorpay_signature' => ['required', 'string', 'min:1', 'max:128'],
        ];
    }
}
