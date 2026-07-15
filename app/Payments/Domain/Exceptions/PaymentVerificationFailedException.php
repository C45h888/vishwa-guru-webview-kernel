<?php

declare(strict_types=1);

namespace App\Payments\Domain\Exceptions;

use App\Payments\Domain\Enums\TransactionStatus;
use App\Shared\Exceptions\DomainException;

/**
 * Thrown when a verified payment cannot be reconciled with the
 * gateway's authoritative state.
 *
 * Examples:
 *   - Gateway returned a status not present in TransactionStatus
 *   - Amount mismatch between local record and gateway response
 *   - Signature verified but response body was unparseable
 *   - Gateway returned a stale or already-refunded record
 */
final class PaymentVerificationFailedException extends DomainException
{
    /**
     * @param  array<string, mixed>  $verificationMetadata
     */
    public function __construct(
        string $message,
        private readonly string $gatewayOrderId,
        private readonly TransactionStatus $observedStatus,
        private readonly array $verificationMetadata = [],
    ) {
        parent::__construct($message);
    }

    public static function amountMismatch(
        string $gatewayOrderId,
        int $expectedMinor,
        int $observedMinor,
        string $currency,
    ): self {
        return new self(
            sprintf(
                'Amount mismatch for order [%s]: expected %d %s, gateway reported %d %s',
                $gatewayOrderId,
                $expectedMinor,
                $currency,
                $observedMinor,
                $currency,
            ),
            $gatewayOrderId,
            TransactionStatus::PENDING,
            [
                'expected_minor' => $expectedMinor,
                'observed_minor' => $observedMinor,
                'currency' => $currency,
            ],
        );
    }

    public static function unknownStatus(string $gatewayOrderId, string $rawStatus): self
    {
        return new self(
            sprintf('Gateway returned unknown status [%s] for order [%s]', $rawStatus, $gatewayOrderId),
            $gatewayOrderId,
            TransactionStatus::PENDING,
            ['raw_status' => $rawStatus],
        );
    }

    public static function staleRecord(string $gatewayOrderId, TransactionStatus $observed): self
    {
        return new self(
            sprintf('Gateway returned stale record for order [%s] with status [%s]', $gatewayOrderId, $observed->value),
            $gatewayOrderId,
            $observed,
        );
    }

    public function errorCode(): string
    {
        return 'payments.verification.failed';
    }

    public function gatewayOrderId(): string
    {
        return $this->gatewayOrderId;
    }

    public function observedStatus(): TransactionStatus
    {
        return $this->observedStatus;
    }

    public function context(): array
    {
        return [
            'gateway_order_id' => $this->gatewayOrderId,
            'observed_status' => $this->observedStatus->value,
            'verification_metadata' => $this->verificationMetadata,
        ];
    }
}