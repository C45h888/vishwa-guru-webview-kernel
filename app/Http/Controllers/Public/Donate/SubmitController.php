<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public\Donate;

use App\Payments\Domain\Repositories\PaymentRepositoryContract;
use App\Payments\Http\Resources\PaymentStatusResource;
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
 */
final class SubmitController
{
    public function __invoke(PaymentRepositoryContract $payments): Response
    {
        $gatewayOrderId = (string) request()->query('gateway_order_id', '');
        $publicKeyId = (string) config('payments.providers.razorpay.key_id', '');

        $payment = $gatewayOrderId !== ''
            ? $payments->findByGatewayOrderId($gatewayOrderId)
            : null;

        $status = $payment !== null
            ? PaymentStatusResource::fromEntity($payment, $publicKeyId)
            : PaymentStatusResource::missing($gatewayOrderId);

        return Inertia::render('payments/Success', [
            'payment' => $status,
            'appName' => config('app.name', 'Temple Trust'),
            'appUrl' => config('app.url'),
        ]);
    }
}
