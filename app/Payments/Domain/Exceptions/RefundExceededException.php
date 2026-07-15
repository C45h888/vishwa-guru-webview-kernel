<?php

declare(strict_types=1);

namespace App\Payments\Domain\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Thrown when a refund amount would violate schema constraints.
 *
 * Schema enforces:
 *   - payments.amount_refunded_minor <= payments.amount_captured_minor
 *   - status='refunded' => amount_refunded_minor = amount_captured_minor
 *   - status='partially_refunded' => 0 < refund < captured
 */
final class RefundExceededException extends DomainException
{
    public function __construct(
        string $message,
        private readonly string $transactionId,
        private readonly int $capturedMinor,
        private readonly int $alreadyRefundedMinor,
        private readonly int $attemptedMinor,
    ) {
        parent::__construct($message);
    }

    public static function exceedsCaptured(string $transactionId, int $captured, int $alreadyRefunded, int $attempted): self
    {
        $remaining = max(0, $captured - $alreadyRefunded);

        return new self(
            sprintf(
                'Refund of %d for transaction [%s] would exceed remaining refundable %d (captured=%d, refunded=%d)',
                $attempted,
                $transactionId,
                $remaining,
                $captured,
                $alreadyRefunded,
            ),
            $transactionId,
            $captured,
            $alreadyRefunded,
            $attempted,
        );
    }

    public static function refundingSettledFullyWithoutFullAmount(string $transactionId, int $captured): self
    {
        return new self(
            sprintf(
                'Cannot fully refund transaction [%s] with an amount different from captured %d',
                $transactionId,
                $captured,
            ),
            $transactionId,
            $captured,
            0,
            $captured,
        );
    }

    public function errorCode(): string
    {
        return 'payments.refund.exceeded';
    }

    public function transactionId(): string
    {
        return $this->transactionId;
    }

    public function capturedMinor(): int
    {
        return $this->capturedMinor;
    }

    public function alreadyRefundedMinor(): int
    {
        return $this->alreadyRefundedMinor;
    }

    public function attemptedMinor(): int
    {
        return $this->attemptedMinor;
    }

    public function context(): array
    {
        return [
            'transaction_id' => $this->transactionId,
            'captured_minor' => $this->capturedMinor,
            'already_refunded_minor' => $this->alreadyRefundedMinor,
            'attempted_minor' => $this->attemptedMinor,
        ];
    }
}