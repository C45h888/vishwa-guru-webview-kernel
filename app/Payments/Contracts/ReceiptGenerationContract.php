<?php

declare(strict_types=1);

namespace App\Payments\Contracts;

use App\Shared\Support\Result;
use App\Shared\ValueObjects\Identifier;

/**
 * Generates receipts for completed payment transactions.
 * Receipts are immutable artifacts produced after a successful
 * gateway-verified payment.
 */
interface ReceiptGenerationContract
{
    /**
     * Generate a receipt for a settled payment.
     *
     * @return Result<array{receipt_number: string, issued_at: string, download_url: string}>
     */
    public function generate(Identifier $transactionId): Result;

    /**
     * Generate a receipt number for a given transaction.
     * Format: TR-YYYY-{transaction_short_id}
     */
    public function receiptNumber(Identifier $transactionId): string;

    /**
     * Whether receipts are enabled in the current environment.
     */
    public function isEnabled(): bool;
}