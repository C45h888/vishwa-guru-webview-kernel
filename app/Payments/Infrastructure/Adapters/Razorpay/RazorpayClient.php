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

            return $this->toArray($order);
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

            return $this->toArray($order);
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

            return $this->toArray($payment);
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

            return $this->toArray($refund);
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

    /**
     * Convert whatever the Razorpay SDK returns into an associative array.
     *
     * Wave 1 N1 fix (2026-08-06): the SDK historically returned objects
     * with a `toArray()` method (Razorpay\Api\Payment), but recent versions
     * and stubbed test environments can return plain stdClass or already-array
     * results. The adapter previously called `$obj->toArray()` unguarded,
     * which crashes on stdClass (TypeError: Call to undefined method
     * stdClass::toArray()) and on arrays (Call to a member function toArray()
     * on array). This helper makes the adapter tolerant of all three.
     *
     * Tolerant of: object with toArray(), plain stdClass, array. Anything
     * else throws — the caller is expected to forward a 502-class error.
     */
    private function toArray(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (is_object($raw) && method_exists($raw, 'toArray')) {
            // Razorpay\Api\* objects expose toArray() returning array.
            /** @var array<string, mixed> $result */
            $result = $raw->toArray();
            return $result;
        }
        if ($raw instanceof \stdClass) {
            // stdClass → cast each public property. Works for flat SDK
            // responses; nested stdClass values will be stdClass objects
            // in the result, which the adapter's typed accessors handle
            // (e.g. (string) ($raw->status ?? ''), (int) ($raw->amount ?? 0)).
            return (array) $raw;
        }
        throw new \RuntimeException(sprintf(
            'RazorpayClient::toArray: unsupported SDK response type [%s]',
            get_debug_type($raw),
        ));
    }
}
