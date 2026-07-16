<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Adapters\PayPal;

use App\Payments\Domain\Exceptions\PaymentInitializationFailedException;
use App\Payments\Infrastructure\Adapters\Common\GatewayErrorTranslator;
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Core\LiveEnvironment;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;
use PayPalCheckoutSdk\Orders\OrdersGetRequest;
use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;
use PayPalCheckoutSdk\Payments\CapturesRefundRequest;
use PayPalHttp\HttpException;
use Throwable;

/**
 * Our SDK wrapper for PayPal Checkout SDK.
 *
 * This is the ONLY place in the codebase where PayPal SDK classes are instantiated.
 * All PayPal interaction flows through this class.
 */
final readonly class PayPalClient
{
    private PayPalHttpClient $httpClient;

    public function __construct(string $clientId, string $clientSecret, bool $sandbox = true)
    {
        $environment = $sandbox
            ? new SandboxEnvironment($clientId, $clientSecret)
            : new LiveEnvironment($clientId, $clientSecret);

        $this->httpClient = new PayPalHttpClient($environment);
    }

    /**
     * Create a PayPal order.
     *
     * @param array<string, mixed> $payload PayPal-shaped fields
     * @return array<string, mixed> Raw PayPal response
     * @throws PaymentInitializationFailedException
     */
    public function createOrder(array $payload): array
    {
        try {
            $request = new OrdersCreateRequest();
            $request->prefer('return=representation');
            $request->body = $payload;

            $response = $this->httpClient->execute($request);

            return $this->flattenOrderResponse($response->result);
        } catch (Throwable $e) {
            throw GatewayErrorTranslator::forInitialization('paypal', $e);
        }
    }

    /**
     * Fetch a PayPal order by ID.
     *
     * @return array<string, mixed>
     * @throws PaymentInitializationFailedException
     */
    public function fetchOrder(string $orderId): array
    {
        try {
            $request = new OrdersGetRequest($orderId);
            $response = $this->httpClient->execute($request);

            return $this->flattenOrderResponse($response->result);
        } catch (Throwable $e) {
            throw GatewayErrorTranslator::forInitialization('paypal', $e);
        }
    }

    /**
     * Capture a PayPal order.
     *
     * @return array<string, mixed>
     * @throws PaymentInitializationFailedException
     */
    public function captureOrder(string $orderId): array
    {
        try {
            $request = new OrdersCaptureRequest($orderId);
            $request->prefer('return=representation');
            $response = $this->httpClient->execute($request);

            return $this->flattenOrderResponse($response->result);
        } catch (Throwable $e) {
            throw GatewayErrorTranslator::forInitialization('paypal', $e);
        }
    }

    /**
     * Refund a captured payment.
     *
     * @param array<string, mixed> $body Refund parameters including capture_id
     * @return array<string, mixed>
     * @throws PaymentInitializationFailedException
     */
    public function refundCapture(string $captureId, array $body): array
    {
        try {
            $request = new CapturesRefundRequest($captureId);
            $request->prefer('return=representation');
            $request->body = $body;
            $response = $this->httpClient->execute($request);

            return (array) $response->result;
        } catch (Throwable $e) {
            throw GatewayErrorTranslator::forInitialization('paypal', $e);
        }
    }

    /**
     * Flatten a PayPal order object to a plain array.
     *
     * @param mixed $result
     * @return array<string, mixed>
     */
    private function flattenOrderResponse(mixed $result): array
    {
        $arr = (array) $result;

        // Flatten purchase_units[0] to top level for easier access
        if (isset($arr['purchase_units']) && is_array($arr['purchase_units'])) {
            $firstUnit = $arr['purchase_units'][0] ?? [];
            if (is_object($firstUnit)) {
                $firstUnit = (array) $firstUnit;
            }
            $arr = array_merge($arr, [
                'order_id' => $arr['id'] ?? null,
                'amount' => $firstUnit['amount'] ?? [],
                'status' => $arr['status'] ?? null,
                'payer' => isset($arr['payer']) ? (array) $arr['payer'] : [],
                'create_time' => $arr['create_time'] ?? null,
                'update_time' => $arr['update_time'] ?? null,
            ]);
        }

        return $arr;
    }
}
