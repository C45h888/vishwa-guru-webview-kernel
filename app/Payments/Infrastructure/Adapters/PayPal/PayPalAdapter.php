<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Adapters\PayPal;

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
 * PayPal implementation of PaymentGatewayContract.
 * Translates our domain types to PayPal Orders API payloads and back.
 */
final class PayPalAdapter implements PaymentGatewayContract
{
    public function __construct(
        private PayPalClient $client,
    ) {}

    /**
     * Initialize a PayPal order.
     */
    public function initialize(PaymentRequest $request): Result
    {
        $amount = $this->formatAmount($request->amount(), $request->currency());

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => $request->idempotencyKey()
                        ?? $request->donorIdentifier()->value(),
                    'description' => $request->purpose(),
                    'amount' => $amount,
                    'custom_id' => $request->donorIdentifier()->value(),
                ],
            ],
        ];

        try {
            $raw = $this->client->createOrder($payload);

            // Find the approve link for checkout URL
            $checkoutUrl = null;
            $links = $raw['links'] ?? [];
            foreach ($links as $link) {
                if (($link['rel'] ?? '') === 'approve') {
                    $checkoutUrl = $link['href'] ?? null;
                    break;
                }
            }

            $status = $this->mapStatus($raw['status'] ?? 'CREATED');

            return Result::success(new GatewayResponseDTO(
                providerCode: 'paypal',
                gatewayOrderId: (string) ($raw['id'] ?? ''),
                gatewayPaymentId: null,
                rawStatusString: (string) ($raw['status'] ?? 'CREATED'),
                amountMinor: $this->parseAmount($raw['amount'] ?? []),
                currency: Currency::from($raw['amount']['currency_code'] ?? $request->currency()->value),
                rawResponse: $raw,
                checkoutUrl: $checkoutUrl,
                method: 'paypal',
            ));
        } catch (PaymentInitializationFailedException $e) {
            return Result::failure($e->getMessage());
        } catch (Throwable $e) {
            return Result::failure(
                PaymentInitializationFailedException::sdkFailure('paypal', $e->getMessage())->getMessage(),
            );
        }
    }

    /**
     * Verify a PayPal order's status.
     */
    public function verify(string $gatewayOrderId, ?string $gatewayPaymentId = null): Result
    {
        try {
            $raw = $this->client->fetchOrder($gatewayOrderId);
            $status = $this->mapStatus($raw['status'] ?? '');

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
     * Capture a PayPal order (explicit capture required).
     */
    public function capture(Identifier $transactionId, int $amount): Result
    {
        try {
            $raw = $this->client->captureOrder($transactionId->value());
            $status = $this->mapStatus($raw['status'] ?? '');

            return Result::success($status);
        } catch (Throwable $e) {
            return Result::failure(
                PaymentVerificationFailedException::unknownStatus(
                    $transactionId->value(),
                    $e->getMessage(),
                )->getMessage(),
            );
        }
    }

    /**
     * Refund a captured PayPal payment.
     */
    public function refund(Identifier $transactionId, int $amount): Result
    {
        // PayPal refund requires the capture_id, not the order_id.
        // The transactionId in our system maps to the PayPal capture_id.
        try {
            $raw = $this->client->refundCapture($transactionId->value(), [
                'amount' => [
                    'value' => $this->formatAmountValue($amount),
                    'currency_code' => 'USD',
                ],
            ]);

            $status = ((string) ($raw['status'] ?? '')) === 'COMPLETED'
                ? TransactionStatus::REFUNDED
                : TransactionStatus::PENDING;

            return Result::success($status);
        } catch (RefundExceededException $e) {
            return Result::failure($e->getMessage());
        } catch (Throwable $e) {
            return Result::failure(
                RefundExceededException::exceedsCaptured(
                    $transactionId->value(),
                    $amount,
                    0,
                    $amount,
                )->getMessage(),
            );
        }
    }

    public function providerName(): string
    {
        return 'paypal';
    }

    public function supports(Currency $currency): bool
    {
        return in_array($currency, [
            Currency::USD,
            Currency::EUR,
            Currency::GBP,
            Currency::CAD,
            Currency::AUD,
            Currency::SGD,
        ], true);
    }

    public function enabled(): bool
    {
        return true; // Enabled state managed via PaymentProviderSelector
    }

    public function minimumAmount(): int
    {
        return 500; // $5.00 in cents
    }

    public function maximumAmount(): int
    {
        return 999_999_99; // ~$999,999.99
    }

    public function priority(): int
    {
        return 20;
    }

    /**
     * Format amount for PayPal API.
     *
     * @return array{currency_code: string, value: string}
     */
    private function formatAmount(int $minorAmount, Currency $currency): array
    {
        $value = number_format($minorAmount / 100, 2, '.', '');

        return [
            'currency_code' => $currency->value,
            'value' => $value,
        ];
    }

    /**
     * Parse PayPal amount back to minor units.
     *
     * @param array<string, string|int|float> $amount
     */
    private function parseAmount(array $amount): int
    {
        $value = (float) ($amount['value'] ?? 0);

        return (int) round($value * 100);
    }

    /**
     * Format amount value string for refund.
     */
    private function formatAmountValue(int $minorAmount): string
    {
        return number_format($minorAmount / 100, 2, '.', '');
    }

    /**
     * Map PayPal order status to our TransactionStatus.
     */
    private function mapStatus(string $paypalStatus): TransactionStatus
    {
        return match (strtoupper($paypalStatus)) {
            'CREATED' => TransactionStatus::INITIALIZED,
            'APPROVED' => TransactionStatus::AUTHORIZED,
            'VOIDED' => TransactionStatus::CANCELLED,
            'COMPLETED' => TransactionStatus::CAPTURED,
            'CAPTURED' => TransactionStatus::CAPTURED,
            'REFUNDED' => TransactionStatus::REFUNDED,
            'PARTIALLY_REFUNDED' => TransactionStatus::PARTIALLY_REFUNDED,
            'FAILED' => TransactionStatus::FAILED,
            default => TransactionStatus::PENDING,
        };
    }
}
