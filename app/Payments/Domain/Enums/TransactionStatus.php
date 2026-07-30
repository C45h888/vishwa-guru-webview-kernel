<?php

declare(strict_types=1);

namespace App\Payments\Domain\Enums;

/**
 * The status of a payment transaction.
 * Covers the full lifecycle from initiation to terminal resolution.
 */
enum TransactionStatus: string
{
    case INITIALIZED = 'initialized';
    case PENDING = 'pending';
    case AUTHORIZED = 'authorized';
    case CAPTURED = 'captured';
    case SETTLING = 'settling';
    case SETTLED = 'settled';
    case FAILED = 'failed';
    case REFUNDED = 'refunded';
    case PARTIALLY_REFUNDED = 'partially_refunded';
    case DISPUTED = 'disputed';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::FAILED,
            self::REFUNDED,
            self::PARTIALLY_REFUNDED,
            self::CANCELLED,
            self::EXPIRED,
            self::SETTLED => true,
            default => false,
        };
    }

    public function isSuccessful(): bool
    {
        return in_array($this, [
            self::CAPTURED,
            self::SETTLING,
            self::SETTLED,
        ], true);
    }

    public function requiresReconciliation(): bool
    {
        return in_array($this, [
            self::AUTHORIZED,
            self::CAPTURED,
            self::SETTLING,
        ], true);
    }

    public function canBeRefunded(): bool
    {
        return in_array($this, [
            self::CAPTURED,
            self::SETTLED,
        ], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::INITIALIZED => 'Initialized',
            self::PENDING => 'Pending',
            self::AUTHORIZED => 'Authorized',
            self::CAPTURED => 'Captured',
            self::SETTLING => 'Settling',
            self::SETTLED => 'Settled',
            self::FAILED => 'Failed',
            self::REFUNDED => 'Refunded',
            self::PARTIALLY_REFUNDED => 'Partially Refunded',
            self::DISPUTED => 'Disputed',
            self::CANCELLED => 'Cancelled',
            self::EXPIRED => 'Expired',
        };
    }
}
