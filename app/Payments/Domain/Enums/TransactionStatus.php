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
        // SETTLED and PARTIALLY_REFUNDED are NOT terminal: post-settlement
        // refunds (full + partial) and dispute resolution are legitimate
        // next states. The PaymentStateMachine.allowedEvents() tables confirm
        // these transitions are valid; the previous isTerminal() short-
        // circuit at PaymentStateMachine.php:38 was blocking refund flows on
        // settled payments. See PaymentStateMachineTest lines 338-360
        // (now-green after Wave 1 fix).
        //
        // REFUNDED remains terminal — you can't double-refund a refund.
        // FAILED/CANCELLED/EXPIRED are also dead-ends with no recovery path.
        return match ($this) {
            self::FAILED,
            self::REFUNDED,
            self::CANCELLED,
            self::EXPIRED => true,
            default => false,
        };
    }

    /**
     * Whether a refund can be initiated FROM this status. Splits the
     * terminal-vs-refundable question: SETTLED and PARTIALLY_REFUNDED are
     * non-terminal AND refundable. Failed/Cancelled/Expired are non-terminal
     * in name only — refunding a failed/cancelled payment makes no sense
     * because no money moved.
     */
    public function isRefundable(): bool
    {
        return in_array($this, [
            self::CAPTURED,
            self::SETTLED,
            self::PARTIALLY_REFUNDED,
        ], true);
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
