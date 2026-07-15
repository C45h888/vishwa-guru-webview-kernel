<?php

declare(strict_types=1);

namespace App\Payments\Domain\Enums;

/**
 * Donation lifecycle states.
 *
 * The string values MUST match the `donation_state` PostgreSQL enum defined
 * in `schema-neon/V1-schema.sql`. The donations table also enforces a
 * CHECK constraint that each state MUST have its corresponding timestamp
 * populated (e.g. state='completed' => completed_at IS NOT NULL).
 *
 * State transitions are decided exclusively by the PaymentStateMachine
 * service in Pass 1.2. Direct mutation of donation.state is forbidden
 * outside the state machine.
 */
enum DonationState: string
{
    case DRAFT = 'draft';
    case PENDING_PAYMENT = 'pending_payment';
    case PAYMENT_VERIFIED = 'payment_verified';
    case RECEIPT_GENERATED = 'receipt_generated';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    /**
     * Whether this state represents a terminal donation outcome.
     * Terminal states have no further transitions.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::COMPLETED, self::FAILED, self::CANCELLED => true,
            self::DRAFT, self::PENDING_PAYMENT, self::PAYMENT_VERIFIED, self::RECEIPT_GENERATED => false,
        };
    }

    /**
     * Whether this state represents a successful donation.
     */
    public function isSuccessful(): bool
    {
        return match ($this) {
            self::COMPLETED, self::RECEIPT_GENERATED, self::PAYMENT_VERIFIED => true,
            default => false,
        };
    }

    /**
     * Whether the donation is still awaiting a payment outcome.
     */
    public function isAwaitingPayment(): bool
    {
        return $this === self::PENDING_PAYMENT;
    }

    /**
     * Whether a receipt has been issued for this donation.
     */
    public function hasReceipt(): bool
    {
        return in_array($this, [
            self::RECEIPT_GENERATED,
            self::COMPLETED,
        ], true);
    }

    /**
     * The timestamp column that MUST be populated when a row is in this state.
     * Mirrors the donations_state_*_ts CHECK constraints in the schema.
     */
    public function requiredTimestampColumn(): ?string
    {
        return match ($this) {
            self::PENDING_PAYMENT => 'payment_initiated_at',
            self::PAYMENT_VERIFIED => 'payment_verified_at',
            self::RECEIPT_GENERATED => 'receipt_generated_at',
            self::COMPLETED => 'completed_at',
            self::FAILED => 'failed_at',
            self::CANCELLED => 'cancelled_at',
            self::DRAFT => null,
        };
    }

    /**
     * Human-readable label for operator-facing surfaces.
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PENDING_PAYMENT => 'Pending Payment',
            self::PAYMENT_VERIFIED => 'Payment Verified',
            self::RECEIPT_GENERATED => 'Receipt Generated',
            self::COMPLETED => 'Completed',
            self::FAILED => 'Failed',
            self::CANCELLED => 'Cancelled',
        };
    }
}