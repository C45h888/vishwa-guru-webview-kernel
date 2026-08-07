<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Adapters\Razorpay;

use App\Payments\Contracts\PaymentGatewayContract;
use App\Payments\Domain\DTOs\GatewayResponseDTO;
use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Domain\Exceptions\PaymentInitializationFailedException;
use App\Payments\Domain\Exceptions\PaymentVerificationFailedException;
use App\Payments\Domain\Exceptions\RefundExceededException;
use App\Payments\Domain\ValueObjects\PaymentRequest;
use App\Shared\Support\Result;
use App\Shared\ValueObjects\Identifier;
use Throwable;

/**
 * Razorpay implementation of PaymentGatewayContract.
 * Translates our domain types to Razorpay API payloads and back.
 */
final class RazorpayAdapter implements PaymentGatewayContract
{
    public function __construct(
        private RazorpayClient $client,
    ) {}

    /**
     * Initialize a payment order at Razorpay.
     */
    public function initialize(PaymentRequest $request): Result
    {
        $payload = [
            'amount' => $request->amount(),
            'currency' => $request->currency()->value,
            'receipt' => $request->idempotencyKey() ?? $request->donorIdentifier()->value(),
            'notes' => [
                'donation_id' => $request->donorIdentifier()->value(),
                'purpose' => $request->purpose(),
            ],
            'payment_capture' => 1, // auto-capture
        ];

        try {
            $raw = $this->client->createOrder($payload);

            return Result::success(new GatewayResponseDTO(
                providerCode: 'razorpay',
                gatewayOrderId: (string) $raw['id'],
                gatewayPaymentId: null,
                rawStatusString: (string) ($raw['status'] ?? 'created'),
                amountMinor: (int) $raw['amount'],
                currency: Currency::from($raw['currency']),
                rawResponse: $raw,
                checkoutUrl: null,
                method: null,
            ));
        } catch (PaymentInitializationFailedException $e) {
            return Result::failure($e->getMessage());
        } catch (Throwable $e) {
            return Result::failure(
                PaymentInitializationFailedException::sdkFailure('razorpay', $e->getMessage())->getMessage(),
            );
        }
    }

    /**
     * Verify a payment's status via Razorpay API.
     */
    public function verify(string $gatewayOrderId, ?string $gatewayPaymentId = null): Result
    {
        try {
            $data = $gatewayPaymentId !== null
                ? $this->client->fetchPayment($gatewayPaymentId)
                : $this->client->fetchOrder($gatewayOrderId);

            $status = $this->mapStatus((string) ($data['status'] ?? ''));

            return Result::success($status);
        } catch (PaymentVerificationFailedException $e) {
            return Result::failure($e->getMessage());
        } catch (Throwable $e) {
            return Result::failure(
                PaymentVerificationFailedException::unknownStatus(
                    $gatewayOrderId,
                    $e->getMessage(),
                )->getMessage(),
            );
        }
    }

    /**
     * Capture a payment (Razorpay auto-captures, so this is a no-op).
     */
    public function capture(Identifier $transactionId, int $amount): Result
    {
        // Razorpay auto-captures on order creation with payment_capture=1
        return Result::success(TransactionStatus::CAPTURED);
    }

    /**
     * Refund a captured payment.
     *
     * Razorpay's /v1/refunds API can return any of three statuses:
     *   'processed' — refund completed by Razorpay's bank rail
     *   'pending'   — refund accepted, still processing (legitimate interim state)
     *   'failed'    — refund rejected (insufficient balance, fraud, etc.)
     *
     * Wave 1 fix (M2 + N7, 2026-08-06):
     *   - The previous mapping collapsed 'failed' (and 'pending') to PENDING,
     *     producing a false-positive REFUNDED in the orchestrator. Now each
     *     Razorpay status maps to its canonical TransactionStatus.
     *   - The Throwable catch no longer fabricates a RefundExceededException
     *     message; non-RefundExceeded failures bubble up with the real
     *     exception class so operator logs see the genuine root cause
     *     (network error / 5xx / SDK auth failure) instead of a misleading
     *     'refund exceeds captured' with hardcoded zeros.
     */
    public function refund(Identifier $transactionId, int $amount): Result
    {
        try {
            $raw = $this->client->refundPayment(
                $transactionId->value(),
                ['amount' => $amount],
            );

            return Result::success($this->mapRefundStatus((string) ($raw['status'] ?? '')));
        } catch (RefundExceededException $e) {
            return Result::failure($e->getMessage());
        } catch (Throwable $e) {
            // Bubble the real exception class/message — do NOT fabricate
            // a RefundExceededException (N7 fix).
            return Result::failure(sprintf(
                'gateway_refund_transport_error [%s]: %s',
                $e::class,
                $e->getMessage(),
            ));
        }
    }

    /**
     * Map Razorpay refund status strings to TransactionStatus.
     * - 'processed' → REFUNDED (terminal positive outcome)
     * - 'pending'   → PENDING  (legitimate interim — orchestrator will retry)
     * - 'failed'    → FAILED   (rejected — orchestrator must NOT mark REFUNDED)
     * - anything else → PENDING (conservative default; orchestrator will retry)
     */
    private function mapRefundStatus(string $razorpayRefundStatus): TransactionStatus
    {
        return match (strtolower($razorpayRefundStatus)) {
            'processed' => TransactionStatus::REFUNDED,
            'failed' => TransactionStatus::FAILED,
            'pending' => TransactionStatus::PENDING,
            default => TransactionStatus::PENDING,
        };
    }

    public function providerName(): string
    {
        return 'razorpay';
    }

    public function supports(Currency $currency): bool
    {
        return $currency === Currency::INR;
    }

    public function enabled(): bool
    {
        return true; // Enabled state managed via PaymentProviderSelector
    }

    public function minimumAmount(): int
    {
        return 100; // ₹1 in paise
    }

    public function maximumAmount(): int
    {
        return 99_999_999; // ~₹1 crore
    }

    public function priority(): int
    {
        return 10;
    }

    /**
     * Map Razorpay status string to our TransactionStatus.
     */
    private function mapStatus(string $razorpayStatus): TransactionStatus
    {
        return match (strtolower($razorpayStatus)) {
            'created', 'attempted' => TransactionStatus::INITIALIZED,
            'authorized' => TransactionStatus::AUTHORIZED,
            'captured' => TransactionStatus::CAPTURED,
            'refunded' => TransactionStatus::REFUNDED,
            'failed' => TransactionStatus::FAILED,
            'pending' => TransactionStatus::PENDING,
            default => TransactionStatus::PENDING,
        };
    }
}
