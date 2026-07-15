<?php

declare(strict_types=1);

namespace App\Payments\Domain\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Thrown when a webhook callback fails signature verification.
 *
 * WebhookVerificationFailedException is ALWAYS terminal — a callback with
 * an invalid signature MUST NOT be processed under any circumstance,
 * and the FailureStateManager MUST classify it as TERMINAL_INVALID.
 */
final class WebhookVerificationFailedException extends DomainException
{
    public function __construct(
        string $message,
        private readonly string $providerCode,
        private readonly string $gatewayOrderId,
        private readonly string $reason,
    ) {
        parent::__construct($message);
    }

    public static function invalidSignature(string $providerCode, string $gatewayOrderId): self
    {
        return new self(
            sprintf('Webhook signature invalid for provider [%s], order [%s]', $providerCode, $gatewayOrderId),
            $providerCode,
            $gatewayOrderId,
            'invalid_signature',
        );
    }

    public static function missingSignature(string $providerCode, string $gatewayOrderId): self
    {
        return new self(
            sprintf('Webhook missing signature header for provider [%s], order [%s]', $providerCode, $gatewayOrderId),
            $providerCode,
            $gatewayOrderId,
            'missing_signature',
        );
    }

    public static function expiredTimestamp(string $providerCode, string $gatewayOrderId, int $ageSeconds): self
    {
        return new self(
            sprintf(
                'Webhook timestamp expired (age=%ds) for provider [%s], order [%s]',
                $ageSeconds,
                $providerCode,
                $gatewayOrderId,
            ),
            $providerCode,
            $gatewayOrderId,
            'expired_timestamp',
        );
    }

    public static function malformedPayload(string $providerCode, string $detail): self
    {
        return new self(
            sprintf('Webhook payload malformed for provider [%s]: %s', $providerCode, $detail),
            $providerCode,
            '',
            'malformed_payload',
        );
    }

    public function errorCode(): string
    {
        return 'payments.webhook.verification.failed';
    }

    public function providerCode(): string
    {
        return $this->providerCode;
    }

    public function gatewayOrderId(): string
    {
        return $this->gatewayOrderId;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function context(): array
    {
        return [
            'provider_code' => $this->providerCode,
            'gateway_order_id' => $this->gatewayOrderId,
            'reason' => $this->reason,
        ];
    }
}