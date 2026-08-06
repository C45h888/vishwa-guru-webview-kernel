<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Adapters\Razorpay;

use App\Payments\Domain\Exceptions\PaymentInitializationFailedException;
use App\Payments\Infrastructure\Adapters\Common\GatewayErrorTranslator;
use Razorpay\Api\Api;
use Razorpay\Api\Errors;
use Throwable;

/**
 * Our SDK wrapper for Razorpay.
 *
 * This is the ONLY place in the codebase where new Razorpay\Api\Api(...) appears.
 * All Razorpay interaction flows through this class.
 */
final class RazorpayClient
{
    /**
     * Not declared `readonly` because tests inject a mock Razorpay\Api\Api
     * via ReflectionProperty::setValue(). PHP 8.2 does not allow reflection
     * writes to readonly properties (the $skipReadonlyChecks flag is 8.3+),
     * so this single property stays mutable. Production code paths only ever
     * assign it inside the constructor — see ::__construct below.
     *
     * @var Api
     */
    private $api;

    public function __construct(string $keyId, string $keySecret)
    {
        $this->api = new Api($keyId, $keySecret);
    }

    /**
     * Create a Razorpay order.
     *
     * @param array<string, mixed> $payload Razorpay-shaped fields
     * @return array<string, mixed> Raw Razorpay response
     * @throws PaymentInitializationFailedException
     */
    public function createOrder(array $payload): array
    {
        try {
            $order = $this->api->order->create($payload);

            return $order->toArray();
        } catch (Throwable $e) {
            throw GatewayErrorTranslator::forInitialization('razorpay', $e);
        }
    }

    /**
     * Fetch a Razorpay order by ID.
     *
     * @return array<string, mixed>
     * @throws PaymentInitializationFailedException
     */
    public function fetchOrder(string $orderId): array
    {
        try {
            $order = $this->api->order->fetch($orderId);

            return $order->toArray();
        } catch (Throwable $e) {
            throw GatewayErrorTranslator::forInitialization('razorpay', $e);
        }
    }

    /**
     * Fetch a Razorpay payment by ID.
     *
     * @return array<string, mixed>
     * @throws PaymentInitializationFailedException
     */
    public function fetchPayment(string $paymentId): array
    {
        try {
            $payment = $this->api->payment->fetch($paymentId);

            return $payment->toArray();
        } catch (Throwable $e) {
            throw GatewayErrorTranslator::forInitialization('razorpay', $e);
        }
    }

    /**
     * Initiate a refund for a payment.
     *
     * @param array<string, mixed> $body Refund parameters
     * @return array<string, mixed>
     * @throws PaymentInitializationFailedException
     */
    public function refundPayment(string $paymentId, array $body): array
    {
        try {
            $refund = $this->api->payment->fetch($paymentId)->refund($body);

            return $refund->toArray();
        } catch (Throwable $e) {
            throw GatewayErrorTranslator::forInitialization('razorpay', $e);
        }
    }

    /**
     * Verify a webhook signature using HMAC-SHA256.
     *
     * @param array<string, string> $headers
     */
    public function verifyWebhookSignature(
        string $payload,
        string $signatureHeader,
        string $webhookSecret,
    ): bool {
        $expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);

        return hash_equals($expectedSignature, $signatureHeader);
    }
}
