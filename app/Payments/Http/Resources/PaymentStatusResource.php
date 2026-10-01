<?php

declare(strict_types=1);

namespace App\Payments\Http\Resources;

use App\Payments\Domain\Entities\Payment;

/**
 * Narrow read-shape of a Payment for the public UI Success page.
 *
 * The Payment entity's full toArray() carries ~30 internal columns
 * (raw_provider_response, verification_metadata, idempotency_key,
 * etc.) that the public UI never needs. The Success page only
 * requires the gateway order id, current status, amount, currency,
 * the public Razorpay key id needed to open checkout.js, and the
 * receipt download reference once one exists.
 *
 * The key_id is loaded from the active provider config so the
 * controller never has to know about provider-specific config keys.
 */
final class PaymentStatusResource
{
    /**
     * @param  array{number: string, download_path: string}|null  $receipt
     * @return array<string, mixed>
     */
    public static function fromEntity(Payment $payment, string $publicKeyId, ?array $receipt = null): array
    {
        return [
            'gateway_order_id' => (string) $payment->providerOrderId(),
            'status' => $payment->status()->value,
            'amount_minor' => $payment->amountMinor(),
            'currency_code' => $payment->currency()->value,
            'provider_code' => $payment->providerCode()->value,
            'captured_at' => $payment->capturedAt()?->format(DATE_ATOM),
            'failed_at' => $payment->failedAt()?->format(DATE_ATOM),
            'last_failure_reason' => $payment->lastFailureReason(),
            'public_key_id' => $publicKeyId,
            'receipt' => $receipt,
        ];
    }

    /**
     * Empty-state shape when no Payment row exists for the gateway order id.
     *
     * @return array<string, mixed>
     */
    public static function missing(string $gatewayOrderId): array
    {
        return [
            'gateway_order_id' => $gatewayOrderId,
            'status' => null,
            'amount_minor' => null,
            'currency_code' => null,
            'provider_code' => null,
            'captured_at' => null,
            'failed_at' => null,
            'last_failure_reason' => null,
            'public_key_id' => '',
            'receipt' => null,
        ];
    }
}
