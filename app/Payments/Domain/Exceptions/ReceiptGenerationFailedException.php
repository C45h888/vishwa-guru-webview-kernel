<?php

declare(strict_types=1);

namespace App\Payments\Domain\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Thrown when a receipt cannot be generated, stored, or delivered
 * for an otherwise verified payment.
 *
 * Receipt generation failure does NOT roll back the payment; the payment
 * remains verified and the ReceiptOrchestrator writes a FailureState
 * with classification=RECOVERABLE_TERMINAL so an operator can retry
 * the receipt workflow manually.
 */
final class ReceiptGenerationFailedException extends DomainException
{
    public function __construct(
        string $message,
        private readonly string $transactionId,
        private readonly string $stage,
    ) {
        parent::__construct($message);
    }

    public static function renderingFailed(string $transactionId, string $reason): self
    {
        return new self(
            sprintf('Receipt rendering failed for transaction [%s]: %s', $transactionId, $reason),
            $transactionId,
            'rendering',
        );
    }

    public static function storageFailed(string $transactionId, string $reason): self
    {
        return new self(
            sprintf('Receipt storage write failed for transaction [%s]: %s', $transactionId, $reason),
            $transactionId,
            'storage',
        );
    }

    public static function metadataPersistFailed(string $transactionId, string $reason): self
    {
        return new self(
            sprintf('Receipt metadata persistence failed for transaction [%s]: %s', $transactionId, $reason),
            $transactionId,
            'metadata_persist',
        );
    }

    public function errorCode(): string
    {
        return 'payments.receipt.generation.failed';
    }

    public function transactionId(): string
    {
        return $this->transactionId;
    }

    public function stage(): string
    {
        return $this->stage;
    }

    public function context(): array
    {
        return [
            'transaction_id' => $this->transactionId,
            'stage' => $this->stage,
        ];
    }
}