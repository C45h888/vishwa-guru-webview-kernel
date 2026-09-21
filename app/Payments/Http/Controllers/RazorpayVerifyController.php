<?php

declare(strict_types=1);

namespace App\Payments\Http\Controllers;

use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Http\Requests\RazorpayVerifyRequest;
use App\Payments\Services\PaymentService;
use Illuminate\Http\JsonResponse;

/**
 * Synchronous Razorpay Standard Checkout callback verification.
 *
 * Doctrine (constitutional):
 *   - The controller is pure transport. It builds a Razorpay verification
 *     request from validated input and hands it to
 *     PaymentService::verifyCheckoutCallback, which delegates to
 *     PaymentOrchestrator → RazorpayVerificationAdapter (HMAC) → state
 *     transition.
 *   - This endpoint exists to close the UX gap between the modal closing
 *     and the async webhook arriving (2-5 min for UPI). The webhook
 *     remains the canonical reconciliation source-of-truth; this endpoint
 *     only flips the local Payment to CAPTURED earlier.
 *   - Signature verification is the canonical authorization gate. It runs
 *     inside the orchestrator via RazorpayVerificationAdapter, NOT here.
 *     Returning the right HTTP status code based on the orchestrator's
 *     Result is the controller's only logic.
 *   - State transitions happen ONLY via the PaymentStateMachine inside
 *     the orchestrator. This controller never touches entities,
 *     repositories, or the gateway.
 *   - Response semantics:
 *       200 — signature valid + payment captured (or already terminal-success)
 *       401 — signature invalid
 *       422 — missing fields, gateway order not found locally, etc.
 *       5xx — server-side error (Laravel default path)
 */
final class RazorpayVerifyController
{
    public function __construct(
        private readonly PaymentService $payments,
    ) {}

    public function verify(RazorpayVerifyRequest $request): JsonResponse
    {
        $razorpayOrderId = (string) $request->validated('razorpay_order_id');
        $razorpayPaymentId = (string) $request->validated('razorpay_payment_id');
        $razorpaySignature = (string) $request->validated('razorpay_signature');

        $result = $this->payments->verifyCheckoutCallback(
            $razorpayOrderId,
            $razorpayPaymentId,
            $razorpaySignature,
        );

        if ($result->isFailure()) {
            $error = (string) $result->error();
            $status = str_contains(strtolower($error), 'signature') ? 401 : 422;

            return response()->json(
                ['error' => $error],
                $status,
            );
        }

        /** @var TransactionStatus $paymentStatus */
        $paymentStatus = $result->value();

        return response()->json(
            [
                'status' => $paymentStatus->value,
                'razorpay_order_id' => $razorpayOrderId,
                'razorpay_payment_id' => $razorpayPaymentId,
            ],
            200,
        );
    }
}
