<?php

declare(strict_types=1);

namespace App\Payments\Domain\Enums;

/**
 * The delivery lifecycle of a Receipt.
 *
 * The string values match the `receipts.delivery_status` column from
 * schema-neon/V1-schema.sql. ReceiptStateMachine owns transition rules.
 *
 * Receipt content (file_asset_id, receipt_number, content_hash, issued_at,
 * donation_id, transaction_id) is SEPARATE from delivery state and is
 * treated as immutable post-issue.
 */
enum ReceiptDeliveryState: string
{
    case PENDING = 'pending';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';
    case BOUNCED = 'bounced';

    public function isTerminal(): bool
    {
        return $this === self::DELIVERED;
    }

    public function isPendingRetry(): bool
    {
        return $this === self::PENDING;
    }

    public function hasFailed(): bool
    {
        return in_array($this, [self::FAILED, self::BOUNCED], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::DELIVERED => 'Delivered',
            self::FAILED => 'Failed',
            self::BOUNCED => 'Bounced',
        };
    }
}