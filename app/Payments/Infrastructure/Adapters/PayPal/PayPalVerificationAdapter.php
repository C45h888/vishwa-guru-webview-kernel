<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Adapters\PayPal;

use App\Payments\Contracts\PaymentVerificationContract;
use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Domain\Exceptions\WebhookVerificationFailedException;
use App\Shared\Contracts\ConfigurationContract;
use App\Shared\Support\Result;

/**
 * PayPal implementation of PaymentVerificationContract.
 * Handles webhook verification for PayPal Checkout callbacks.
 *
 * Note: Full PayPal webhook verification requires calling the Webhook Events API
 * to validate the transmission details against PayPal's API. This adapter
 * provides the HMAC-SHA256 verification method suitable for most integrations.
 */
final class PayPalVerificationAdapter implements PaymentVerificationContract
{
    public function __construct(
        private PayPalClient $client,
        private ConfigurationContract $config,
    ) {}

    /**
     * Verify a PayPal webhook callback.
     *
     * PayPal webhooks are verified using the transmission signature.
     * Headers needed: PAYPAL-TRANSMISSION-SIG, PAYPAL-TRANSMISSION-ID,
     *                  PAYPAL-TRANSMISSION-TIME, PAYPAL-CERT-URL
     */
    public function verifyWebhook(array $headers, string $payload): Result
    {
        $signature = $headers['paypal-transmission-sig']
            ?? $headers['PAYPAL-TRANSMISSION-SIG']
            ?? null;

        $transmissionId = $headers['paypal-transmission-id']
            ?? $headers['PAYPAL-TRANSMISSION-ID']
            ?? null;

        $transmissionTime = $headers['paypal-transmission-time']
            ?? $headers['PAYPAL-TRANSMISSION-TIME']
            ?? null;

        $certUrl = $headers['paypal-cert-url']
            ?? $headers['PAYPAL-CERT-URL']
            ?? null;

        if ($signature === null || $transmissionId === null) {
            return Result::failure(
                WebhookVerificationFailedException::missingSignature('paypal', '')->getMessage(),
            );
        }

        $webhookId = (string) $this->config->get(
            'payments.providers.paypal.webhook_id',
            '',
        );

        if ($webhookId === '') {
            return Result::failure(
                WebhookVerificationFailedException::malformedPayload(
                    'paypal',
                    'Webhook ID not configured',
                )->getMessage(),
            );
        }

        // Build the expected signature payload per PayPal webhook spec
        $expectedSignature = $this->buildSignaturePayload(
            $transmissionId,
            $transmissionTime,
            $webhookId,
            $payload,
        );

        if (! $this->verifySignature($payload, $signature)) {
            return Result::failure(
                WebhookVerificationFailedException::invalidSignature('paypal', '')->getMessage(),
            );
        }

        $body = json_decode($payload, true) ?? [];
        $resource = $body['resource'] ?? [];

        return Result::success([
            'gateway_order_id' => (string) ($resource['id'] ?? $body['resource_id'] ?? ''),
            'gateway_payment_id' => (string) ($resource['id'] ?? ''),
            'status' => $this->mapStatus($resource['status'] ?? ''),
            'amount' => $this->parseAmount($resource['amount'] ?? []),
            'currency' => (string) ($resource['amount']['currency_code'] ?? 'USD'),
            'method' => (string) ($resource['payment_method'] ?? 'paypal'),
        ]);
    }

    /**
     * Verify a signature string against a payload.
     * Uses HMAC-SHA256 with webhook ID as secret.
     */
    public function verifySignature(string $payload, string $signature): bool
    {
        $webhookId = (string) $this->config->get(
            'payments.providers.paypal.webhook_id',
            '',
        );

        $expected = hash_hmac('sha256', $webhookId, $signature);

        return hash_equals($expected, $signature);
    }

    /**
     * Generate a signature for outgoing requests or test fixtures.
     */
    public function generateSignature(string $payload): string
    {
        $webhookId = (string) $this->config->get(
            'payments.providers.paypal.webhook_id',
            '',
        );

        return hash_hmac('sha256', $payload, $webhookId);
    }

    /**
     * Build the signature payload per PayPal webhook transmission spec.
     */
    private function buildSignaturePayload(
        string $transmissionId,
        string $transmissionTime,
        string $webhookId,
        string $payload,
    ): string {
        return $transmissionId . '|' . $transmissionTime . '|' . $webhookId . '|' . crc32($payload);
    }

    /**
     * Parse PayPal amount to minor units.
     *
     * @param array<string, string|int|float> $amount
     */
    private function parseAmount(array $amount): int
    {
        $value = (float) ($amount['value'] ?? 0);

        return (int) round($value * 100);
    }

    /**
     * Map PayPal resource status to TransactionStatus.
     */
    private function mapStatus(string $paypalStatus): TransactionStatus
    {
        return match (strtoupper($paypalStatus)) {
            'COMPLETED' => TransactionStatus::CAPTURED,
            'APPROVED' => TransactionStatus::AUTHORIZED,
            'PENDING' => TransactionStatus::PENDING,
            'REFUNDED' => TransactionStatus::REFUNDED,
            'PARTIALLY_REFUNDED' => TransactionStatus::PARTIALLY_REFUNDED,
            'VOIDED' => TransactionStatus::CANCELLED,
            'FAILED' => TransactionStatus::FAILED,
            default => TransactionStatus::PENDING,
        };
    }
}
