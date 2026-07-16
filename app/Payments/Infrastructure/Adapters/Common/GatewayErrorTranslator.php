<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Adapters\Common;

use App\Payments\Domain\Exceptions\PaymentInitializationFailedException;
use App\Payments\Domain\Exceptions\PaymentVerificationFailedException;
use App\Payments\Domain\Exceptions\RefundExceededException;
use App\Payments\Domain\Exceptions\WebhookVerificationFailedException;
use App\Shared\Exceptions\InfrastructureException;
use Throwable;

/**
 * Translates SDK-level exceptions into domain exceptions.
 * Centralizes error mapping so adapters remain thin.
 */
final class GatewayErrorTranslator
{
    /**
     * Translate SDK exception during payment initialization.
     */
    public static function forInitialization(string $providerCode, Throwable $e): PaymentInitializationFailedException
    {
        $message = $e->getMessage();

        if (str_contains($message, 'idempotency')) {
            return PaymentInitializationFailedException::idempotencyConflict(
                $providerCode,
                self::extractIdempotencyKey($message),
            );
        }

        if (str_contains($message, 'currency') || str_contains($message, 'amount')) {
            return PaymentInitializationFailedException::gatewayRejected(
                $providerCode,
                $message,
            );
        }

        return PaymentInitializationFailedException::sdkFailure($providerCode, $message);
    }

    /**
     * Translate SDK exception during verification.
     */
    public static function forVerification(string $gatewayOrderId, Throwable $e): PaymentVerificationFailedException
    {
        $message = $e->getMessage();

        if (str_contains($message, 'amount')) {
            return PaymentVerificationFailedException::amountMismatch(
                $gatewayOrderId,
                0,
                0,
                'unknown',
            );
        }

        if (str_contains($message, 'status')) {
            return PaymentVerificationFailedException::unknownStatus(
                $gatewayOrderId,
                self::extractStatus($message),
            );
        }

        return PaymentVerificationFailedException::amountMismatch(
            $gatewayOrderId,
            0,
            0,
            'INR',
        );
    }

    /**
     * Translate SDK exception during refund.
     */
    public static function forRefund(string $transactionId, int $capturedMinor, Throwable $e): RefundExceededException
    {
        $message = $e->getMessage();

        if (str_contains($message, 'exceed') || str_contains($message, 'refund')) {
            return RefundExceededException::exceedsCaptured(
                $transactionId,
                $capturedMinor,
                0,
                0,
            );
        }

        return RefundExceededException::exceedsCaptured(
            $transactionId,
            $capturedMinor,
            0,
            0,
        );
    }

    /**
     * Translate SDK exception during webhook verification.
     */
    public static function forWebhookVerification(
        string $providerCode,
        string $gatewayOrderId,
        Throwable $e,
    ): WebhookVerificationFailedException {
        $message = $e->getMessage();

        if (str_contains($message, 'signature')) {
            return WebhookVerificationFailedException::invalidSignature(
                $providerCode,
                $gatewayOrderId,
            );
        }

        if (str_contains($message, 'timestamp') || str_contains($message, 'expired')) {
            return WebhookVerificationFailedException::expiredTimestamp(
                $providerCode,
                $gatewayOrderId,
                0,
            );
        }

        return WebhookVerificationFailedException::malformedPayload(
            $providerCode,
            $message,
        );
    }

    /**
     * Wrap unclassified SDK errors as InfrastructureException.
     */
    public static function forUnclassified(Throwable $e, string $context): InfrastructureException
    {
        return new InfrastructureException(
            "Unclassified SDK error in {$context}: {$e->getMessage()}",
            $e->getCode(),
            $e,
        );
    }

    private static function extractIdempotencyKey(string $message): string
    {
        if (preg_match('/idempotency[_-]?key[:\s]+([^\s,]+)/i', $message, $matches)) {
            return $matches[1];
        }

        return 'unknown';
    }

    private static function extractStatus(string $message): string
    {
        if (preg_match('/status[:\s]+([^\s,]+)/i', $message, $matches)) {
            return $matches[1];
        }

        return 'unknown';
    }
}
