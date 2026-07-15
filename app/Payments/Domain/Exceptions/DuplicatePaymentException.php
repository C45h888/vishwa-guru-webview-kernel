<?php

declare(strict_types=1);

namespace App\Payments\Domain\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Thrown when a payment intent collides with an already-processed payment.
 *
 * Sources of duplication:
 *   - Idempotency key reused with a different payload
 *   - Webhook callback arriving for an already-verified transaction
 *   - Replay of a captured payment by a malicious actor
 */
final class DuplicatePaymentException extends DomainException
{
    public function __construct(
        string $message,
        private readonly string $idempotencyKey,
        private readonly ?string $existingTransactionId = null,
    ) {
        parent::__construct($message);
    }

    public static function idempotencyKeyReused(string $key, string $existingTransactionId): self
    {
        return new self(
            sprintf(
                'Idempotency key [%s] already bound to transaction [%s]',
                $key,
                $existingTransactionId,
            ),
            $key,
            $existingTransactionId,
        );
    }

    public static function webhookReplay(string $gatewayOrderId, string $existingTransactionId): self
    {
        return new self(
            sprintf(
                'Webhook for gateway order [%s] replayed; transaction [%s] already verified',
                $gatewayOrderId,
                $existingTransactionId,
            ),
            $gatewayOrderId,
            $existingTransactionId,
        );
    }

    public function errorCode(): string
    {
        return 'payments.duplicate.detected';
    }

    public function idempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function existingTransactionId(): ?string
    {
        return $this->existingTransactionId;
    }

    public function context(): array
    {
        return [
            'idempotency_key' => $this->idempotencyKey,
            'existing_transaction_id' => $this->existingTransactionId,
        ];
    }
}