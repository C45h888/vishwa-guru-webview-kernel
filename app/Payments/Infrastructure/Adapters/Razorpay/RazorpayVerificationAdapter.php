<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Adapters\Razorpay;

use App\Payments\Contracts\PaymentVerificationContract;
use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Domain\Exceptions\WebhookVerificationFailedException;
use App\Shared\Contracts\ConfigurationContract;
use App\Shared\Support\Result;

/**
 * Razorpay implementation of PaymentVerificationContract.
 * Handles webhook signature verification for Razorpay callbacks.
 */
final class RazorpayVerificationAdapter implements PaymentVerificationContract
{
    public function __construct(
        private RazorpayClient $client,
        private ConfigurationContract $config,
    ) {}

    /**
     * Verify a Razorpay webhook callback.
     *
     * Doctrine: the configured header name (default: X-Razorpay-Signature) is
     * read from config so deployments behind a stripping proxy can override
     * without touching this class. Header lookup is case-insensitive because
     * Symfony's HeaderBag preserves source-case but downstream consumers may
     * not.
     */
    public function verifyWebhook(array $headers, string $payload): Result
    {
        $configuredHeader = (string) $this->config->get(
            'payments.providers.razorpay.webhook_signature_header',
            'X-Razorpay-Signature',
        );
        $signature = $this->extractHeader($headers, $configuredHeader);

        if ($signature === null || $signature === '') {
            return Result::failure(
                WebhookVerificationFailedException::missingSignature('razorpay', '')->getMessage(),
            );
        }

        $webhookSecret = (string) $this->config->get(
            'payments.providers.razorpay.webhook_secret',
            '',
        );

        if ($webhookSecret === '') {
            return Result::failure(
                WebhookVerificationFailedException::malformedPayload(
                    'razorpay',
                    'Webhook secret not configured',
                )->getMessage(),
            );
        }

        if (! $this->client->verifyWebhookSignature($payload, $signature, $webhookSecret)) {
            return Result::failure(
                WebhookVerificationFailedException::invalidSignature('razorpay', '')->getMessage(),
            );
        }

        $body = json_decode($payload, true) ?? [];
        $payloadPayment = $body['payload']['payment']['entity'] ?? [];
        $payloadOrder = $body['payload']['order']['entity'] ?? [];

        // Wave 1 B1 fix (2026-08-06): the order id can live in three
        // places depending on which webhook event Razorpay fired:
        //   - order.* events (order.paid, order.expired, etc.):
        //       payload.order.entity.id
        //   - payment.captured / payment.failed / payment.authorized:
        //       payload.payment.entity.order_id
        //     (and payload.order.entity is typically NOT present)
        //   - error scenarios — only the payment entity is present.
        // The previous code only checked payload.order.entity.id,
        // causing every canonical payment.captured webhook (the
        // donation-flipping event for Razorpay Standard Checkout) to
        // produce gateway_order_id = '' and fail Stage 2 of
        // PaymentVerificationService.
        $orderIdFromOrder = (string) ($payloadOrder['id'] ?? '');
        $orderIdFromPayment = (string) ($payloadPayment['order_id'] ?? '');
        $gatewayOrderId = $orderIdFromPayment !== ''
            ? $orderIdFromPayment
            : $orderIdFromOrder;

        return Result::success([
            'gateway_order_id' => $gatewayOrderId,
            'gateway_payment_id' => (string) ($payloadPayment['id'] ?? ''),
            'status' => $this->mapStatus((string) ($payloadPayment['status'] ?? '')),
            'amount' => (int) ($payloadPayment['amount'] ?? 0),
            'currency' => (string) ($payloadPayment['currency'] ?? 'INR'),
            'method' => (string) ($payloadPayment['method'] ?? ''),
        ]);
    }

    /**
     * Verify a signature string against a payload.
     */
    public function verifySignature(string $payload, string $signature): bool
    {
        $webhookSecret = (string) $this->config->get(
            'payments.providers.razorpay.webhook_secret',
            '',
        );

        return $this->client->verifyWebhookSignature($payload, $signature, $webhookSecret);
    }

    /**
     * Generate a signature for outgoing requests or test fixtures.
     */
    public function generateSignature(string $payload): string
    {
        $webhookSecret = (string) $this->config->get(
            'payments.providers.razorpay.webhook_secret',
            '',
        );

        return hash_hmac('sha256', $payload, $webhookSecret);
    }

    /**
     * Map Razorpay payment status to TransactionStatus.
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

    /**
     * Locate a header value by name, case-insensitively. Returns null when
     * the header is absent. Multi-value headers return the first value.
     *
     * @param  array<string, string|array<int, string>>  $headers
     */
    private function extractHeader(array $headers, string $name): ?string
    {
        $needle = strtolower($name);
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) !== $needle) {
                continue;
            }
            if (is_array($value)) {
                $first = $value[0] ?? null;
                return $first === null ? null : (string) $first;
            }
            return (string) $value;
        }
        return null;
    }
}
