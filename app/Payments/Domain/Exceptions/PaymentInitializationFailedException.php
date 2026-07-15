<?php

declare(strict_types=1);

namespace App\Payments\Domain\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Thrown when a payment cannot be initialized at the gateway.
 *
 * Examples:
 *   - Gateway rejected the order request (invalid amount, currency, or metadata)
 *   - Gateway SDK threw during order creation
 *   - Idempotency key conflict on a fresh attempt
 *
 * The exception carries the gateway response so operators can diagnose
 * without re-running the failed request.
 */
final class PaymentInitializationFailedException extends DomainException
{
    /**
     * @param  array<string, mixed>  $gatewayResponse
     */
    public function __construct(
        string $message,
        private readonly string $providerCode,
        private readonly array $gatewayResponse = [],
    ) {
        parent::__construct($message);
    }

    public static function gatewayRejected(string $providerCode, string $reason, array $response = []): self
    {
        return new self(
            sprintf('Payment initialization rejected by gateway [%s]: %s', $providerCode, $reason),
            $providerCode,
            $response,
        );
    }

    public static function sdkFailure(string $providerCode, string $detail): self
    {
        return new self(
            sprintf('Payment gateway SDK [%s] raised an exception: %s', $providerCode, $detail),
            $providerCode,
        );
    }

    public static function idempotencyConflict(string $providerCode, string $key): self
    {
        return new self(
            sprintf('Idempotency key [%s] already used at provider [%s]', $key, $providerCode),
            $providerCode,
            ['idempotency_key' => $key],
        );
    }

    public function errorCode(): string
    {
        return 'payments.initialization.failed';
    }

    public function providerCode(): string
    {
        return $this->providerCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function gatewayResponse(): array
    {
        return $this->gatewayResponse;
    }

    public function context(): array
    {
        return [
            'provider_code' => $this->providerCode,
            'gateway_response' => $this->gatewayResponse,
        ];
    }
}