<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public\Donate;

use App\Payments\Domain\Repositories\PaymentRepositoryContract;
use App\Payments\Domain\Repositories\ReceiptRepositoryContract;
use App\Payments\Http\Resources\PaymentStatusResource;
use App\Seo\Contracts\SeoMetaContract;
use App\Payments\Services\PaymentService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Donation success status page (GET /donate/success).
 *
 * Called after the Svelte form POSTs to /api/v1/razorpay/checkout and
 * Razorpay's checkout.js modal closes. Reads ?gateway_order_id=… from
 * the URL and looks up the matching Payment row. If no row exists
 * yet (e.g. the user opened /donate/success directly), returns the
 * missing-state shape so the Svelte Success.svelte can show a
 * "we couldn't find that order" message instead of crashing.
 *
 * The page reflects the backend's authoritative state, not the browser's
 * word. On a fresh load (not an in-place Inertia poll) a non-terminal
 * payment is reconciled against the gateway first, so a payment that the
 * provider has really captured is shown as successful even when the
 * async webhook is delayed or missing. Polls only read local state, so
 * the page never hammers the gateway.
 */
final class SubmitController
{
    public function __invoke(
        PaymentRepositoryContract $payments,
        ReceiptRepositoryContract $receipts,
        PaymentService $paymentService,
        SeoMetaContract $seo,
    ): Response {
        $request = request();
        $gatewayOrderId = (string) $request->query('gateway_order_id', '');
        $publicKeyId = (string) config('payments.providers.razorpay.key_id', '');

        $payment = $gatewayOrderId !== ''
            ? $payments->findByGatewayOrderId($gatewayOrderId)
            : null;

        // Reconcile on full page loads only — the client's status poll
        // sends X-Inertia-Partial-Component, which we treat as
        // read-only. This keeps the completion page accurate without
        // issuing a gateway call on every 2s poll.
        $isPartialPoll = $request->header('X-Inertia-Partial-Component') !== null;
        if (
            ! $isPartialPoll
            && $payment !== null
            && $gatewayOrderId !== ''
            && ! $payment->status()->isTerminal()
            && ! $payment->status()->isSuccessful()
        ) {
            $paymentService->reconcileOrder($gatewayOrderId);
            $payment = $payments->findByGatewayOrderId($gatewayOrderId);
        }

        $receipt = null;
        if ($payment !== null) {
            $receiptEntity = $receipts->findByTransactionId($payment->id());
            if ($receiptEntity !== null) {
                $token = (string) ($receiptEntity->accessToken() ?? '');
                $receipt = [
                    'number' => $receiptEntity->receiptNumber(),
                    'download_path' => '/receipts/'.rawurlencode($receiptEntity->receiptNumber())
                        .'/download'.($token !== '' ? '?t='.rawurlencode($token) : ''),
                ];
            }
        }

        $status = $payment !== null
            ? PaymentStatusResource::fromEntity($payment, $publicKeyId, $receipt)
            : PaymentStatusResource::missing($gatewayOrderId);

        return Inertia::render('payments/Success', [
            'payment' => $status,
            'appName' => config('app.name', 'Temple Trust'),
            'appUrl' => config('app.url'),
            'seo' => $seo->forPage(title: 'Donation status', noindex: true),
        ]);
    }
}
